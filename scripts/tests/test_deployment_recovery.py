import importlib.util, sys, json, shlex, hashlib, tempfile, unittest
from pathlib import Path
SCRIPTS=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(SCRIPTS))
from deployment_recovery import protected_deploy, recover
spec=importlib.util.spec_from_file_location('recovery_api',SCRIPTS/'desplegar-sftp.py');api=importlib.util.module_from_spec(spec);spec.loader.exec_module(api)
h=lambda b:hashlib.sha256(b).hexdigest()
class Fake:
 def __init__(self,files,fail_at=None):self.files=files;self.fail_at=fail_at;self.renames=0
 def run(self,commands):
  for c in commands:
   p=shlex.split(c);cmd=p[0].lstrip('-')
   if cmd=='get':
    if p[1] in self.files:Path(p[2]).write_bytes(self.files[p[1]])
    elif not p[0].startswith('-'):raise RuntimeError('missing')
   elif cmd=='put':self.files[p[2]]=Path(p[1]).read_bytes()
   elif cmd=='rename':
    self.renames+=1
    if self.renames==self.fail_at:raise RuntimeError('connection lost')
    self.files[p[2]]=self.files.pop(p[1])
   elif cmd=='rm':self.files.pop(p[1],None)
class RecoveryTests(unittest.TestCase):
 def scenario(self,root,fail_at=None):
  config={'site':'explorandolugares','host':'example.invalid','remote_base':'/home/user/site','public_directory':'public'}
  old={'site':'explorandolugares','files':[{'relative':'public/index.html','sha256':h(b'old'),'bytes':3}]}
  files={api.remote_path(config,'public/index.html'):b'old',config['remote_base']+'/'+api.MANIFEST_NAME:json.dumps(old).encode()}
  old_snapshot=dict(files);client=Fake(files,fail_at)
  selected=[]
  for i,(rel,raw) in enumerate([('public/index.html',b'new'),('app/New.php',b'new dependency')]):
   path=root/str(i);path.write_bytes(raw);selected.append({'relative':rel,'local':str(path),'bytes':len(raw),'sha256':h(raw)})
  new=root/'new.json';new.write_text(json.dumps({'files':[]}));local=root/'live.json';local.write_text(json.dumps(old))
  return config,old,client,selected,new,local,old_snapshot
 def test_every_interruption_recovers_only_delta(self):
  for failure in (1,2,3):
   with self.subTest(failure=failure),tempfile.TemporaryDirectory() as t:
    root=Path(t);c,old,client,selected,new,local,before=self.scenario(root,failure)
    with self.assertRaises(RuntimeError):protected_deploy(api,client,c,selected,old,new,root/'recovery',local)
    journal=next((root/'recovery').glob('*/journal.json'));client.fail_at=None
    recover(api,client,c,journal,local)
    for target,raw in before.items():self.assertEqual(client.files[target],raw)
    self.assertNotIn(api.remote_path(c,'app/New.php'),client.files)
    self.assertEqual(json.loads(journal.read_text())['state'],'recovered')
 def test_completed_release_can_be_recovered(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);c,old,client,selected,new,local,before=self.scenario(root)
   journal=protected_deploy(api,client,c,selected,old,new,root/'recovery',local)
   recover(api,client,c,journal,local)
   self.assertEqual(client.files[api.remote_path(c,'public/index.html')],b'old')
 def test_remote_drift_blocks_before_writes(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);c,old,client,selected,new,local,before=self.scenario(root)
   client.files[api.remote_path(c,'public/index.html')]=b'changed elsewhere'
   with self.assertRaises(ValueError):protected_deploy(api,client,c,selected,old,new,root/'recovery',local)
   self.assertEqual(client.renames,0)
 def test_recovery_refuses_foreign_changes(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);c,old,client,selected,new,local,before=self.scenario(root,3)
   with self.assertRaises(RuntimeError):protected_deploy(api,client,c,selected,old,new,root/'recovery',local)
   journal=next((root/'recovery').glob('*/journal.json'));client.fail_at=None
   client.files[api.remote_path(c,'public/index.html')]=b'unrelated update'
   with self.assertRaises(ValueError):recover(api,client,c,journal,local)
   self.assertEqual(client.files[api.remote_path(c,'public/index.html')],b'unrelated update')
 def test_success_only_backs_up_modified_file(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);c,old,client,selected,new,local,before=self.scenario(root)
   journal=protected_deploy(api,client,c,selected,old,new,root/'recovery',local)
   self.assertEqual(len(list(journal.parent.glob('before-*'))),1)
   self.assertEqual(client.files[api.remote_path(c,'public/index.html')],b'new')
   self.assertEqual(local.read_bytes(),new.read_bytes())
if __name__=='__main__':unittest.main()
