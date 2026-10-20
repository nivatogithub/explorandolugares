#!/usr/bin/env python3
"""Freeze and compare the approved commercial scope. Never deploy or migrate."""
import argparse, hashlib, importlib.util, io, json, os, subprocess, tarfile, tempfile, zipfile
from release_guard import require_complete_delta, require_no_missing_files, require_main_ancestor
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify-remote',action='store_true')
    parser.add_argument('--output',type=Path,default=ROOT/'.local/production/segunda-auditoria-integrada')
    args=parser.parse_args()
    spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py')
    api=importlib.util.module_from_spec(spec);spec.loader.exec_module(api)
    config=api.load_config(ROOT/'.local/sftp.json')
    out=args.output.resolve();out.mkdir(parents=True,exist_ok=True);os.chmod(out,0o700)
    commit=api.git_commit()
    api.require_approved_preprod(commit)
    require_main_ancestor(ROOT)
    allowed=set((ROOT/'preprod/auditoria2-rutas.txt').read_text().splitlines())
    if any(Path(p).suffix.lower() in {'.png','.jpg','.jpeg','.webp','.svg','.gif','.geojson','.mmdb'} or '/cobertura/' in p or 'Coverage' in p or p.startswith('app/content/') for p in allowed):
        raise ValueError('El alcance incluye imágenes, datos de lugares o cobertura.')
    baseline=out/'remote-manifest.json'
    if args.verify_remote:
        api.SFTP(config).run(['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(baseline)])
    base=api.parse_manifest(baseline,config)
    old={f['relative']:f['sha256'] for f in base['files']}
    with tempfile.TemporaryDirectory(prefix='el-audit2-') as temp:
        temp=Path(temp);source=temp/'source';source.mkdir()
        data=subprocess.check_output(['git','archive',commit,'--','ensayos/v1','backend','content','scripts','design/portal','preprod'],cwd=ROOT)
        with tarfile.open(fileobj=io.BytesIO(data)) as archive:archive.extractall(source,filter='data')
        frozen_spec=importlib.util.spec_from_file_location('frozen',source/'scripts/desplegar-sftp.py')
        frozen=importlib.util.module_from_spec(frozen_spec);frozen_spec.loader.exec_module(frozen)
        files=frozen.build(temp/'build',config)
        changed=[f for f in files if old.get(f['relative'])!=f['sha256']]
        selected=[f for f in changed if f['relative'] in allowed]
        require_no_missing_files(files,base)
        require_complete_delta(changed,selected)
        if not selected:raise ValueError('No hay cambios de auditoría que preparar.')
        if args.verify_remote:
            checks=[(f,temp/('remote-'+str(i))) for i,f in enumerate(selected)]
            commands=[('get ' if f['relative'] in old else '-get ')+api.quote(api.remote_path(config,f['relative']))+' '+api.quote(p) for f,p in checks]
            confirm=temp/'manifest.json';commands+=['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(confirm)]
            api.SFTP(config).run(commands)
            for f,p in checks:
                if f['relative'] in old:
                    if not p.is_file() or hashlib.sha256(p.read_bytes()).hexdigest()!=old[f['relative']]:raise ValueError('Cambio remoto ajeno: '+f['relative'])
                elif p.exists():raise ValueError('Archivo no inventariado: '+f['relative'])
            if api.parse_manifest(confirm,config)!=base:raise ValueError('Otro despliegue cambió la referencia; repetir preparación.')
        zipped=out/'auditoria2.zip'
        with zipfile.ZipFile(zipped,'w',compression=zipfile.ZIP_DEFLATED) as z:
            for f in selected:z.write(f['local'],f['relative'])
        os.chmod(zipped,0o600)
        resulting=api.create_manifest(config,files,base,commit)
        (out/'manifiesto-esperado.json').write_text(json.dumps(resulting,indent=2,ensure_ascii=False)+'\n')
        report={'commit':commit,'baseline_commit':base.get('commit'),'remote_verified':args.verify_remote,'files':[{k:f[k] for k in ('relative','sha256','bytes')} for f in selected], 'excluded':[f['relative'] for f in changed if f['relative'] not in allowed], 'file_count':len(selected),'migrations':['045-business-service-sections','046-premium-page-aliases'],'zip_sha256':hashlib.sha256(zipped.read_bytes()).hexdigest(),'production_published':False,'statistics_modified':False,'coverage_files':[],'image_files':[],'runtime_scope_complete':True,'resulting_full_package_files':len(files),'removals':[]}
        (out/'paquete.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
        (out/'rutas.txt').write_text(''.join(f['relative']+'\n' for f in selected))
        print(json.dumps({k:v for k,v in report.items() if k not in ('files','excluded')},ensure_ascii=False))

if __name__=='__main__':main()
