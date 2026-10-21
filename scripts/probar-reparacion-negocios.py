#!/usr/bin/env python3
"""Run the isolated business repair regression. Never loads production config."""
from pathlib import Path
import argparse,io,json,secrets,subprocess,sys,tarfile,time
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--mariadb',action='store_true',help='Also use disposable databases in explorando-preprod via its root Unix socket')
args=parser.parse_args()
output=ROOT/'.local/reparacion-admin';output.mkdir(parents=True,exist_ok=True)
tests=['business-editorial','lead-admin','admin-repair','lead-replies','business-self-onboarding','business-account-portal','disputed-business-claims','account-email-verification','account-mail-local','account-mail-template','account-session-security','account-profile','business-onboarding-limits','business-subscription-choice','business-subscriptions','subscription-changes','business-crm','business-deletion','business-email-campaigns','database-maintenance','database-change-package']
results=[]
def run(name,command,**kwargs):
    started=time.monotonic()
    try:
        result=subprocess.run(command,cwd=ROOT,capture_output=True,**kwargs)
        text=result.stdout+result.stderr
        if isinstance(text,bytes):text=text.decode('utf8','replace')
        item={'test':name,'passed':result.returncode==0,'seconds':round(time.monotonic()-started,2),'output':text[-6000:]}
    except Exception as error:item={'test':name,'passed':False,'error':str(error)}
    results.append(item);print(('PASS ' if item['passed'] else 'FAIL ')+name,flush=True)
    return item['passed']
for name in tests:run(name,['php',str(ROOT/'backend/tests'/f'{name}.php')],text=True,timeout=90)
run('directory-editorial-layout',['node',str(ROOT/'backend/tests/directory-editorial-layout.cjs')],text=True,timeout=30)
run('admin-http',[sys.executable,str(ROOT/'backend/tests/admin-pages.integration.py')],text=True,timeout=300)
if args.mariadb:
    archive=io.BytesIO()
    with tarfile.open(fileobj=archive,mode='w') as package:
        for name in ['backend/app','backend/database','backend/tests/admin-repair.mariadb.php']:package.add(ROOT/name,arcname=name)
    temp='/tmp/el-admin-repair-'+secrets.token_hex(8)
    # Only this random test directory is removed; persistent volume and DB are untouched.
    command=f'mkdir {temp} && tar -xf - -C {temp}; php {temp}/backend/tests/admin-repair.mariadb.php; test_result=$?; rm -rf {temp}; exit $test_result'
    run('mariadb-concurrency-and-recovery',['podman','exec','-i','explorando-preprod','sh','-c',command],input=archive.getvalue(),timeout=300)
report={'source_commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),'working_tree_dirty':bool(subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip()),'production_access':False,'real_emails_sent':False,'persistent_preprod_data_changed':False,'results':results,'passed':all(row['passed'] for row in results)}
(output/'resultado.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print(f"{sum(row['passed'] for row in results)}/{len(results)} checks passed. Report: {output/'resultado.json'}")
sys.exit(0 if report['passed'] else 1)
