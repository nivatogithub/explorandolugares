#!/usr/bin/env python3
"""Compare home rendering with synthetic content and unavailable data states."""
from pathlib import Path
import json,shutil,subprocess,sys,tempfile

old,new=map(lambda p:Path(p).resolve(),sys.argv[1:3])
database=old/'private/presentation.sqlite'
assert database.is_file(),'Prepare the disposable presentation fixture first.'
with tempfile.TemporaryDirectory(prefix='home-comparison-') as temporary:
 roots=[]
 for source,name in ((old,'old'),(new,'new')):
  root=Path(temporary)/name;root.mkdir();(root/'private').mkdir()
  shutil.copytree(source/'app',root/'app');roots.append(root)
 empty=Path(temporary)/'empty.sqlite';empty.touch()
 for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-BR')):
  template=new/'public'/prefix/'index.html'
  assert template.read_bytes()==(old/'public'/prefix/'index.html').read_bytes()
  for scenario in ('missing','catalogue','invalid_dsn','empty_db','invalid_settings','mock_content','mock_failure'):
   outputs=[]
   for root in roots:
    config=root/'private/config.php';settings=root/'private/portal-settings.json'
    config.unlink(missing_ok=True);settings.unlink(missing_ok=True)
    source=old if root.name=='old' else new
    shutil.copy2(source/'app/HomeAttractions.php',root/'app/HomeAttractions.php')
    if scenario.startswith('mock_'):
     render="throw new RuntimeException('synthetic failure');" if scenario=='mock_failure' else "return '<article>Example &amp; test</article>';"
     (root/'app/HomeAttractions.php').write_text("<?php final class HomeAttractions { public static function items(PDO $db,string $locale,string $public):array { return [1]; } public static function render(array $items,string $locale):string { "+render+" } }")
    if scenario!='missing':
     dsn='invalid:missing' if scenario=='invalid_dsn' else 'sqlite:'+str(empty if scenario=='empty_db' else database)
     config.write_text('<?php return json_decode('+json.dumps(json.dumps({'dsn':dsn}))+',true);')
    if scenario=='invalid_settings':settings.write_text('{invalid')
    php='$_SERVER["REQUEST_URI"]='+json.dumps('/'+prefix)+'; require '+json.dumps(str(root/'app/PortalPageRenderer.php'))+'; PortalPageRenderer::render('+json.dumps(str(template))+','+json.dumps(locale)+',"home",'+json.dumps(str(new/'public'))+');'
    result=subprocess.run(['php','-d','display_errors=stderr','-r',php],capture_output=True)
    assert result.returncode==0,(locale,scenario,result.stderr.decode())
    outputs.append(result.stdout)
   assert outputs[0]==outputs[1],(locale,scenario)
   if scenario=='mock_content':assert b'<article>Example &amp; test</article>' in outputs[1]
print('OK: 21 home responses match, including populated, empty and failure states.')
