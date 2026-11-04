import ast
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class PublicComponentBoundaries(unittest.TestCase):
 def test_controller_does_not_transform_completed_markup(self):
  source=(ROOT/'backend/app/PortalPageRenderer.php').read_text()
  for operation in ('preg_replace','preg_replace_callback','str_replace','DOMDocument','file_get_contents'):
   self.assertNotIn(operation,source,'Do not parse or rewrite completed page markup in this controller')

 def test_page_renderer_does_not_connect_to_database(self):
  source=(ROOT/'backend/app/PortalPageRenderer.php').read_text()
  self.assertNotIn('new PDO(',source)
  self.assertNotIn('PortalSettings::load(',source)
  provider=(ROOT/'backend/app/PortalPageData.php').read_text()
  for request_global in ('$_GET','$_POST','$_SESSION','$_SERVER'):
   self.assertNotIn(request_global,provider)

 def test_private_fragments_are_built_with_the_release(self):
  source=(ROOT/'scripts/_build-hosting.py').read_text()
  tree=ast.parse(source)
  self.assertTrue(tree.body)
  for builder in ('prepare-privacy-shell.py','prepare-commercial-shell.py','prepare-home-shell.py'):
   self.assertIn(builder,source)
  for builder in ('prepare-privacy-shell.py','prepare-commercial-shell.py','prepare-home-shell.py'):
   self.assertIn("release/'app/presentation/",(ROOT/'scripts'/builder).read_text())


if __name__=='__main__':unittest.main()
