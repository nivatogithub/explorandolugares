from pathlib import Path
import json,hashlib
p=Path(__file__).parent;d=p.parent
r=lambda n:json.loads((p/n).read_text())
a=r('candidatas-visuales.json');texts=r('textos-propuestos.json');audit=r('auditoria-componentes.json')
assert len(a['options'])==5
for x in a['options']:
 if x['aprobado']:
  evidence=json.loads((p/x['approval_evidence']).resolve().read_text())
  assert evidence['candidate']==x['id']
  assert evidence['original_sha256']==x['sha256']
  assert evidence['user_decision']=='approved_conditional_identity' and evidence['condition_verified']
  assert not evidence['publication_authorized']

assert len({(x['uso'],x['opcion_numero']) for x in a['options']})==5
assert len({x['source_page'] for x in a['options']})==5
for x in a['options']:
 if x['local_file']:
  assert (d/x['local_file']).is_file()
  assert hashlib.sha256((d/x['local_file']).read_bytes()).hexdigest()==x['sha256']
 else:assert x['estado']=='pendiente_descarga_no_presentar_como_apta'
assert set(texts['contenido'])=={'es','en','pt-BR'}
assert all(set(t)=={'jardin','simon','historia','movilidad','practica'} for t in texts['contenido'].values())
b=json.loads((d/'directorio.json').read_text());assert len(b)==10
assert all(set(x['descripciones'])=={'es','en','pt-BR'} and not x['alianza'] for x in b)
assert sum(bool(x['direccion']) for x in b)==10
# auditoria-componentes registra una instantánea histórica; el ledger individual por hash manda.
experiences=json.loads((d/'experiencias.json').read_text())
ciclovia=next(x for x in experiences if x['id']=='ciclovia')
assert 'Carrera3' not in ciclovia['ubicacion']
assert ciclovia['clase']=='editorial_autonoma' and ciclovia['operador'] is None
assert all(x['foto_id']!='actividad-ciclovia' for x in experiences if x['clase']=='oferta_documentada_operador')
assert all(set(x['textos'])=={'es','en','pt-BR'} and not x['aprobado'] for x in experiences)
for x in a['options']:
 if x.get('tipo_archivo_local')=='miniatura_oficial_no_original': assert not x['maestro_completo_disponible']

print('OK: 5 opciones; aprobaciones individuales trazadas por hash, sin autorización de publicación; 3 idiomas; 10 fichas/10 direcciones.')
