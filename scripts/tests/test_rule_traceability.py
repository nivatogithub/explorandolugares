from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[2]
class RuleTraceability(unittest.TestCase):
 def test_every_original_obligation_remains_explicit(self):
  archive=(ROOT/'docs/unificacion/AGENTS_BASE_2026-10-21.md').read_text()
  canonical=(ROOT/'docs/REGLAS_PROYECTO.md').read_text()
  for line in archive.splitlines():
   if line.startswith('- '):self.assertIn(line,canonical,line[:90])
if __name__=='__main__':unittest.main()
