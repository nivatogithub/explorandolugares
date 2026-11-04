from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
class LeadInboxBoundaryTest(unittest.TestCase):
 def test_view_uses_prepared_data(self):
  text=(ROOT/'backend/app/views/admin/lead-inbox.php').read_text()
  for token in ('db()', '$_GET', '$_POST', '$_SESSION', 'SELECT ', 'DELETE '):self.assertNotIn(token,text)
 def test_queries_and_writes_live_outside_controller(self):
  text=(ROOT/'backend/public/admin/index.php').read_text()
  self.assertNotIn('SELECT ',text);self.assertNotIn('DELETE ',text)
  self.assertIn('new LeadInboxSelector',text);self.assertIn('new LeadInboxWriter',text)
if __name__=='__main__':unittest.main()
