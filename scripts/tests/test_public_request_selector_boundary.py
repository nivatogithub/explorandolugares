from pathlib import Path
import re, unittest
ROOT=Path(__file__).resolve().parents[2]
class PublicRequestSelectorBoundaryTest(unittest.TestCase):
 def test_admin_reads_through_selector(self):
  controller=(ROOT/'backend/public/admin/public-requests.php').read_text()
  self.assertIn('new PublicRequestSelector(db())',controller)
  self.assertNotRegex(controller,r'(?i)\bSELECT\b.*\bFROM\s+public_requests\b')
  self.assertLess(controller.index('require_admin_session()'),controller.index('new PublicRequestSelector'))
 def test_selector_is_read_only(self):
  selector=(ROOT/'backend/app/PublicRequestSelector.php').read_text()
  self.assertNotRegex(selector,r'(?i)\b(INSERT|UPDATE|DELETE)\s+(INTO|public_requests|FROM)\b')
  self.assertNotRegex(selector,r'\$_(GET|POST|SESSION)')
if __name__=='__main__':unittest.main()
