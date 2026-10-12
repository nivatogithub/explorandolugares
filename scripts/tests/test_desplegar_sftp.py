import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import hashlib

MODULE=Path(__file__).resolve().parents[1]/'desplegar-sftp.py'
spec=importlib.util.spec_from_file_location('deploy',MODULE);deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)

class Remote:
 def __init__(self,fail_at=None):self.commands=[];self.fail_at=fail_at
 def run(self,commands):
  self.commands.extend(commands)
  if self.fail_at and len(self.commands)>=self.fail_at:raise RuntimeError('Interrupción simulada')

class DeploymentTests(unittest.TestCase):
 def setUp(self):
  self.config={'site':'explorandolugares','host':'sftp.example.invalid','port':22,'user':'usuario','remote_base':'/home/usuario/explorandolugares','public_directory':'public','known_hosts_file':'~/.ssh/known_hosts','identity_file':''}
 def load(self,config):
  with tempfile.TemporaryDirectory() as folder:
   p=Path(folder)/'config.json';p.write_text(json.dumps(config));return deploy.load_config(p)
 def test_valid_config(self):self.assertEqual(self.load(self.config)['site'],'explorandolugares')
 def test_reject_unsafe_targets_and_credentials(self):
  for changes in [{'remote_base':'/'},{'remote_base':'/home/user/public_html/otro'},{'remote_base':'/home/user/../otro'},{'public_directory':'private'},{'host':'host;rm'},{'password':'no guardar'},{'site':'otro'},{'port':0}]:
   with self.subTest(changes=changes),self.assertRaises(ValueError):self.load({**self.config,**changes})
 def test_literal_sftp_paths(self):
  self.assertEqual(deploy.quote('/tmp/mi carpeta'), '"/tmp/mi carpeta"')
  for value in ['a\nrm /','a*','a?','a[1]']:
   with self.assertRaises(ValueError):deploy.quote(value)
 def test_staging_never_puts_in_live_directory(self):
  files=[{'relative':'public/index.html','local':'/tmp/index.html'},{'relative':'app/bootstrap.php','local':'/tmp/bootstrap.php'}]
  commands=deploy.stage_commands(self.config,'test-release',files)
  uploads=[s for s in commands if s.startswith('put ')]
  self.assertEqual(len(uploads),2)
  self.assertTrue(all('/.el-releases/test-release/' in s for s in uploads))
  self.assertFalse(any(s.startswith(('rm ','rename ')) for s in commands))
 def test_existing_install_preserves_both_directories(self):
  client=Remote();journal={};deploy.activate(client,self.config,'test',False,journal,lambda:None)
  ops=journal['activation'];self.assertEqual(len(ops),4)
  self.assertTrue(ops[0]['to'].endswith('previous-public'))
  self.assertTrue(ops[1]['to'].endswith('previous-app'))
  self.assertTrue(all(op['status']=='confirmed' for op in ops));self.assertEqual(journal['status'],'activated')
  self.assertFalse(any('/private' in s or s.startswith('rm ') for s in client.commands))
 def test_failure_stops_activation_and_retains_uncertain_operation(self):
  client=Remote(fail_at=4);journal={}
  with self.assertRaises(RuntimeError):deploy.activate(client,self.config,'test',False,journal,lambda:None)
  self.assertEqual(len(journal['activation']),2)
  self.assertEqual(journal['activation'][0]['status'],'confirmed')
  self.assertEqual(journal['activation'][1]['status'],'pending')
 def test_initial_install_does_not_rename_previous(self):
  client=Remote();journal={};deploy.activate(client,self.config,'test',True,journal,lambda:None)
  self.assertEqual(len(journal['activation']),2)
  self.assertFalse(any('previous-' in s for s in client.commands))
 def test_strict_host_checking(self):
  command=deploy.SFTP(self.config).command
  self.assertIn('-oStrictHostKeyChecking=yes',command)
  self.assertIn('-oBatchMode=yes',command)
  self.assertIn('-oForwardAgent=no',command)
 def test_changed_upload_blocks_verification(self):
  class ChangedRemote:
   def run(self,commands):
    for command in commands:
     target=command.rsplit(' ',1)[1].strip('"');Path(target).write_bytes(b'alterado')
  with tempfile.TemporaryDirectory() as folder:
   files=[{'relative':'public/index.html','sha256':hashlib.sha256(b'original').hexdigest()}]
   with self.assertRaises(RuntimeError):deploy.verify_upload(ChangedRemote(),self.config,'test',files,Path(folder))

if __name__=='__main__':unittest.main()
