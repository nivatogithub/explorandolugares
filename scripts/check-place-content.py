#!/usr/bin/env python3
"""Exercise migration and real PHP rendering in a new, disposable package fixture."""
from pathlib import Path
import argparse
import importlib.util
from html import unescape
import json
import sqlite3
import subprocess

ROOT = Path(__file__).resolve().parents[1]


def php(code, payload=None, ok=True):
    result = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', code],
                            input=json.dumps(payload or {}), text=True, capture_output=True)
    if ok:
        assert result.returncode == 0 and not result.stderr, result.stderr
    else:
        assert result.returncode != 0, 'Expected a rejected migration'
    return result


def run(package, output):
    package = package.resolve()
    assert package.is_relative_to(ROOT / '.local') and (package / 'build-manifest.json').is_file()
    assert not (package / 'private/presentation.sqlite').exists(), 'Use a NEW package fixture'
    spec = importlib.util.spec_from_file_location('fixture', ROOT / 'scripts/prepare-presentation-fixture.py')
    fixture = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(fixture)
    fixture.prepare(package, migrate_content=False)
    database = package / 'private/presentation.sqlite'
    connection = sqlite3.connect(database)
    plan = json.loads((package / 'app/content/destination-content-060.json').read_text())
    migration_code = 'require ' + json.dumps(str(package / 'app/PlaceContentMigration.php')) + ''';
    $i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $p=new PDO('sqlite:'.$i['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    try{echo json_encode((new PlaceContentMigration($p))->run($i['plan'],$i['public'],$i['apply']));}
    catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}'''

    def migrate(path=database, apply=False, data=plan, ok=True):
        return php(migration_code, {'database': str(path), 'public': str(package / 'public'),
                                   'plan': data, 'apply': apply}, ok)

    def dump():
        return list(connection.iterdump())

    def protected():
        # Every non-editorial table, entity row, map, legal credit and provenance remains intact.
        excluded = {'destination_translations', 'destination_story_translations',
                    'point_of_interest_translations', 'media_assets', 'media_asset_translations',
                    'destination_media', 'point_of_interest_media', 'destination_content_migrations'}
        tables = [r[0] for r in connection.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")]
        rows = {t: connection.execute('SELECT * FROM ' + t + ' ORDER BY rowid').fetchall()
                for t in tables if t not in excluded and t != 'sqlite_sequence'}
        rows['poi_links'] = connection.execute('SELECT point_of_interest_id,locale,map_url,external_url,external_label FROM point_of_interest_translations ORDER BY point_of_interest_id,locale').fetchall()
        rows['media_legal'] = connection.execute('SELECT id,asset_path,origin,creator,source_url,license_name,license_url,provenance_json FROM media_assets ORDER BY id').fetchall()
        return rows

    before, immutable = dump(), protected()
    conflict = package / 'private/conflict.sqlite'
    with sqlite3.connect(conflict) as target:
        connection.backup(target)
        target.execute("UPDATE destination_translations SET intro='Edición posterior que se debe conservar' WHERE locale='es' AND destination_id=(SELECT id FROM destinations WHERE slug='jerico')")
    with sqlite3.connect(conflict) as target:
        conflict_before = list(target.iterdump())
    assert 'Contenido cambiado' in migrate(conflict, True, ok=False).stderr
    with sqlite3.connect(conflict) as target:
        assert list(target.iterdump()) == conflict_before, 'Failure must roll back earlier destinations too'
    # A missing/mutated image also aborts the whole data transfer.
    invalid = json.loads(json.dumps(plan))
    next(iter(invalid['destinations']['bogota']['media'].values()))['files']['assets/missing.webp'] = '0' * 64
    assert 'Medio aprobado' in migrate(conflict, True, invalid, ok=False).stderr
    first = json.loads(migrate().stdout)
    assert first['changes'] > 0 and not first['applied'] and dump() == before, 'Dry-run changed the fixture'
    applied = json.loads(migrate(apply=True).stdout)
    assert applied['applied'] and applied['changes'] == first['changes'] and protected() == immutable
    after = dump()
    repeated = json.loads(migrate(apply=True).stdout)
    assert repeated['already_applied'] and repeated['changes'] == 0 and dump() == after
    changed_plan = json.loads(json.dumps(plan))
    changed_plan['base_commit'] = 'unexpected-plan-revision'
    assert 'otro hash' in migrate(apply=True, data=changed_plan, ok=False).stderr
    assert dump() == after

    repository_code = 'require ' + json.dumps(str(package / 'app/PlaceRepository.php')) + ''';
    $i=json_decode(stream_get_contents(STDIN),true);
    $p=new PDO('sqlite:'.$i['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    echo json_encode((PlaceRepository::fromDatabase($p))->findPublished($i['slug'],$i['locale']));'''

    def repository(slug, locale):
        return json.loads(php(repository_code, {'database': str(database), 'slug': slug, 'locale': locale}).stdout)

    def render(slug, locale):
        prefix = {'es': '', 'en': '/en', 'pt-BR': '/pt-br'}[locale]
        code = '$_GET=' + 'json_decode(' + json.dumps(json.dumps({'destino': slug, 'idioma': locale})) + ',true);'
        code += '$_SERVER["REQUEST_URI"]=' + json.dumps(prefix + '/lugares/' + slug + '/') + ';'
        code += '$_SERVER["HTTP_HOST"]="candidate.localhost";$_SERVER["REQUEST_METHOD"]="GET";require ' + json.dumps(str(package / 'public/place.php')) + ';'
        return php(code).stdout

    checked = []
    for slug, content in plan['destinations'].items():
        for locale, fields in content['translations'].items():
            destination = repository(slug, locale)
            html = render(slug, locale)
            for field, values in fields.items():
                assert destination[field] == values['after'], (slug, locale, field)
            places = {p['slug']: p for p in destination['places']}
            for place_slug, translations in content['places'].items():
                for field, values in translations[locale].items():
                    assert places[place_slug][field] == values['after'], (slug, place_slug, locale, field)
            for place_slug, media in content['media'].items():
                actual = destination['hero'] if place_slug == 'hero' else places[place_slug]['media'][0]
                assert actual['path'] == media['path'] and actual['alt'] == media['translations'][locale]['after']
                assert actual['srcset'] == media['srcset'] and actual['sizes'] == media['sizes']
                assert media['path'] in html
                assert 'provenance_json' not in actual and 'responsive_srcset' not in actual
            assert destination['available_locales'] == ['en', 'es', 'pt-BR']
            assert html.count('data-portal-shell="1"') == 1 and html.count('data-portal-footer="1"') == 1
            checked.append({'slug': slug, 'locale': locale})

    # Real editorial edits must survive the residual layout normalizer and old files.
    stale = package / 'app/presentation/destinations'
    stale.mkdir(parents=True, exist_ok=True)
    (stale / 'jerico.json').write_text(json.dumps({'copy': {'es': {'intro': 'SHADOW FILE MUST NEVER WIN'}}}))
    for locale in ['es', 'en', 'pt-BR']:
        marker = 'Edición desde base de datos ' + locale + ' & <segura>'
        destination = repository('jerico', locale)
        connection.execute('UPDATE destination_translations SET intro=?,headline=? WHERE destination_id=? AND locale=?', (marker, marker, destination['id'], locale))
        connection.execute('UPDATE destination_story_translations SET body=? WHERE story_block_id=(SELECT id FROM destination_story_blocks WHERE destination_id=? ORDER BY display_order,id LIMIT 1) AND locale=?', (marker, destination['id'], locale))
        connection.execute('UPDATE point_of_interest_translations SET name=?,description=? WHERE point_of_interest_id=? AND locale=?', (marker, marker, destination['places'][0]['id'], locale))
        connection.commit()
        html = render('jerico', locale)
        assert unescape(html).count(marker) >= 5, 'A rendered field ignores a database edit'
        assert '<segura>' not in html and 'SHADOW FILE MUST NEVER WIN' not in html
    edited = dump()
    assert json.loads(migrate(apply=True).stdout)['already_applied'] and dump() == edited, 'Repeat overwrote an editorial edit'
    assert protected() == immutable
    # The home selector shares database media and publication status with destination pages.
    home_code = 'require ' + json.dumps(str(package / 'app/HomeAttractions.php')) + ''';
    $i=json_decode(stream_get_contents(STDIN),true);
    $p=new PDO('sqlite:'.$i['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    echo json_encode(HomeAttractions::items($p,'es',$i['public']));'''
    payload = {'database': str(database), 'public': str(package / 'public')}
    item = json.loads(php(home_code, payload).stdout)[0]
    media_id = connection.execute('SELECT id FROM media_assets WHERE asset_path=?', (item['image'].lstrip('/'),)).fetchone()[0]
    connection.execute("UPDATE media_asset_translations SET alt_text='Imagen elegida en la base' WHERE media_asset_id=? AND locale='es'", (media_id,))
    connection.commit()
    assert json.loads(php(home_code, payload).stdout)[0]['alt_text'] == 'Imagen elegida en la base'
    home_destination = repository(item['destination_slug'], 'es')
    same_place = next(p for p in home_destination['places'] if p['slug'] == item['slug'])
    assert same_place['media'][0]['alt'] == 'Imagen elegida en la base'
    assert '/' + same_place['media'][0]['path'] == item['image']
    connection.execute("UPDATE destinations SET publication_status='draft' WHERE slug=?", (item['destination_slug'],))
    connection.commit()
    assert all(i['destination_slug'] != item['destination_slug'] for i in json.loads(php(home_code, payload).stdout))
    connection.execute("UPDATE destinations SET publication_status='published' WHERE slug=?", (item['destination_slug'],))
    connection.commit()
    assert protected() == immutable
    (stale / 'jerico.json').unlink()
    conflict.unlink()
    connection.close()
    report = {'database': 'disposable SQLite from versioned content', 'production_accessed': False,
              'dry_run': first, 'first_apply': applied, 'repeat': repeated, 'protected_data_unchanged': True,
              'conflicts_and_bad_media_rolled_back': True, 'database_edits_reach_initial_html': True,
              'home_and_destination_share_database_media': True,
              'checked_destinations': checked}
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(f'OK: {len(checked)} destination/locale combinations; migration dry-run, two applications, protected data, conflicts, media hashes and database edits in initial HTML.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.package, args.output)
