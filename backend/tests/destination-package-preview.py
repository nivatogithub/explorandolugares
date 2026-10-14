#!/usr/bin/env python3
"""End-to-end test of ZIP import, staged image reads, and private draft creation."""
import http.cookiejar
import json
import os
import re
import sqlite3
import urllib.parse
import urllib.request
import uuid
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get('EL_DESTINATIONS_TEST_BASE', 'http://127.0.0.1:8014/v1/').rstrip('/') + '/'
DB = Path(os.environ.get('EL_DESTINATIONS_TEST_DB', ROOT / '.local/destinos-dinamicos-preview.sqlite'))
MEDIA_ROOT = Path(os.environ.get('EL_DESTINATIONS_TEST_MEDIA_ROOT', ROOT / '.local/destinos-media'))
COOKIE_JAR = http.cookiejar.CookieJar()
CLIENT = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(COOKIE_JAR))
destination_id = 0


def multipart(fields, files):
    boundary = '----el-package-test-' + uuid.uuid4().hex
    body = bytearray()
    for name, value in fields.items():
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    for name, filename, data, mime in files:
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode())
        body.extend(data + b'\r\n')
    body.extend(f'--{boundary}--\r\n'.encode())
    return bytes(body), boundary


def post(path, fields, files=()):
    body, boundary = multipart(fields, files)
    request = urllib.request.Request(BASE + path, data=body, headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
    return CLIENT.open(request)


def image_fields(prefix, metadata):
    result = {}
    for key in ('image_origin', 'image_creator', 'image_source_url', 'image_license', 'image_license_url'):
        value = metadata.get(key, '')
        result[f'{prefix}[{key}]' if prefix else key] = value or ('ai_generated' if key == 'image_origin' else '')
    for locale in ('es', 'en', 'pt-BR'):
        for key in ('alt', 'caption'):
            name = f'{prefix}[image_{key}][{locale}]' if prefix else f'image_{key}[{locale}]'
            result[name] = (metadata.get(key) or {}).get(locale, '')
    return result


def opener_404(client, url):
    import urllib.error
    try:
        client.open(url)
        return False
    except urllib.error.HTTPError as error:
        return error.code == 404


try:
    login = CLIENT.open(BASE + 'admin/index.php').read().decode()
    csrf = re.search(r'name="csrf" value="([^"]+)"', login).group(1)
    CLIENT.open(urllib.request.Request(BASE + 'admin/index.php', data=urllib.parse.urlencode({
        'csrf': csrf, 'action': 'login', 'username': 'localadmin', 'password': 'DestinosLocal8014!'
    }).encode()))
    editor = CLIENT.open(BASE + 'admin/destinos.php').read().decode()
    csrf = re.search(r'name="csrf" value="([^"]+)"', editor).group(1)

    archive_path = ROOT / 'backend/public/plantillas/paquete-destino-v2.zip'
    with zipfile.ZipFile(archive_path) as archive:
        manifest = json.loads(archive.read('paquete-destino.json'))
        raw_images = {name: archive.read(name) for name in archive.namelist() if name.startswith('images/') and not name.endswith('/')}
    fields, files = multipart({'csrf': csrf}, [('package_zip', archive_path.name, archive_path.read_bytes(), 'application/zip')])
    request = urllib.request.Request(BASE + 'admin/paquete-destino.php', data=fields, headers={'Content-Type': f'multipart/form-data; boundary={files}'})
    staged = json.loads(CLIENT.open(request).read())
    assert staged['manifest']['schema_version'] == 2 and len(staged['images']) == 2
    staged_bytes = {}
    for image in staged['images']:
        response = CLIENT.open(BASE + 'admin/' + image['url'])
        staged_bytes[image['path']] = response.read()
        assert staged_bytes[image['path']] == raw_images[image['path']], 'Staged image bytes must match the ZIP.'

    destination = manifest['destination']
    data = {
        'csrf': csrf, 'action': 'save', 'id': '', 'slug': destination['slug'] + '-' + uuid.uuid4().hex[:6],
        'destination_type': 'municipality', 'country_code': 'CO', 'department': destination['department'],
        'subregion': destination.get('subregion', ''), 'latitude': '', 'longitude': '',
        'publication_status': 'draft', 'display_order': '999', 'hero_asset_id': '0', 'history_asset_id': '0',
        'internal_research_json': json.dumps(manifest.get('local_research', {'schema_version': 1}), ensure_ascii=False),
        'package_import_token': staged['token'],
    }
    for locale in ('es', 'en', 'pt-BR'):
        translation = destination['translations'][locale]
        for key in ('display_name', 'eyebrow', 'headline', 'intro', 'meta_description'):
            data[f'translations[{locale}][{key}]'] = translation.get(key, '')
        data[f'history[{locale}]'] = '\n'.join(destination['history'][locale])
        data[f'reasons[{locale}]'] = '\n'.join(destination['reasons'][locale])
        data[f'sources[{locale}]'] = ''
    data.update(image_fields('', manifest['hero_image']))
    place_files = []
    for index, place in enumerate(manifest['places']):
        data[f'places[{index}][slug]'] = place['slug']
        data[f'places[{index}][publication_status]'] = 'draft'
        data[f'places[{index}][media_asset_id]'] = '0'
        for locale in ('es', 'en', 'pt-BR'):
            for key in ('name', 'description'):
                data[f'places[{index}][translations][{locale}][{key}]'] = place['translations'][locale][key]
        data.update(image_fields(f'places[{index}]', place['image']))
        image_name = place['image']['file']
        place_files.append((f'place_uploads[{index}]', Path(image_name).name, staged_bytes[image_name], 'image/png'))
    hero_name = manifest['hero_image']['file']
    uploads = [('hero_upload', Path(hero_name).name, staged_bytes[hero_name], 'image/png'), *place_files]
    response = post('admin/destinos.php', data, uploads)
    matched = re.search(r'[?&]id=(\d+)', response.geturl())
    assert matched, f'Draft save did not return to its editor: {response.geturl()}'
    destination_id = int(matched.group(1))

    with sqlite3.connect(DB) as db:
        status = db.execute('SELECT publication_status FROM destinations WHERE id=?', (destination_id,)).fetchone()
        assert status == ('draft',), 'Imported destination must remain a draft.'
        assets = db.execute('SELECT asset_path FROM media_assets WHERE asset_path LIKE ?', (f'assets/destinos/{destination_id}/%',)).fetchall()
        assert len(assets) == len(uploads), 'All ZIP images must be saved and registered.'
        assert db.execute('SELECT COUNT(*) FROM destination_media WHERE destination_id=? AND role="hero"', (destination_id,)).fetchone()[0] == 1
    for (asset_path,) in assets:
        assert (MEDIA_ROOT / asset_path).is_file(), 'Uploaded destination images must exist in media storage.'
    assert opener_404(CLIENT, BASE + 'admin/paquete-destino-imagen.php?token=' + staged['token'] + '&file=' + urllib.parse.quote(hero_name)), 'Staged copies are removed after save.'
    print('OK: ZIP import authenticates, stages the images for review, and saves all content as a private draft.')
finally:
    if destination_id:
        with sqlite3.connect(DB) as db:
            media_ids = [row[0] for row in db.execute('SELECT id FROM media_assets WHERE asset_path LIKE ?', (f'assets/destinos/{destination_id}/%',))]
            if media_ids:
                marks = ','.join('?' for _ in media_ids)
                db.execute(f'DELETE FROM media_asset_translations WHERE media_asset_id IN ({marks})', media_ids)
                db.execute(f'DELETE FROM point_of_interest_media WHERE media_asset_id IN ({marks})', media_ids)
                db.execute(f'DELETE FROM destination_media WHERE media_asset_id IN ({marks})', media_ids)
                db.execute(f'DELETE FROM media_assets WHERE id IN ({marks})', media_ids)
            db.execute('DELETE FROM point_of_interest_translations WHERE point_of_interest_id IN (SELECT id FROM points_of_interest WHERE destination_id=?)', (destination_id,))
            db.execute('DELETE FROM points_of_interest WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_translations WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_story_translations WHERE story_block_id IN (SELECT id FROM destination_story_blocks WHERE destination_id=?)', (destination_id,))
            db.execute('DELETE FROM destination_story_blocks WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_reason_translations WHERE reason_id IN (SELECT id FROM destination_reasons WHERE destination_id=?)', (destination_id,))
            db.execute('DELETE FROM destination_reasons WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_sources WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destinations WHERE id=?', (destination_id,))
        for path in MEDIA_ROOT.glob(f'assets/destinos/{destination_id}/*') if MEDIA_ROOT.exists() else []:
            path.unlink(missing_ok=True)
        folder = MEDIA_ROOT / 'assets' / 'destinos' / str(destination_id)
        if folder.is_dir() and not any(folder.iterdir()): folder.rmdir()
