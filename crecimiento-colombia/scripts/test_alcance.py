import unittest,json,copy
from alcance import ROOT,eligible,scoped_rows
from turismo import ordered_rows
from operacion import validate
class ScopeTests(unittest.TestCase):
    def setUp(self):
        self.inv=json.loads((ROOT/'inventario.json').read_text());self.rows,self.t=ordered_rows(self.inv)
    def test_queue_limited_to_p1_p2(self):
        active,deferred,_=scoped_rows(self.rows,self.t)
        self.assertEqual(len(active),97);self.assertEqual(len(deferred),3)
        self.assertTrue(all(d['priority']<=2 and p['priority']<=2 for d,p,di,pi in active))
    def test_legacy4_rejected_even_when_reclassified_p1(self):
        legacy=json.loads((ROOT/'historico/clasificacion-antioquia-propietario.json').read_text())
        name=next(x['name'] for x in legacy['municipalityCatalog'] if x['pilotCategory']==4)
        self.assertFalse(eligible({'id':'CO-05','priority':1},{'name':name,'priority':1},{'legacy_pilot_category':None}))
    def test_explicit_legacy4_rejected(self):
        self.assertFalse(eligible({'id':'CO-99','priority':1},{'name':'candidato','priority':1},{'legacy_pilot_category':4}))
    def test_p3_department_does_not_enter_via_p1_place(self):
        self.assertFalse(eligible({'id':'CO-99','priority':3},{'name':'candidato','priority':1},{}))
    def test_deferred_cannot_receive_worker(self):
        op=json.loads((ROOT/'operacion.json').read_text());op['deferred'][0]['worker']='Lugar 1'
        with self.assertRaisesRegex(ValueError,'fuera de alcance'):validate(op)
    def test_target_denominator_separate(self):
        report=json.loads((ROOT/'cobertura-operativa.json').read_text())
        self.assertEqual(len(report['places']),100);self.assertEqual(report['medium_term_target']['place_count'],97)
        self.assertEqual(report['medium_term_target']['excluded_legacy_category4_count'],22)
if __name__=='__main__':unittest.main()
