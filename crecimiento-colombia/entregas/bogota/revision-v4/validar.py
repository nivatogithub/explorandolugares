from pathlib import Path
import json,hashlib
p=Path(__file__).parent;d=p.parent
r=lambda n:json.loads((p/n).read_text())
a=r('candidatas-visuales.json');texts=r('textos-propuestos.json');audit=r('auditoria-componentes.json')
assert len(a['options'])==5
assert all(not x['aprobado'] for x in a['options'])
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
assert sum(bool(x['direccion']) for x in b)==9
assert audit['componentes']['aprobacion']['estado']=='0_aprobaciones_v4'
print('OK:5opcionesporuso,sinaprobaciones,hasheslocales,3idiomas,10fichas/9direcciones,procedenciaypendientesexplícitos.')
