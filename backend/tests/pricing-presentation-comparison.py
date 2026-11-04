#!/usr/bin/env python3
"""Compare commercial pages with synthetic catalogue and failure states."""
from pathlib import Path
import json,shutil,subprocess,sys,tempfile

old,new=map(lambda p:Path(p).resolve(),sys.argv[1:3])
database=old/'private/presentation.sqlite'
assert database.is_file(),'Prepare the disposable presentation fixture first.'
with tempfile.TemporaryDirectory(prefix='pricing-comparison-') as temporary:
 roots=[]
 for source,name in ((old,'old'),(new,'new')):
  root=Path(temporary)/name;root.mkdir();(root/'private').mkdir()
  shutil.copytree(source/'app',root/'app');roots.append(root)
 empty=Path(temporary)/'empty.sqlite';empty.touch()
 for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-BR')):
  for folder,kind in (('suscripciones','subscriptions'),('proceso','business-process')):
   template=new/'public'/prefix/'negocios-locales'/folder/'index.html'
   assert template.read_bytes()==(old/'public'/prefix/'negocios-locales'/folder/'index.html').read_bytes()
   for scenario in ('missing','catalogue','invalid_dsn','empty_db','invalid_settings','operator_failure'):
    outputs=[]
    for root in roots:
     config=root/'private/config.php';settings=root/'private/portal-settings.json'
     config.unlink(missing_ok=True);settings.unlink(missing_ok=True)
     source=old if root.name=='old' else new
     shutil.copy2(source/'app/SubscriptionPricing.php',root/'app/SubscriptionPricing.php')
     if scenario=='operator_failure':
      (root/'app/SubscriptionPricing.php').write_text("<?php final class SubscriptionPricing { public function __construct(PDO $db) {} public function publicTable(string $locale): string { return '<strong>Test price</strong>'; } public function operatorText(string $locale): string { throw new RuntimeException('synthetic failure'); } }")
     if scenario!='missing':
      dsn='invalid:missing' if scenario=='invalid_dsn' else 'sqlite:'+str(empty if scenario=='empty_db' else database)
      encoded=json.dumps({'dsn':dsn})
      config.write_text('<?php return json_decode('+json.dumps(encoded)+',true);')
     if scenario=='invalid_settings':settings.write_text('{invalid')
     php='$_SERVER["REQUEST_URI"]='+json.dumps('/'+prefix+'negocios-locales/'+folder+'/')+'; require '+json.dumps(str(root/'app/PortalPageRenderer.php'))+'; PortalPageRenderer::render('+json.dumps(str(template))+','+json.dumps(locale)+','+json.dumps(kind)+','+json.dumps(str(new/'public'))+');'
     result=subprocess.run(['php','-d','display_errors=stderr','-r',php],capture_output=True)
     assert result.returncode==0,(locale,kind,scenario,result.stderr.decode())
     outputs.append(result.stdout)
    assert outputs[0]==outputs[1],(locale,kind,scenario)
    if scenario=='catalogue':
     assert b'data-operator-pricing>' in outputs[1] and b'COP' in outputs[1]
print('OK: 36 commercial responses match, including fixture prices, partial errors and unavailable configuration/database.')
