#!/usr/bin/env python3
"""Compare old and new privacy rendering with isolated, synthetic settings."""
from pathlib import Path
import json
import shutil
import subprocess
import sys
import tempfile

old,new=map(lambda p:Path(p).resolve(),sys.argv[1:3])
scenarios={
 'missing':None,
 'configured':{'operator':'Operador <test> & hijos','contact':'privacy@example.invalid',
               'retention':'Hasta 2 años','retention_en':'For 2 years','retention_pt':'Por 2 anos'},
 'partial':{'operator':'','contact':'','retention':'Español'},
 'overridden':{'operator':'Previous','contact':'before@example.invalid','retention':'Old'},
 'invalid_settings':{'operator':'Private','contact':'private@example.invalid','retention':'Example'},
}
override={'operator':'Prueba & Cía','contact':'test@example.invalid','retention':'Dos años',
          'retention_en':'Two years','retention_pt':'Dois anos'}

with tempfile.TemporaryDirectory(prefix='privacy-comparison-') as temporary:
 roots=[]
 for source,name in ((old,'old'),(new,'new')):
  root=Path(temporary)/name;root.mkdir();(root/'private').mkdir()
  shutil.copytree(source/'app',root/'app')
  roots.append(root)
 for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-BR')):
  old_template=old/'public'/prefix/'privacidad/index.html'
  new_template=new/'public'/prefix/'privacidad/index.html'
  assert old_template.read_bytes()==new_template.read_bytes(),locale
  for scenario,data in scenarios.items():
   outputs=[]
   for root in roots:
    config=root/'private/config.php';settings=root/'private/portal-settings.json'
    config.unlink(missing_ok=True);settings.unlink(missing_ok=True)
    if data is not None:
     encoded=json.dumps({'privacy':data},ensure_ascii=True)
     config.write_text('<?php return json_decode('+json.dumps(encoded)+',true);')
    if scenario=='overridden':settings.write_text(json.dumps({'privacy':override},ensure_ascii=False))
    if scenario=='invalid_settings':settings.write_text('{invalid')
    php='$_SERVER["REQUEST_URI"]='+json.dumps('/'+prefix+'privacidad/')+'; require '+json.dumps(str(root/'app/PortalPageRenderer.php'))+'; PortalPageRenderer::render('+json.dumps(str(new_template))+','+json.dumps(locale)+',"privacy",'+json.dumps(str(new/'public'))+');'
    result=subprocess.run(['php','-d','display_errors=stderr','-r',php],capture_output=True)
    assert result.returncode==0,(locale,scenario,result.stderr.decode())
    outputs.append(result.stdout)
   assert outputs[0]==outputs[1],(locale,scenario)
   if scenario=='configured':
    assert b'&lt;test&gt; &amp; hijos' in outputs[1] and b'<test>' not in outputs[1]
 print('OK: 15 privacy responses match exactly, including missing/invalid settings, overrides and escaped values.')
