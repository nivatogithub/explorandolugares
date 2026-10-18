import unittest,tempfile,shutil,json,copy
from pathlib import Path
import validar as v
class ValidationTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.previous=v.ROOT
        v.ROOT=(Path(self.tmp.name)/'plan').resolve();shutil.copytree(self.previous,v.ROOT,ignore=shutil.ignore_patterns('__pycache__'))
    def tearDown(self):v.ROOT=self.previous;self.tmp.cleanup()
    def manifest(self):
        inv=v.read(v.ROOT/'inventario.json');return v.ROOT/inv['departments'][0]['places'][0]['path']
    def mutate(self,path,fn):
        data=v.read(path);fn(data);path.write_text(json.dumps(data))
    def test_legacy_priority_preserved_in_snapshot(self):
        inv,ms=v.validate()
        legacy=v.read(v.ROOT/'historico/clasificacion-antioquia-propietario.json')
        source=json.loads((self.previous.parent/'backend/app/contact-options.json').read_text())
        self.assertEqual(legacy['municipalityCatalog'],source['municipalityCatalog'])
        self.assertEqual(len(legacy['municipalityCatalog']),125)
        self.assertTrue(all(not g['passed'] for m in ms for g in m['gates'].values()))
    def test_gate_without_evidence_rejected(self):
        self.mutate(self.manifest(),lambda m:m['gates']['fotos'].update(passed=True))
        with self.assertRaisesRegex(ValueError,'Gate sin evidencia'):v.validate()
    def test_published_without_gates_rejected(self):
        self.mutate(self.manifest(),lambda m:m.update(state='published'))
        with self.assertRaisesRegex(ValueError,'Aprobación sin gates'):v.validate()
    def test_parent_cycle_rejected(self):
        p=v.ROOT/'inventario.json'
        def cycle(inv):
            a,b=inv['departments'][0]['places'][:2];a['parent_place_id']=b['id'];b['parent_place_id']=a['id']
        self.mutate(p,cycle)
        with self.assertRaisesRegex(ValueError,'Ciclo territorial'):v.validate()
    def test_score_tampering_rejected(self):
        self.mutate(v.ROOT/'inventario.json',lambda inv:inv['departments'][0]['places'][0].update(score=1))
        with self.assertRaisesRegex(ValueError,'Puntuación inconsistente'):v.validate()
    def test_path_escape_rejected(self):
        with self.assertRaisesRegex(ValueError,'Ruta fuera'):v.local('../../outside')
    def test_approval_hash_changes_with_content(self):
        m=v.read(self.manifest());before=v.content_hash(m);m['next_action']='Otra acción'
        self.assertNotEqual(before,v.content_hash(m))
        before=v.content_hash(m);m['state']='review';self.assertEqual(before,v.content_hash(m))
if __name__=='__main__':unittest.main()
