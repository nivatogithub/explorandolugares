#!/usr/bin/env python3
"""Compare shared admin rendering with isolated data, not an extra web server."""
from pathlib import Path
from contextlib import closing
import argparse,json,shutil,sqlite3,subprocess,tempfile
from importlib.util import spec_from_file_location,module_from_spec

ROOT=Path(__file__).resolve().parents[1]
spec=spec_from_file_location('place_view_check',ROOT/'scripts/check-place-admin-view.py');view_module=module_from_spec(spec);spec.loader.exec_module(view_module)
Markup=view_module.Markup
ROUTES=[
 ('index.php',{}),('index.php',{'tab':'sistema'}),('index.php',{'tab':'unknown'}),
 ('email-templates.php',{}),('request-response.php',{}),
 ('request-details.php',{'type':'account'}),('request-details.php',{'tipo':'account'}),('request-details.php',{'type':'lead','tipo':'account'}),
 ('business-support.php',{}),('business-editorial.php',{}),('editorial.php',{}),('editorial-package.php',{}),
 ('businesses.php',{}),('transfer-businesses.php',{}),('import-businesses.php',{}),('business-contacts.php',{}),('update-businesses.php',{}),
 ('campaigns.php',{'tab':'conexion'}),('campaigns.php',{}),('claims.php',{}),('account-applications.php',{}),('accounts.php',{}),
 ('subscriptions.php',{}),('coupons.php',{}),('places.php',{}),('paquete-destino-v2.php',{}),('reports.php',{}),('public-requests.php',{}),
 ('payment-settings.php',{}),('settings.php',{}),('database.php',{}),('investors.php',{}),('costs.php',{}),('cancellations.php',{}),('incidents.php',{}),('unknown.php',{}),
]
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_GET=$i['query'];$_POST=[];$_FILES=[];
$_SESSION=['admin'=>true,'last_seen'=>time(),'csrf'=>'signed-layout-token'];
if($i['account'])$_SESSION['admin_account_id']=$i['account'];
$_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$i['uri'],'HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1'];
require $i['package'].'/app/bootstrap.php';
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
if($i['shared']){
 if(is_file($i['package'].'/public/admin/_page.php')){
  require $i['package'].'/public/admin/_page.php';admin_page();
  echo '<!doctype html><html lang="es"><head></head><body><main class="fixture">Contenido privado</main></body></html>';
 }else{
  require $i['package'].'/public/_portal-shell.php';
  $adminLayout=admin_layout_context();
  echo '<!doctype html><html lang="es"><head>'.PortalPresentation::assets(false,'admin').'</head><body class="portal-admin"><main class="fixture admin-page">';
  require $i['package'].'/public/admin/_navigation.php';
  echo 'Contenido privado</main></body></html>';
 }
}else{
 if(function_exists('admin_layout_context'))$adminLayout=admin_layout_context();
 require $i['package'].'/public/admin/_navigation.php';
}
'''

def run(before,after,database,output):
 before,after,database=before.resolve(),after.resolve(),database.resolve()
 assert all(p.is_relative_to(ROOT/'.local') for p in (before,after,database))
 checks=[]
 with tempfile.TemporaryDirectory(prefix='admin-layout-',dir=ROOT/'.local') as temporary:
  packages=[]
  for label,source in [('before',before),('after',after)]:
   package=Path(temporary)/label;package.mkdir();shutil.copytree(source/'app',package/'app')
   (package/'public').mkdir();shutil.copytree(source/'public/admin',package/'public/admin')
   for name in ['_portal-shell.php','asset-manifest.json']:shutil.copy2(source/'public'/name,package/'public'/name)
   (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
   (package/'private').mkdir();fixture=package/'private/fixture.sqlite';shutil.copy2(database,fixture)
   with closing(sqlite3.connect(fixture)) as db:
    account_id=db.execute('SELECT id FROM accounts ORDER BY id LIMIT 1').fetchone()[0]
    db.execute('UPDATE accounts SET display_name=? WHERE id=?',('Cuenta <prueba> & "segura"',account_id));db.commit()
   config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-admin-layout-comparison-key','local_test':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
   (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
   packages.append(package)
  scenarios=[(name,query,False,'/admin/'+name) for name,query in ROUTES]
  scenarios += [('shared-editor',{},True,'/business/editorial.php'),('shared-admin-editor',{},True,'/admin/business-editorial.php')]
  for name,query,shared,uri in scenarios:
   for mode,identity in [('legacy',0),('account',account_id),('missing_account',999999)]:
    results=[]
    for package in packages:
     payload={'package':str(package),'uri':uri,'query':query,'account':identity,'shared':shared}
     process=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),capture_output=True,text=True,check=True)
     assert not process.stderr.strip(),(name,mode,process.stderr[:400])
     response=json.loads(process.stdout);markup=Markup(package/'public');markup.feed(response['html'])
     if identity==account_id:assert 'Cuenta &lt;prueba&gt; &amp; &quot;segura&quot;' in response['html'],(name,'account name escaping')
     assert 'signed-layout-token' in response['html'],(name,'CSRF missing')
     assert ('action="/negocios/salir/"' if identity else 'action="/admin/index.php"') in response['html'],(name,'logout changed')
     results.append((response['http'],markup.events))
    assert results[0]==results[1],(name,mode,'private layout changed')
    checks.append({'route_case':name,'mode':mode,'shared_handler':shared,'markup_resources_account_logout_identical':True})
 output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'checks':checks},indent=2)+'\n')
 print(f'OK: {len(checks)} shared-layout comparisons preserve active menu, account escaping, legacy/account logout, CSRF and shared editor rendering.')

if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--before',type=Path,required=True);p.add_argument('--after',type=Path,required=True);p.add_argument('--database',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.before,a.after,a.database,a.output)
