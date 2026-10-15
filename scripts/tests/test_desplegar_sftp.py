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
 def test_separate_public_root(self):
  config={**self.config,'public_root':'/home/usuario/public_html/explorandolugares.com'}
  self.assertEqual(deploy.public_path(self.load(config)),config['public_root'])
  remote=Remote();journal={};deploy.activate(remote,config,'test',False,journal,lambda:None)
  self.assertEqual(journal['activation'][0]['from'],config['public_root'])
  self.assertEqual(journal['activation'][-1]['to'],config['public_root'])
  self.assertTrue(journal['activation'][1]['from'].startswith(config['remote_base']))
 def test_reject_other_public_roots(self):
  for path in ['/home/usuario/public_html','/home/usuario/public_html/otro.com','/home/otro/public_html/explorandolugares.com','/home/usuario/public_html/../explorandolugares.com',42]:
   with self.subTest(path=path),self.assertRaises(ValueError):self.load({**self.config,'public_root':path})
 def test_literal_sftp_paths(self):
  self.assertEqual(deploy.quote('/tmp/mi carpeta'), '"/tmp/mi carpeta"')
  for value in ['a\nrm /','a*','a?','a[1]']:
   with self.assertRaises(ValueError):deploy.quote(value)
 def test_staging_never_puts_in_live_directory(self):
  files=[{'relative':'public/index.html','local':'/tmp/index.html'},{'relative':'app/bootstrap.php','local':'/tmp/bootstrap.php'}]
  commands=deploy.stage_commands(self.config,'test-release',files)
  uploads=[s for s in commands if s.startswith('put ')]
  self.assertEqual(len(uploads),2)
  self.assertTrue(any('/public/.well-known/acme-challenge' in command and command.startswith('mkdir ') for command in commands))
  self.assertTrue(any('/public/cgi-bin' in command and command.startswith('mkdir ') for command in commands))
  self.assertTrue(all('/.el-releases/test-release/' in s for s in uploads))
  self.assertFalse(any(s.startswith(('rm ','rename ')) for s in commands))
 def test_existing_install_preserves_both_directories(self):
  client=Remote();journal={};deploy.activate(client,self.config,'test',False,journal,lambda:None)
  ops=journal['activation'];self.assertEqual(len(ops),4)
  self.assertTrue(ops[0]['to'].endswith('previous-public'))
  self.assertTrue(ops[1]['to'].endswith('previous-app'))
  self.assertTrue(all(op['status']=='confirmed' for op in ops));self.assertEqual(journal['status'],'activated')
  self.assertFalse(any('/private' in s or s.startswith('rm ') for s in client.commands))
 def test_activate_uploaded_release_uses_verified_stage_and_keeps_rollback_pair(self):
  client=Remote();journal={'status':'uploaded'};deploy.activate_uploaded(client,self.config,'test-release',journal,lambda:None)
  self.assertEqual(journal['status'],'activated')
  self.assertTrue(any('rename "/home/usuario/explorandolugares/.el-releases/test-release/public"' in command for command in client.commands))
  self.assertTrue(any('rename "/home/usuario/explorandolugares/.el-releases/test-release/app"' in command for command in client.commands))
  self.assertTrue(client.commands[-1].startswith('rmdir ') and '.el-deploy-lock' in client.commands[-1])
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
     if command.startswith('get '):
      target=command.rsplit(' ',1)[1].strip('"');Path(target).write_bytes(b'alterado')
  with tempfile.TemporaryDirectory() as folder:
   files=[{'relative':path,'sha256':hashlib.sha256(b'original').hexdigest()} for path in deploy.VERIFY_RELATIVE]
   files.append({'relative':'app/database/migrations/006-final.mysql.sql','sha256':hashlib.sha256(b'original').hexdigest()})
   with self.assertRaises(RuntimeError):deploy.verify_upload(ChangedRemote(),self.config,'test',files,Path(folder))
 def test_incremental_selects_only_changed_and_never_deletes(self):
  previous=[{'relative':'public/index.html','sha256':'old'},{'relative':'app/bootstrap.php','sha256':'same'}]
  current=[{'relative':'public/index.html','sha256':'new','local':'/tmp/index.html','bytes':1},{'relative':'app/bootstrap.php','sha256':'same','local':'/tmp/app.php','bytes':1}]
  changed=deploy.changed_files(previous,current)
  self.assertEqual([item['relative'] for item in changed],['public/index.html'])
  commands=deploy.incremental_commands(self.config,changed)
  self.assertTrue(any(command.startswith('put ') and command.endswith('/public/index.html"') for command in commands))
  with self.assertRaises(ValueError):deploy.changed_files(previous,current[:1])
 def test_incremental_creates_every_missing_parent_before_upload(self):
  files=[{'relative':'public/assets/leaflet/images/layers.png','local':'/tmp/layers.png','sha256':'new','bytes':1}]
  commands=deploy.incremental_commands({**self.config,'public_root':'/home/usuario/public_html/explorandolugares.com'},files)
  dirs=[command for command in commands if command.startswith('-mkdir ')]
  upload=next(index for index,command in enumerate(commands) if command.startswith('put '))
  self.assertIn('-mkdir "/home/usuario/public_html/explorandolugares.com/assets/leaflet"',dirs)
  self.assertIn('-mkdir "/home/usuario/public_html/explorandolugares.com/assets/leaflet/images"',dirs)
  self.assertLess(commands.index('-mkdir "/home/usuario/public_html/explorandolugares.com/assets/leaflet"'),upload)
  self.assertLess(commands.index('-mkdir "/home/usuario/public_html/explorandolugares.com/assets/leaflet/images"'),upload)
 def test_incremental_uploads_backend_before_public_entrypoints(self):
  files=[{'relative':'public/business/register.php','local':'/tmp/register.php','sha256':'p','bytes':1},
         {'relative':'app/BusinessAccountPortalService.php','local':'/tmp/service.php','sha256':'a','bytes':1}]
  commands=deploy.incremental_commands(self.config,files)
  uploads=[command for command in commands if command.startswith('put ')]
  self.assertIn('/tmp/service.php',uploads[0])
  self.assertIn('/tmp/register.php',uploads[1])
 def test_incremental_cleanup_only_removes_known_paths_after_uploads(self):
  files=[{'relative':'public/negocios-locales/index.html','local':'/tmp/new.html','sha256':'new','bytes':1}]
  commands=deploy.incremental_commands(self.config,files,['public/aliados/index.html','public/aliados.html'])
  upload=max(index for index,command in enumerate(commands) if command.startswith('put '))
  deletion=min(index for index,command in enumerate(commands) if command.startswith('rm '))
  self.assertLess(upload,deletion)
  self.assertTrue(any(command.startswith('rmdir ') and command.endswith('/public/aliados\"') for command in commands))

if __name__=='__main__':unittest.main()
