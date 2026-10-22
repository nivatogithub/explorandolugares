#!/usr/bin/env python3
"""Retire only the explicitly approved 20261021 old image paths; never touch database rows."""
from pathlib import Path
import argparse,importlib.util,json,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1]
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--apply',action='store_true');p.add_argument('--approved-preprod',required=True);args=p.parse_args()
 spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');api=importlib.util.module_from_spec(spec);spec.loader.exec_module(api)
 api.require_approved_preprod(args.approved_preprod)
 from release_guard import require_main_ancestor,require_package_parity
 require_main_ancestor(ROOT)
 config=api.load_config(ROOT/'.local/sftp.json');plan=json.loads((ROOT/'content/image-review/estilo-replacements-20261021.json').read_text());allowed={'public/'+path for row in plan for path in row['old_paths']}
 previous=api.latest_local_manifest(ROOT,config)
 with tempfile.TemporaryDirectory(prefix='el-image-retirement-') as temporary:
  temp=Path(temporary);files=api.build(temp/'build',config);removed=api.obsolete_files(previous,files);changed=api.changed_files(previous,files)
  if not removed or any(f['relative'] not in allowed for f in removed):raise ValueError('Retirement exceeds approved old image list or already complete.')
  if {f['relative'] for f in changed}!={'app/content/release-media-manifest.json'}:raise ValueError('Unexpected runtime changes in image-only cleanup.')
  oldpaths={f['relative'].removeprefix('public/') for f in removed}
  for f in files:
   if f['relative']=='app/content/release-media-manifest.json':continue
   if Path(f['relative']).suffix in {'.php','.json','.html','.js','.css'}:
    text=Path(f['local']).read_text(errors='replace')
    if any(path in text for path in oldpaths):raise ValueError('Old image remains referenced: '+f['relative'])
  report={'old_files':len(removed),'bytes_released':sum(f['bytes'] for f in removed),'paths':[f['relative'] for f in removed],'applied':False}
  client=api.SFTP(config);remote=config['remote_base']+'/'+api.MANIFEST_NAME
  client.run(['get '+api.quote(remote)+' '+api.quote(temp/'remote.json')])
  if api.parse_manifest(temp/'remote.json',config)!=previous:raise ValueError('Production changed; cleanup stopped.')
  if args.apply:
   # Preview locks the fixed matching media rows, rolls back, and proves no old path is active.
   subprocess.run([sys.executable,str(ROOT/'scripts/actualizar-estilo-imagenes.py')],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
   preview=json.loads((ROOT/'.local/estilo-migracion-preview.json').read_text())
   if preview['result']['changed']!=0 or preview['result']['matched']!=82:raise ValueError('Old database image references remain.')
   manifest=api.create_manifest(config,files,previous,api.git_commit());target=temp/'manifest.json';target.write_text(json.dumps(manifest,indent=2)+'\n')
   commands=api.upload_commands(config,changed)
   commands+=['-rm '+api.quote(api.remote_path(config,f['relative'])) for f in removed]
   commands+=['put '+api.quote(target)+' '+api.quote(remote)]
   client.run(commands)
   client.run(['get '+api.quote(remote)+' '+api.quote(temp/'confirmation.json')])
   if api.parse_manifest(temp/'confirmation.json',config)!=manifest:raise ValueError('Cleanup manifest not confirmed.')
   require_package_parity(files,manifest)
   (ROOT/'.local/deployment-manifest.json').write_bytes(target.read_bytes());report['applied']=True;report['commit']=manifest['commit']
  (ROOT/'.local/estilo-limpieza-produccion.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({k:v for k,v in report.items() if k!='paths'}))
if __name__=='__main__':main()
