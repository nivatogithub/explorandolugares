from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
class RequestDetailBoundaryTest(unittest.TestCase):
 def test_view_is_independent_of_request_and_database(self):
  for name in ('request-details.php','request-snapshot.php'):
   text=(ROOT/'backend/app/views/admin'/name).read_text()
   for forbidden in ('db()', '$_GET', '$_POST', '$_SESSION', 'SELECT ', 'INSERT ', 'UPDATE ', 'DELETE '):
    self.assertNotIn(forbidden,text,(name,forbidden))
 def test_controller_uses_selector_after_authorization(self):
  text=(ROOT/'backend/public/admin/request-details.php').read_text()
  self.assertNotIn('SELECT ',text)
  self.assertLess(text.index("if(empty($_SESSION['admin']))"),text.index('new AdminRequestDetailSelector'))
if __name__=='__main__':unittest.main()
