import sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from css_components import component_ownership_conflicts
class ShellOwnershipContract(unittest.TestCase):
 def test_rejects_cross_component_patches(self):
  self.assertTrue(component_ownership_conflicts('.portal-shell~main.wrap{max-width:1440px}','portal-shell.css'))
  self.assertTrue(component_ownership_conflicts('.el-measurement-preferences{padding:4px}','portal-shell.css'))
  self.assertTrue(component_ownership_conflicts('@media(max-width:650px){.wrap{padding:1px}}','extra.css'))
 def test_allows_component_owners_and_parent_placement(self):
  self.assertFalse(component_ownership_conflicts('.el-measurement-preferences{margin:0;padding:9px 14px}','analytics.css'))
  self.assertFalse(component_ownership_conflicts('.portal-shell-footer .el-measurement-preferences{order:3;align-self:center}','portal-shell.css'))
  self.assertFalse(component_ownership_conflicts('.wrap{max-width:var(--el-wrap-max,1240px)}','style.css'))
 def test_current_sources_follow_ownership(self):
  root=Path(__file__).resolve().parents[2]
  for name,path in [('style.css','ensayos/v1/assets/style.css'),('portal-shell.css','backend/public/assets/portal-shell.css'),('analytics.css','backend/public/assets/cobertura/analytics.css')]:
   with self.subTest(name=name):self.assertEqual(component_ownership_conflicts((root/path).read_text(),name),[])
if __name__=='__main__':unittest.main()
