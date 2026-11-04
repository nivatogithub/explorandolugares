from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
class CancellationBoundaryTest(unittest.TestCase):
 def test_view_has_no_request_or_database_access(self):
  text=(ROOT/'backend/app/views/admin/cancellations.php').read_text()
  for forbidden in ('db()', '$_GET', '$_POST', '$_SESSION', 'SELECT ', 'INSERT ', 'UPDATE ', 'DELETE '):self.assertNotIn(forbidden,text)
 def test_controller_authorizes_before_reading(self):
  text=(ROOT/'backend/public/admin/cancellations.php').read_text()
  self.assertNotIn('SELECT ',text)
  self.assertLess(text.index('require_admin_session()'),text.index('new CancellationRequestSelector'))
if __name__=='__main__':unittest.main()
