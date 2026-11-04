from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
class BusinessEditorBoundaryTest(unittest.TestCase):
 def test_editor_view_receives_request_and_database_data(self):
  text=(ROOT/'backend/app/views/business/editorial.php').read_text()
  for forbidden in ('db()', '$_GET', '$_POST', '$_SESSION', 'SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', "'/phone-fields.php'"):
   self.assertNotIn(forbidden,text)
 def test_editor_uses_selector(self):
  text=(ROOT/'backend/public/business/editorial.php').read_text()
  self.assertIn('new BusinessEditorialSelector',text)
  for query in ('SELECT id,original_name FROM business_editorial_images', 'SELECT id,asset_path FROM media_assets', 'SELECT public_id FROM businesses'):
   self.assertNotIn(query,text)
if __name__=='__main__':unittest.main()
