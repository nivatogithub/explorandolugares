import copy,json,unittest
from operacion import ROOT,validate,intervals,bundle

class OperationsTests(unittest.TestCase):
    def setUp(self):self.op=json.loads((ROOT/'operacion.json').read_text())
    def rejects(self,op):
        with self.assertRaises((ValueError,KeyError,TypeError)):validate(op)
    def test_real_seed(self):self.assertEqual(len(validate(self.op)[2]['queue']),100)
    def test_reordered_block(self):
        self.op['queue'][0],self.op['queue'][1]=self.op['queue'][1],self.op['queue'][0];self.rejects(self.op)
    def test_duplicate_place(self):
        self.op['queue'][1]['place_id']=self.op['queue'][0]['place_id'];self.rejects(self.op)
    def test_inventory_drift(self):self.op['inventory_sha256']='0'*64;self.rejects(self.op)
    def test_wrong_worker_name(self):self.op['workers'][0]['name']='fuentes';self.rejects(self.op)
    def test_third_worker_without_metrics(self):self.op['max_active']=3;self.rejects(self.op)
    def test_assignment_without_approval(self):
        self.op['queue'][0].update(state='asignado',worker='Lugar 1');self.rejects(self.op)
    def test_component_without_evidence(self):
        self.op['queue'][0]['components']['fotos_reales']['status']='completo';self.rejects(self.op)
    def test_local_evidence_escape(self):
        c=self.op['queue'][0]['components']['fotos_reales'];c.update(status='completo',evidence=['../../../../etc/passwd'],checked_at='2026-10-16T18:00:00Z',reviewer='test');self.rejects(self.op)
    def test_stale_approval(self):
        self.op['queue'][0]['approval']={'hash':'0'*64};self.rejects(self.op)
    def test_intervals(self):
        q={'state':'revision','events':[{'type':'produccion_inicio','at':'2026-10-16T08:00:00Z'},{'type':'produccion_fin','at':'2026-10-16T10:00:00Z'},{'type':'revision_solicitada','at':'2026-10-16T10:00:00Z'},{'type':'revision_inicio','at':'2026-10-16T11:00:00Z'},{'type':'revision_fin','at':'2026-10-16T12:00:00Z'}]}
        m=intervals(q);self.assertEqual(m['produccion_inicio'],2);self.assertEqual(m['revision_solicitada'],1);self.assertEqual(m['revision_inicio'],1);self.assertIsNone(m['correccion_inicio'])
    def test_open_intervals_cannot_approve(self):
        with self.assertRaises(ValueError):intervals({'state':'aprobado','events':[{'type':'produccion_inicio','at':'2026-10-16T08:00:00Z'}]})
    def test_artifact_hash_modified(self):
        with self.assertRaises(ValueError):bundle({'artifacts':[{'path':'crecimiento-colombia/PLAN_MAESTRO.md','sha256':'0'*64}]})
    def test_wip_limit(self):self.op['max_active']=4;self.rejects(self.op)
if __name__=='__main__':unittest.main()
