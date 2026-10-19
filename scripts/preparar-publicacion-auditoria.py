#!/usr/bin/env python3
"""Freeze the implemented first-audit delta from Git. Never publish or change a database."""
import argparse,hashlib,html,importlib.util,io,json,os,subprocess,tarfile,tempfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
# Exact ownership boundary: no renderer, agenda, visual identity, country metrics or editorial data.
APP_NAMES={'AccountAdminService.php','AccountClosureService.php','AccountLifecycle.php','AccountMailTemplate.php','AccountReactivationDelivery.php','AccountReactivationService.php','AccountRecoveryDelivery.php','AccountSessionSecurity.php','BusinessPaymentEvidenceService.php','BusinessSubscriptionAdminService.php','DatabaseMaintenance.php','LeadGeography.php','SubscriptionChangeService.php','bootstrap.php','contact-options.json'}
PUBLIC_NAMES={'admin/medicion.php','admin/usuarios.php','admin/suscripciones.php','assets/account-registration.css','assets/admin-subscriptions.js','assets/business-subscriptions.js','assets/cobertura/cobertura.js','assets/contact-options.json','assets/opciones-contacto.js','assets/telefono.js','business/login.php','business/logout.php','business/password.php','business/recover.php','business/register.php','business/registration-choice.php','business/traveler.php','business/dashboard.php','business/publication-terms.php'}
ALLOWED={'app/'+p for p in APP_NAMES}|{'public/'+p for p in PUBLIC_NAMES}|{'app/database/migrations/'+n+'.'+engine+'.sql' for n in ('043-account-lifecycle','044-subscription-changes') for engine in ('mysql','sqlite')}
SOURCES=['ensayos/v1','backend','content','scripts','design/portal','preprod']
def module(root):
 spec=importlib.util.spec_from_file_location('audit_deploy',root/'scripts/desplegar-sftp.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

def select(files,baseline):
 old={f['relative']:f['sha256'] for f in baseline['files']}
 changed=[f for f in files if old.get(f['relative'])!=f['sha256']]
 return [f for f in changed if f['relative'] in ALLOWED],[f for f in changed if f['relative'] not in ALLOWED]

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--commit',default='HEAD');p.add_argument('--manifest',type=Path,default=ROOT/'.local/deployment-manifest.json');p.add_argument('--verify-remote',action='store_true',help='Solo descarga manifiesto y archivos seleccionados para contrastar hashes.');p.add_argument('--output',type=Path,default=ROOT/'.local/production/primera-auditoria');a=p.parse_args()
 commit=subprocess.check_output(['git','rev-parse',a.commit+'^{commit}'],cwd=ROOT,text=True).strip();api=module(ROOT);config=api.load_config(a.config)
 out=a.output.resolve();out.mkdir(parents=True,exist_ok=True,mode=0o700);os.chmod(out,0o700)
 base=api.parse_manifest(a.manifest,config)
 if not base:raise ValueError('Falta un manifiesto confirmado de referencia.')
 if a.verify_remote:
  remote=out/'remote-manifest.json';api.SFTP(config).run(['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(remote)]);base=api.parse_manifest(remote,config)
 with tempfile.TemporaryDirectory(prefix='el-first-audit-') as folder:
  temp=Path(folder);snapshot=temp/'source';snapshot.mkdir()
  data=subprocess.check_output(['git','archive',commit,'--',*SOURCES],cwd=ROOT)
  with tarfile.open(fileobj=io.BytesIO(data)) as archive:archive.extractall(snapshot,filter='data')
  frozen=module(snapshot)
  with (out/'construccion.log').open('w') as log:
   import contextlib
   # build() emits subprocess output; redirect the process descriptors as well.
   oldout=os.dup(1);olderr=os.dup(2)
   try:os.dup2(log.fileno(),1);os.dup2(log.fileno(),2);files=frozen.build(temp/'build',config)
   finally:os.dup2(oldout,1);os.dup2(olderr,2);os.close(oldout);os.close(olderr)
  selected,excluded=select(files,base)
  if not selected:raise ValueError('No hay un delta de auditoría para preparar.')
  verified=False
  if a.verify_remote:
   known={f['relative']:f for f in base['files']};checks=[]
   for index,item in enumerate(selected):
    if item['relative'] in known:checks.append((item,temp/('remote-'+str(index))))
   unknown=[(f,temp/('unexpected-'+str(i))) for i,f in enumerate(selected) if f['relative'] not in known]
   commands=['get '+api.quote(api.remote_path(config,f['relative']))+' '+api.quote(path) for f,path in checks]
   commands+=['-get '+api.quote(api.remote_path(config,f['relative']))+' '+api.quote(path) for f,path in unknown]
   confirm=temp/'manifest-confirm.json';commands+=['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(confirm)]
   api.SFTP(config).run(commands)
   for f,path in checks:
    if hashlib.sha256(path.read_bytes()).hexdigest()!=known[f['relative']]['sha256']:raise ValueError('Archivo remoto distinto del manifiesto: '+f['relative'])
   for f,path in unknown:
    if path.exists():raise ValueError('Archivo remoto nuevo no inventariado: '+f['relative'])
   if api.parse_manifest(confirm,config)!=base:raise ValueError('Otro worker publicó durante la comprobación. Recalcular el paquete.')
   verified=True
  rows=[{k:f[k] for k in ('relative','bytes','sha256')} for f in selected]
  report={'schema_version':1,'scope':'first-audit-implemented-fixes','commit':commit,'baseline_commit':base.get('commit'),'baseline_deployed_at':base.get('deployed_at'),'baseline_sha256':hashlib.sha256(json.dumps(base,sort_keys=True).encode()).hexdigest(),'remote_verified':verified,'files':rows,'file_count':len(rows),'bytes':sum(f['bytes'] for f in rows),'excluded':sorted(f['relative'] for f in excluded),'removals':[],'place_or_image_files':[],'database_data_included':False,'statistics_modified':False,'production_published':False,'migrations':['043-account-lifecycle','044-subscription-changes'],'backup_and_isolated_restore':'required immediately before authorized migration','functional_production_validation':'pending after deployment'}
  zipped=out/'ajustes-primera-auditoria.zip'
  with zipfile.ZipFile(zipped,'w',compression=zipfile.ZIP_DEFLATED) as z:
   for f in selected:
    info=zipfile.ZipInfo(f['relative'],date_time=(1980,1,1,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,Path(f['local']).read_bytes())
  os.chmod(zipped,0o600);report['zip_sha256']=hashlib.sha256(zipped.read_bytes()).hexdigest();report['zip_bytes']=zipped.stat().st_size
  (out/'paquete.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
  (out/'rutas.txt').write_text(''.join(f['relative']+'\n' for f in rows))
  escaped=lambda text:html.escape(str(text))
  table=''.join('<tr><td>'+escaped(f['relative'])+'</td><td>'+str(f['bytes'])+'</td></tr>' for f in rows)
  document='<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preparación de publicación · primera auditoría</title><style>body{font:16px system-ui;max-width:1040px;margin:40px auto;padding:0 20px;color:#183a35}table{border-collapse:collapse;width:100%}td,th{padding:9px;text-align:left;border-bottom:1px solid #ddd}code{overflow-wrap:anywhere}section{padding:18px;background:#f6f5f1;margin:20px 0}li{margin:8px 0}</style><h1>Ajustes de la primera auditoría</h1><p>Paquete preparado; no publicado.</p><p>Versión: <code>'+commit+'</code>. Referencia productiva: <code>'+escaped(base.get('commit'))+'</code>.</p><section><strong>'+str(len(rows))+' archivos · '+str(report['bytes'])+' bytes sin comprimir</strong><p>Incluye seguridad y ciclo de cuentas, formulario nacional, cambios de suscripción y enlace de visitantes.</p><p>No incluye datos, lugares, imágenes, agenda, estilos del encabezado ni idiomas. No elimina archivos.</p></section><h2>Antes de publicar</h2><ol><li>Aprobar esta versión y comprobar que el manifiesto remoto no cambió.</li><li>Generar respaldo reciente y comprobar su restauración en una base aislada.</li><li>Aplicar solo 043 y 044 que falten; comprobar estados completos.</li><li>Publicar este delta con recuperación temporal y sin restaurar estadísticas.</li><li>Validar producción, limpiar la contingencia y alinear main.</li></ol><p>La preparación no declara cerrados los demás puntos pendientes de la auditoría.</p><h2>Archivos seleccionados</h2><table><tr><th>Ruta</th><th>Bytes</th></tr>'+table+'</table><h2>Cambios excluidos</h2><ul>'+''.join('<li><code>'+escaped(r)+'</code></li>' for r in report['excluded'])+'</ul></html>'
  (out/'preparacion.html').write_text(document)
  print(json.dumps({k:report[k] for k in ('commit','baseline_commit','remote_verified','file_count','bytes','zip_bytes','production_published')},ensure_ascii=False))
 return 0
if __name__=='__main__':raise SystemExit(main())
