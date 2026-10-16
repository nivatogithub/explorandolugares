#!/usr/bin/env python3
"""Entrega aditiva acotada de cobertura. Sin --deploy solo prepara archivos locales."""
import argparse,hashlib,importlib.util,json,re,subprocess,sys,tempfile
from datetime import datetime,timezone
from pathlib import Path,PurePosixPath
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('el_sftp',ROOT/'scripts/desplegar-sftp.py');sftp=importlib.util.module_from_spec(spec);spec.loader.exec_module(sftp)
NAME='.explorandolugares-deploy.json'
def target(config,relative):
 parts=PurePosixPath(relative)
 if parts.is_absolute() or '..' in parts.parts:raise ValueError('Ruta no permitida')
 if parts.parts[0]=='public':return str(PurePosixPath(sftp.public_path(config),*parts.parts[1:]))
 if parts.parts[0]=='app':return str(PurePosixPath(config['remote_base'],*parts.parts))
 raise ValueError('Raíz no permitida')
def sources():
 pairs=[('backend/public/cobertura/colombia/index.html','public/cobertura/colombia/index.html'),('backend/public/api/cobertura.php','public/api/cobertura.php'),('backend/app/CoverageService.php','app/CoverageService.php'),('backend/app/content/coverage-plan.json','app/content/coverage-plan.json'),('backend/app/content/coverage-geography.json','app/content/coverage-geography.json')]
 for p in sorted((ROOT/'backend/public/assets/cobertura').iterdir()):
  if p.name in {'cobertura.css','cobertura.js','medicion.js','select-flotante.js','departamentos.geojson','procedencia.json'} or re.fullmatch(r'municipios-\d{2}\.geojson',p.name):pairs.append((str(p.relative_to(ROOT)),'public/assets/cobertura/'+p.name))
 for p in sorted((ROOT/'backend/app/analytics').rglob('*')):
  if p.is_file():pairs.append((str(p.relative_to(ROOT)),'app/analytics/'+str(p.relative_to(ROOT/'backend/app/analytics'))))
 for name in ['api/medicion.php','admin/medicion.php']:
  pairs.append(('backend/public/'+name,'public/'+name))
 for p in sorted((ROOT/'backend/integration-medicion/public').rglob('*')):
  if p.is_file():pairs.append((str(p.relative_to(ROOT)),'public/'+str(p.relative_to(ROOT/'backend/integration-medicion/public'))))
 return sorted(pairs,key=lambda p:4 if p[1]=="public/cobertura/colombia/index.html" else 3 if p[1].endswith("/index.html") else 2 if p[1]=="public/api/cobertura.php" else 1 if p[1].startswith("public/") else 0)
def manifest(data,config):
 if data.get('site')!='explorandolugares' or data.get('host')!=config['host'] or data.get('remote_base')!=config['remote_base'] or data.get('public_root')!=sftp.public_path(config):raise ValueError('Manifiesto de otro destino o inconsistente')
 if not isinstance(data.get('files'),list):raise ValueError('Manifiesto incompleto')
 for f in data['files']:
  target(config,f['relative'])
  if not re.fullmatch('[a-f0-9]{64}',f['sha256']):raise ValueError('Hash inválido')
 return data
def prepare(config):
 output=ROOT/'.local/coverage/release';files=[]
 for source,relative in sources():
  src=ROOT/source
  if src.is_symlink():raise ValueError('Enlace simbólico no permitido')
  raw=src.read_bytes()
  if source.startswith('backend/public/') and relative.endswith('.php'):raw=raw.decode().replace("dirname(__DIR__,2).'/app/","'"+config['remote_base']+"/app/").encode()
  dst=output/relative;dst.parent.mkdir(parents=True,exist_ok=True);dst.write_bytes(raw)
  files.append({'relative':relative,'local':str(dst),'bytes':len(raw),'sha256':hashlib.sha256(raw).hexdigest()})
 return files
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--config',type=Path,required=True);parser.add_argument('--manifest',type=Path,required=True);parser.add_argument('--deploy',action='store_true');args=parser.parse_args()
 config=sftp.load_config(args.config);previous=manifest(json.loads(args.manifest.read_text()),config)
 subprocess.run([sys.executable,str(ROOT/'scripts/generar-catalogo-cobertura.py')],check=True)
 subprocess.run(['php',str(ROOT/'backend/tests/coverage-service.php')],check=True)
 files=prepare(config);old={x['relative']:x for x in previous['files']}
 guards=json.loads((ROOT/'backend/integration-medicion/baseline.json').read_text())
 for relative,expected in guards.items():
  prepared=next(x['sha256'] for x in files if x['relative']==relative)
  if old.get(relative,{}).get('sha256') not in [expected,prepared]:raise ValueError('La integración necesita reconciliar cambios de producción: '+relative)
 changed=[x for x in files if old.get(x['relative'],{}).get('sha256')!=x['sha256']]
 print(f'Entrega de cobertura: {len(changed)} archivos, {sum(x["bytes"] for x in changed)//1024} KB. Sin eliminaciones. Activación aditiva separada mediante administración o permiso de despliegue de un uso.')
 report={'prepared_at':datetime.now(timezone.utc).isoformat(),'deployment_performed':False,'files':[{k:v for k,v in x.items() if k!='local'} for x in files]};reportPath=ROOT/'.local/coverage/preparacion.json';reportPath.write_text(json.dumps(report,indent=2)+'\n')
 if not args.deploy:
  print('Paquete local .local/coverage/release. No se conectó ni modificó el hosting.');return
 # Require an approved, committed source state. Never upload a mixed work tree.
 if subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip():raise ValueError('La rama contiene cambios sin commit')
 client=sftp.SFTP(config)
 with tempfile.TemporaryDirectory(prefix='el-coverage-') as temp:
  remote=Path(temp)/'remote.json';client.run([f'get {sftp.quote(config["remote_base"]+"/"+NAME)} {sftp.quote(remote)}'])
  actual=manifest(json.loads(remote.read_text()),config)
  if actual!=previous:raise ValueError('El manifiesto remoto cambió; detener y reconciliar antes de publicar')
  directories=set();commands=[]
  for f in changed:
   dest=PurePosixPath(target(config,f['relative']));stop=PurePosixPath(sftp.public_path(config)) if f['relative'].startswith('public/') else PurePosixPath(config['remote_base'])
   parent=dest.parent
   while parent!=stop:
    if stop not in parent.parents:raise ValueError('Directorio fuera de destino')
    directories.add(str(parent));parent=parent.parent
  commands.extend('-mkdir '+sftp.quote(d) for d in sorted(directories,key=lambda p:(p.count('/'),p)))
  commands.extend(f'put {sftp.quote(f["local"])} {sftp.quote(target(config,f["relative"]))}' for f in changed)
  merged={x['relative']:x for x in actual['files']}
  for f in changed:merged[f['relative']]={k:v for k,v in f.items() if k!='local'}
  deployed={**actual,'deployed_at':datetime.now(timezone.utc).isoformat(),'commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),'files':sorted(merged.values(),key=lambda f:f['relative']),'file_count':len(merged)}
  new=Path(temp)/'manifest.json';new.write_text(json.dumps(deployed,ensure_ascii=False,indent=2)+'\n');commands.append(f'put {sftp.quote(new)} {sftp.quote(config["remote_base"]+"/"+NAME)}')
  client.run(commands);args.manifest.write_text(new.read_text());report['deployment_performed']=True;reportPath.write_text(json.dumps(report,indent=2)+'\n')
  print('Transferencia terminada. Falta comprobar HTTP, API, medios y navegación para confirmar publicación funcional.')
if __name__=='__main__':main()
