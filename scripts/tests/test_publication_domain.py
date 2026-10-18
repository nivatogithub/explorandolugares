import json,re,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
class PublicationDomainTest(unittest.TestCase):
 def test_release_has_no_public_draft_labels(self):
  pack=json.loads((ROOT/'content/release-places-businesses.json').read_text())
  for slug in pack['public_places']:
   for locale,t in pack['editorial'][slug]['translations'].items():
    with self.subTest(slug=slug,locale=locale):
     self.assertNotRegex(' '.join(t.get(k,'') for k in ['eyebrow','headline','intro','meta_description']),re.compile(r'borrador local|local draft|rascunho local|final images pending',re.I))
 def test_canonical_host_precedes_routes_and_preserves_local(self):
  rules=(ROOT/'ensayos/v1/.htaccess').read_text()
  self.assertIn('^www\\.explorandolugares\\.com',rules)
  self.assertIn('https://explorandolugares.com%{REQUEST_URI} [R=301,L,NE]',rules)
  self.assertLess(rules.index('Canonical production host'),rules.index('sitemap'))
if __name__=='__main__':unittest.main()
