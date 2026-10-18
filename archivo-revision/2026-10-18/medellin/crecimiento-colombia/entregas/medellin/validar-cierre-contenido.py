from pathlib import Path
import hashlib,json
from html.parser import HTMLParser
r=Path(__file__).resolve().parent
read=lambda f:json.loads((r/f).read_text())
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
d=read('CIERRE_CONTENIDO_SIN_FOTOS.json');c=read('contenido.json');a=read('experiencias.json');main=read('principales-v3.json');roles=read('roles-contenido.json')
assert all(sha(r/f)==h for f,h in d['protected_files'].items())
assert all(sha(r/f)==h for f,h in d['protected_image_files'].items())
assert all(sha(r/f)==x['after_sha256'] for f,x in d['authorized_text_changes'].items())
assert len(c['places'])==28 and len(main)==8
assert [x['slug'] for x in main]==d['counts']['main_ids']==roles['top8_current_ids']
assert 'metro-metrocable' not in {x['slug'] for x in c['places']}
assert len(a)==11 and sum(x['editorial_role']!='hidden_external_candidate' for x in a)==9
assert all(x['coverage_counting']=='no_suma_otro_lugar' for x in a)
assert len({x['id'] for x in a})==len(a)
assert all(set(x['translations'])=={'es','en','pt-BR'} for x in c['places']+a+read('directorio.json'))
assert read('eventos.json')==[]==c['agenda']['events']
ids={x['slug'] for x in c['places']}
assert all(set(x.get('place_ids',[]))<=ids for x in a)
metro=next(x for x in a if x['id']=='metro-metrocable-recorrido')
assert metro['editorial_role']=='mobility_experience' and metro['operator'] is None and not metro.get('image_file')
assert not any(d[k] for k in ['database_changed','preproduction_changed','production_changed','ai_generated','ledger_changed'])
class QA(HTMLParser):
 def handle_starttag(self,tag,attrs):
  x=dict(attrs)
  if tag=='img':assert (r/x['src']).is_file()
  if tag=='select' and any(t in x.get('data-part','') for t in ['referencia','licencia','encuadre']):assert 'disabled' in x
q=QA();q.feed((r/'preview.html').read_text());q.feed((r/'propuestos-top8-v6.html').read_text())
print('PASS cierre: 8 lugares sin cambiar,28 borradores,9 experiencias visibles/2 ocultas,3 idiomas,0 eventos, fotos y ledgers idénticos; revisión fotográfica suspendida.')
