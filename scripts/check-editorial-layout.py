#!/usr/bin/env python3
"""Compare the real shared editor on disposable CLI fixtures; never starts a server."""
from contextlib import closing
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlsplit, parse_qs
import argparse, hashlib, json, shutil, sqlite3, subprocess, tempfile
from importlib.util import spec_from_file_location,module_from_spec

ROOT = Path(__file__).resolve().parents[1]
auth_spec=spec_from_file_location('account_auth_views',ROOT/'scripts/check-account-auth-views.py');auth_module=module_from_spec(auth_spec);auth_spec.loader.exec_module(auth_module)
PLANS = ['free', 'featured', 'page', 'page_premium']
STATES = ['draft', 'in_editorial', 'awaiting_owner', 'changes_requested', 'rejected', 'owner_approved', 'published']
SEED = r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
require $i['package'].'/app/BusinessEditorialService.php';
$p=new PDO('sqlite:'.$i['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->exec('PRAGMA foreign_keys=ON');
$owner=(int)$p->query('SELECT id FROM accounts ORDER BY id LIMIT 1')->fetchColumn();
$admin=(int)$p->query('SELECT account_id FROM account_roles WHERE role="administrator" LIMIT 1')->fetchColumn();
$place=(int)$p->query('SELECT id FROM destinations ORDER BY id LIMIT 1')->fetchColumn();
$p->prepare('UPDATE accounts SET display_name=? WHERE id=?')->execute(['Cuenta <prueba> & "segura"',$admin]);
$service=new BusinessEditorialService($p,$i['package'].'/private/references');$result=[];
foreach(['free','featured','page','page_premium'] as $plan){
 $p->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,record_origin,publication_status,latitude,longitude) VALUES(?, ?, "hospedaje", ?, "VERIFICADO", "owner_submitted", "published",5.791,-75.786)')->execute(['layout-'.$plan,$place,'layout-'.$plan]);$business=(int)$p->lastInsertId();
 foreach(['es','en','pt-BR'] as $locale)$p->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)')->execute([$business,$locale,'Negocio <privado> & "seguro" '.$plan,$plan==='free'?'':'Resumen '.$locale,'']);
 $p->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")')->execute([$owner,$business]);
 $p->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status,starts_at) VALUES(?,?,"active",CURRENT_TIMESTAMP)')->execute([$business,$plan]);
 $version=$service->save($owner,$business,0,[]);
 $p->prepare('UPDATE business_editorial_versions SET note=? WHERE id=?')->execute(['Nota <privada> & "completa"',$version]);
 $p->prepare('INSERT INTO business_editorial_images(version_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,?,?,?,?,?)')->execute([$version,str_repeat('a',40).'.png','Referencia <privada>.png','image/png',1000,640,480]);
 $result[$plan]=['business'=>$business,'version'=>$version];
}
echo json_encode(['owner'=>$owner,'admin'=>$admin,'plans'=>$result],JSON_THROW_ON_ERROR);
'''
RENDER = r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_GET=$i['query'];$_POST=$i['post']??[];$_FILES=[];
$_SESSION=['csrf'=>'signed-editor-token'];
if($i['mode']==='admin'||$i['mode']==='legacy')$_SESSION+=['admin'=>true,'last_seen'=>time()];
if($i['mode']==='admin')$_SESSION['admin_account_id']=$i['admin'];
if($i['mode']==='owner'||$i['mode']==='intruder')$_SESSION+=['business_account_id'=>$i['mode']==='owner'?$i['owner']:$i['admin'],'business_last_seen'=>time()];
if($i['mode']==='expired')$_SESSION+=['business_account_id'=>$i['owner'],'business_last_seen'=>time()-3600];
if(isset($i['session_locale']))$_SESSION['account_locale']=$i['session_locale'];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/'.($i['controller']??'business/editorial.php'),'HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/'.($i['controller']??'business/editorial.php');
'''

def php(code, payload):
    result = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', code], input=json.dumps(payload), text=True, capture_output=True, check=True)
    assert not result.stderr.strip(), result.stderr[:800]
    return json.loads(result.stdout)


class EditorMarkup(HTMLParser):
    def __init__(self, public):
        super().__init__(convert_charrefs=True)
        self.public, self.events = public, []
        self.svg_depth = 0

    def handle_starttag(self, tag, attrs):
        if tag == 'svg': self.svg_depth += 1
        normalized = []
        for key, value in attrs:
            if key in ('href', 'src') and value:
                url = urlsplit(urljoin('/business/editorial.php', value))
                if not url.netloc and url.path.startswith('/assets/'):
                    asset = self.public / url.path.lstrip('/')
                    assert asset.is_file(), url.path
                    digest = hashlib.sha256(asset.read_bytes()).hexdigest()
                    version = parse_qs(url.query).get('v')
                    # Existing editorial resources also have short numeric versions.
                    legacy_version = version and (url.path, version[0]) == ('/assets/style.css', 'business-pages-v2')
                    if version and not version[0].isdigit() and not legacy_version:
                        assert digest.startswith(version[0]), (url.path, version[0], digest[:12], 'invalid asset hash')
                    value = 'resource:' + url.path + ':' + digest + ':version=' + (version[0] if version else '')
            normalized.append((key, value))
        self.events.append(('start', tag, normalized))

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if self.svg_depth: self.handle_endtag(tag)
    def handle_endtag(self, tag):
        self.events.append(('end', tag))
        if tag == 'svg': self.svg_depth -= 1
    def handle_data(self, data):
        if data.strip(): self.events.append(('text', data))
    def handle_decl(self, decl): self.events.append(('decl', decl))
    def handle_comment(self, data): self.events.append(('comment', data))


def run(before, after, database, output):
    before, after, database = before.resolve(), after.resolve(), database.resolve()
    assert all(p.is_relative_to(ROOT / '.local') for p in (before, after, database))
    checks = []
    with tempfile.TemporaryDirectory(prefix='editorial-layout-', dir=ROOT / '.local') as temporary:
        packages, seeds = [], []
        for label, source in [('before', before), ('after', after)]:
            package = Path(temporary) / label
            shutil.copytree(source / 'app', package / 'app')
            shutil.copytree(source / 'public', package / 'public', ignore=shutil.ignore_patterns('assets'))
            (package / 'public/assets').symlink_to(source / 'public/assets', target_is_directory=True)
            (package / 'private').mkdir()
            fixture = package / 'private/fixture.sqlite'
            shutil.copy2(database, fixture)
            config = {'dsn': 'sqlite:' + str(fixture), 'app_key': 'isolated-editorial-comparison', 'local_test': True,
                      'business_accounts_enabled': True, 'unified_admin_only': True, 'account_mail': {'enabled': False}}
            (package / 'private/config.php').write_text('<?php return json_decode(' + json.dumps(json.dumps(config)) + ',true);')
            seeds.append(php(SEED, {'package': str(package), 'database': str(fixture)}))
            packages.append(package)
        assert seeds[0] == seeds[1], 'Fixture identities changed'
        seed = seeds[0]

        def check(name, plan='page_premium', state='in_editorial', mode='admin', query=None, post=None, expected=200, controller=None, session_locale=None):
            base = seed['plans'][plan]
            query = dict({'business': base['business'], **({'admin': '1'} if mode in ('admin', 'legacy') else {})}, **(query or {}))
            results = []
            for package in packages:
                fixture = package / 'private/fixture.sqlite'
                with closing(sqlite3.connect(fixture)) as db:
                    db.execute('UPDATE business_editorial_versions SET status=?', (state,)); db.commit()
                    snapshot = list(db.iterdump())
                response = php(RENDER, {'package': str(package), **seed, 'mode': mode, 'query': query, 'post': post, 'controller': controller or 'business/editorial.php', 'session_locale': session_locale})
                assert response['http'] == expected, (name, response['http'], response['html'][:160])
                with closing(sqlite3.connect(fixture)) as db:
                    assert list(db.iterdump()) == snapshot, (name, 'read or rejected action wrote data')
                markup = auth_module.AuthMarkup(package/'public','/business/login.php') if controller=='business/login.php' else EditorMarkup(package/'public')
                markup.feed(response['html'])
                results.append((response['http'], markup.events))
                if expected == 303:
                    assert 'business-editorial' not in response['html'], name
                elif expected == 200 and not query.get('preview') and controller is None:
                    assert 'Negocio &lt;privado&gt;' in response['html'], (name, 'private text not escaped')
                    assert ('admin-sidebar' in response['html']) == (mode in ('admin', 'legacy')), (name, 'wrong shell')
                    if state in ('in_editorial', 'changes_requested'):
                        assert ('value="save"' in response['html']) == (mode in ('admin', 'legacy')), (name, 'editor permission changed')
            if results[0] != results[1]:
                mismatch = next((i for i, (a, b) in enumerate(zip(results[0][1], results[1][1])) if a != b), -1)
                raise AssertionError((name, 'markup changed', mismatch, results[0][1][max(0,mismatch-1):mismatch+2], results[1][1][max(0,mismatch-1):mismatch+2]))
            checks.append({'case': name, 'http': expected, 'markup_fields_resources_identical': True, 'database_unchanged': True})

        for plan in PLANS:
            for state in STATES:
                for mode in ['admin', 'owner']:
                    check(f'{plan}/{state}/{mode}', plan, state, mode)
        for mode in ['legacy', 'guest', 'expired', 'intruder']:
            check(mode, mode=mode, expected=303 if mode in ('guest', 'expired') else 503 if mode == 'intruder' else 200)
        for locale in ['es', 'en', 'pt-br', 'invalid']:
            check('owner-locale/' + locale, mode='owner', query={'lang': locale})
        for locale in ['en', 'pt-br', 'invalid']:
            check('remembered-locale/' + locale, mode='owner', session_locale=locale)
        for locale in ['es', 'en', 'pt-br']:
            for controller, mode, status, tab in [('contact.php','guest',200,''), ('business/login.php','guest',200,''),
                                                  ('business/dashboard.php','owner',200,'personal'), ('business/dashboard.php','owner',200,'business'),
                                                  ('business/dashboard.php','guest',303,'')]:
                check('legacy-account/'+controller+'/'+tab+'/'+locale, controller=controller, mode=mode, query={'lang':locale,'tab':tab}, expected=status)
        for mode in ['admin', 'owner']:
            check('missing-business/' + mode, mode=mode, query={'business': 99999999}, expected=503)
            check('missing-version/' + mode, mode=mode, query={'version': 99999999}, expected=503)
            check('foreign-version/' + mode, mode=mode, query={'version': seed['plans']['free']['version']}, expected=503)
            check('invalid-csrf/' + mode, mode=mode, post={'csrf': 'invalid', 'action': 'save'}, expected=303)
            check('stale-version/' + mode, mode=mode, post={'csrf': 'signed-editor-token', 'version_id': 99999, 'action': 'save'}, expected=400)
            for plan in PLANS:
                check('preview-card/' + mode + '/' + plan, plan, mode=mode, query={'preview': 'card'})
                if plan in ('page', 'page_premium'):
                    check('preview-page/' + mode + '/' + plan, plan, mode=mode, query={'preview': 'page'})
        # Whole-document translation used to alter data that happened to match UI copy.
        # Once the candidate editor no longer uses that translator, verify the correction.
        data_checks=[]
        if 'account_page();' not in (after/'public/business/editorial.php').read_text():
            old_translator='account_page();' in (before/'public/business/editorial.php').read_text()
            base=seed['plans']['page_premium']
            for package in packages:
                with closing(sqlite3.connect(package/'private/fixture.sqlite')) as db:
                    payload=json.loads(db.execute('SELECT payload_json FROM business_editorial_versions WHERE id=?',(base['version'],)).fetchone()[0])
                    payload['translations']['es']['display_name']='Nombre'
                    payload_hash=hashlib.sha256(json.dumps(payload,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()).hexdigest()
                    db.execute('UPDATE business_editorial_versions SET status="awaiting_owner",payload_json=?,payload_hash=?,note="Tu cuenta" WHERE id=?',(json.dumps(payload),payload_hash,base['version']));db.commit()
            for locale, translated in [('en','Name'),('pt-br','Nome')]:
                results=[php(RENDER,{'package':str(package),**seed,'mode':'owner','query':{'business':base['business'],'lang':locale},'post':None}) for package in packages]
                assert all(r['http']==200 for r in results)
                assert '<h1>'+(translated if old_translator else 'Nombre')+'</h1>' in results[0]['html'], 'Reference stored-data behavior changed'
                assert '<h1>Nombre</h1>' in results[1]['html'] and '<p class="notice">Tu cuenta</p>' in results[1]['html'], 'Stored business data translated as UI copy'
                data_checks.append({'locale':locale,'old_translation_defect_reproduced':old_translator,'candidate_stored_name_and_note_preserved':True})
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(json.dumps({'production_accessed': False, 'cli_fixture_only': True, 'checks': checks,'stored_data_checks':data_checks}, indent=2) + '\n')
    print(f'OK: {len(checks)} controller comparisons preserve editor/account behavior; {len(data_checks)} cases preserve stored business data instead of translating it as UI copy.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    for argument in ['before', 'after', 'database', 'output']: parser.add_argument('--' + argument, type=Path, required=True)
    run(**vars(parser.parse_args()))
