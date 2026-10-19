import importlib.util,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('audit_package',Path(__file__).parents[1]/'preparar-publicacion-auditoria.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class FirstAuditPackageTests(unittest.TestCase):
 def test_only_explicit_owned_delta_is_selected(self):
  base={'files':[{'relative':'app/bootstrap.php','sha256':'old'},{'relative':'public/business/login.php','sha256':'same'}]}
  paths=['app/bootstrap.php','app/SubscriptionChangeService.php','public/business/login.php','public/assets/header-controls.css','public/assets/menu-principal.js','public/assets/place-sections.js','public/agenda/index.html','public/destino.php','app/content/destinos-dinamicos.json','public/assets/destinos/hero.webp','private/config.php']
  selected,excluded=m.select([{'relative':p,'sha256':'same' if p.endswith('login.php') else 'new'} for p in paths],base)
  self.assertEqual({f['relative'] for f in selected},{'app/bootstrap.php','app/SubscriptionChangeService.php'})
  self.assertNotIn('public/business/login.php',{f['relative'] for f in excluded})
 def test_migrations_are_an_exact_pair(self):
  selected,_=m.select([{'relative':'app/database/migrations/'+n+'.mysql.sql','sha256':'new'} for n in ['043-account-lifecycle','044-subscription-changes','045-unapproved']],{'files':[]})
  self.assertEqual(len(selected),2)
 def test_no_content_or_visual_identity_in_allowlist(self):
  for p in m.ALLOWED:
   self.assertNotIn('/destinos/',p);self.assertNotIn('/content/',p);self.assertNotIn('header-controls',p);self.assertNotIn('portal-tokens',p)
if __name__=='__main__':unittest.main()
