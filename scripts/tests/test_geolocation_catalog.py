import json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[2]
class GeolocationCatalog(unittest.TestCase):
 def test_approved_canonical_catalog_and_snapshots(self):
  policy=json.loads((R/'backend/app/content/geolocation-policy.json').read_text());release=json.loads((R/'content/release-places-businesses.json').read_text());retired=set(policy['retired_public_ids']);exempt=set(policy['business_exceptions'])
  self.assertEqual(len(retired),235)
  def businesses(rows):
   for b in rows:
    if b['public_id'] in retired:self.assertNotEqual(b['publication_status'],'published',b['slug'])
    elif b['publication_status']=='published' and b['category_code']!='experiencias' and b['public_id'] not in exempt:
     self.assertIsNotNone(b.get('latitude'),b['slug']);self.assertIsNotNone(b.get('longitude'),b['slug'])
  businesses(release['tables']['businesses'])
  for f in (R/'content/destination-migration').glob('*.json'):
   snapshot=json.loads(f.read_text());self.assertEqual(snapshot['geolocation_policy'],policy);businesses(snapshot['tables']['businesses'])
  for slug,d in release['editorial'].items():
   self.assertIsNotNone(d['latitude'],slug);self.assertIsNotNone(d['longitude'],slug)
   for p in d['places']:
    exception=any(e['destination']==slug and e['slug']==p['slug'] for e in policy['place_exceptions'])
    if p['type']=='place' and p['publication_status']=='published' and not exception:
     self.assertIsNotNone(p.get('latitude'),slug+'/'+p['slug']);self.assertIsNotNone(p.get('longitude'),slug+'/'+p['slug'])
 def test_rural_exception_has_no_map(self):
  native=json.loads((R/'content/destinos-dinamicos.json').read_text());exception=json.loads((R/'backend/app/content/geolocation-policy.json').read_text())['place_exceptions'][0]
  d=next(d for d in native['destinations'] if d['slug']==exception['destination'])
  index=next(i for i,p in enumerate(d['translations']['es']['places']) if p['slug']==exception['slug'])
  for tr in d['translations'].values():
   p=tr['places'][index];self.assertIsNone(p.get('latitude'));self.assertIsNone(p.get('longitude'));self.assertEqual(p['map_url'],'')
if __name__=='__main__':unittest.main()
