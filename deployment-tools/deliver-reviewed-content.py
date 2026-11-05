"""Incremental delivery of additive place content, reusing proven unchanged application checks."""
from pathlib import Path
import argparse,hashlib,importlib.util,json,subprocess,sys,tempfile
from reuse_validation import require_reusable_application_validation

root=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(root/'scripts'))
from release_guard import require_complete_delta,require_main_ancestor,require_no_missing_files
p=argparse.ArgumentParser();p.add_argument('--slug',required=True);p.add_argument('--source-folder',required=True);p.add_argument('--output',type=Path,required=True);p.add_argument('--baseline-report',type=Path,required=True);p.add_argument('--payload',required=True);args=p.parse_args();out=args.output.resolve();out.mkdir(parents=True,exist_ok=True)
spec=importlib.util.spec_from_file_location('deploy',root/'scripts/desplegar-sftp.py');api=importlib.util.module_from_spec(spec);spec.loader.exec_module(api)
commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip();api.require_approved_preprod(commit);require_main_ancestor(root);config=api.load_config(root/'.local/sftp.json');previous=api.latest_local_manifest(root,config)
pack=json.loads((root/args.payload).read_text());report=json.loads(args.baseline_report.read_text())
certificate=require_reusable_application_validation(root,previous,report,args.slug,args.source_folder,pack)
for name in ['preprod-seo.json','preprod-visual-check.json','mariadb-rehearsal.json']:
 data=json.loads((out/name).read_text())
 if name=='mariadb-rehearsal.json':assert len(data['two_mariadb_rehearsals'])==2 and data['old_records_preserved'] and data['statistics_preserved']
 else:assert data['passed']
runtime_payload=root/'.local/preprod/release'/args.payload.removeprefix('backend/')
assert runtime_payload.is_file() and hashlib.sha256(runtime_payload.read_bytes()).hexdigest()==certificate['payload_sha256']
client=api.SFTP(config)
with tempfile.TemporaryDirectory(prefix='el-reviewed-content-') as temp:
 temp=Path(temp);files=api.build(temp/'package',config);require_no_missing_files(files,previous);selected=api.changed_files(previous,files);require_complete_delta(selected,selected)
 probe=temp/'remote.json';client.run(['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(probe)]);assert api.parse_manifest(probe,config)==previous
 manifest=api.create_manifest(config,files,previous,api.git_commit());path=temp/'manifest.json';path.write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
 from deployment_recovery import protected_deploy
 journal=protected_deploy(api,client,config,selected,previous,path,root/'.local/deployment-recovery',root/'.local/deployment-manifest.json')
 receipt={'commit':commit,'application_validation':certificate,'changed_files':[{'relative':f['relative'],'sha256':f['sha256']} for f in selected],'uploaded_files':len(selected),'journal':str(journal),'preprod_data_and_render_checks_passed':True,'remote_manifest_concurrency_checked':True}
 (out/'delivery.json').write_text(json.dumps(receipt,indent=2)+'\n');print(json.dumps({'files_uploaded':len(selected),'shared_application_checks_reused':True}))
