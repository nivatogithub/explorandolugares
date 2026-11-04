from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class BusinessAudienceBoundaryTest(unittest.TestCase):
 def test_controller_delegates_sql_after_authentication(self):
  source=(ROOT/'backend/public/api/entity-audience.php').read_text()
  self.assertNotIn('->prepare(',source)
  self.assertNotIn('->query(',source)
  self.assertLess(source.index("if(!$admin&&!$owner)"),source.index('->hasActiveMembership('))
  self.assertLess(source.index('require_business_portal_access()'),source.index('->hasActiveMembership('))
  self.assertLess(source.index('->hasActiveMembership('),source.index('->entityReport('))

if __name__=='__main__':unittest.main()
