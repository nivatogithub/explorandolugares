import unittest,json,copy
from pathlib import Path
from turismo import ROOT,ordered_rows,validate_tourism
class TourismTests(unittest.TestCase):
    def setUp(self):self.inv=json.loads((ROOT/'inventario.json').read_text())
    def test_all_country_and_candidates_reviewed(self):
        rows,data=ordered_rows(self.inv);self.assertEqual(len(data['departments']),33);self.assertEqual(len(rows),100)
    def test_san_jeronimo_precedes_tamesis(self):
        rows,_=ordered_rows(self.inv);ids=[p['id'] for d,p,di,pi in rows];self.assertLess(ids.index('CO-05-san-jeronimo'),ids.index('CO-05-tamesis'))
    def test_preparation_does_not_order_tourism(self):
        before,_=ordered_rows(self.inv)
        for d in self.inv['departments']:
            d['score']=100-d['score']
            for p in d['places']:p['score']=100-p['score']
        after,_=ordered_rows(self.inv);self.assertEqual([p['id'] for d,p,di,pi in before],[p['id'] for d,p,di,pi in after])
    def test_priority_blocks_strict(self):
        rows,_=ordered_rows(self.inv);blocks=[(d['priority'],p['priority']) for d,p,di,pi in rows];self.assertEqual(blocks,sorted(blocks))
    def test_major_destinations_in_first_block(self):
        rows,_=ordered_rows(self.inv);self.assertEqual([p['slug'] for d,p,di,pi in rows[:5]],['cartagena','bogota','medellin','santa-marta','san-andres'])
    def test_reclassified_place_must_match_method(self):
        self.inv['departments'][0]['places'][0]['priority']=3
        with self.assertRaisesRegex(ValueError,'Grupo turístico'):validate_tourism(self.inv)
    def test_legacy_categories_preserved(self):
        _,data=ordered_rows(self.inv)
        byid={p['id']:p for p in data['places']}
        self.assertEqual(byid['CO-05-tamesis']['legacy_pilot_category'],1)
        self.assertEqual(byid['CO-05-san-jeronimo']['legacy_pilot_category'],1)
        self.assertEqual(byid['CO-05-tamesis']['previous_priority'],1)
        self.assertEqual(byid['CO-05-tamesis']['priority'],2)
    def test_added_children_keep_parent(self):
        ps={p['slug']:p for d in self.inv['departments'] for p in d['places']}
        for child,parent in [('minca','santa-marta'),('palomino','dibulla'),('capurgana','acandi')]:self.assertEqual(ps[child]['parent_place_id'],ps[parent]['id'])
if __name__=='__main__':unittest.main()
