from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]

class ParticipationBoundaryTest(unittest.TestCase):
 def test_controller_delegates_persistence_after_validation(self):
  source=(ROOT/'backend/public/api/participation.php').read_text()
  self.assertNotIn('->prepare(',source)
  self.assertNotIn('->query(',source)
  self.assertLess(source.index('csrf('),source.index('->record('))
  self.assertLess(source.index('lead_validation('),source.index('->record('))
  self.assertLess(source.index('->hasRequest('),source.index("throttle('registro'"))
  self.assertLess(source.index("throttle('registro'"),source.index('->record('))

if __name__=='__main__':unittest.main()
