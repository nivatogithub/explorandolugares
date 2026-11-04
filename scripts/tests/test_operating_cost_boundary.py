from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]

class OperatingCostBoundaryTest(unittest.TestCase):
 def test_controller_delegates_reads_after_admin_guard(self):
  source=(ROOT/'backend/public/admin/costs.php').read_text()
  self.assertNotIn('->prepare(',source)
  self.assertNotIn('->query(',source)
  self.assertLess(source.index('require_admin_session()'),source.index('new OperatingCostSelector('))

if __name__=='__main__':unittest.main()
