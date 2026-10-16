from pathlib import Path
import json,hashlib
p=Path(__file__).parent
read=lambda n:json.loads((p/n).read_text())
a=read('paquete-borrador.json');s=read('fuentes.json');d=read('directorio.json');m=read('imagenes.json')
assert a['destino_id']=='CO-11-bogota'
assert a['worker']=='Lugar 2' and a['rama']=='lugar/CO/bogota-dc/bogota'
assert a['publicable'] is False and a['aprobado'] is False and a['publicado'] is False
assert len(s)>=10 and len({x['id'] for x in s})==len(s)
ids={x['id'] for x in s}
assert all(x['url'].startswith('https://') and x['consulta']=='2026-10-16' for x in s)
assert len(d)>=10 and len({x['id'] for x in d})==len(d)
assert len({x['categoria'] for x in d})>=3
assert all(x['fuente'] in ids and not x['alianza'] and not x['contactado'] and not x['representante_verificado'] for x in d)
for lang in ['es','en','pt-BR']:
 t=a['contenido'][lang]
 assert len(t['historia'])>100 and len(t['atractivos'])>=3 and len(t['experiencias'])>=3
 assert all(x[2] in ids for x in t['atractivos']+t['experiencias'])
 assert (p/f'contenido.{lang}.md').is_file()
assert m['activos'][0]['permite_comercial'] and m['activos'][0]['permite_adaptacion']
assert not m['activos'][0]['aprobacion_humana']
assert a['gates']['imagenes']=='parcial_portada_lista_faltan_atractivos'
assert m['activos'][0]['original_local'] and len(m['activos'][0]['derivados'])==2
assert hashlib.sha256((p/m['activos'][0]['original_local']).read_bytes()).hexdigest()==m['activos'][0]['sha256']
assert 'noindex,nofollow' in (p/'previsualizacion.html').read_text()
manifest=p/'evidencia-sha256.json'
if manifest.exists():
 entries=read(manifest.name)
 actual={str(x.relative_to(p)) for x in p.rglob('*') if x.is_file() and x.name!='evidencia-sha256.json' and '__pycache__' not in x.parts}
 assert actual==set(entries),'Manifiesto no cubre todos los archivos'
 assert all(hashlib.sha256((p/f).read_bytes()).hexdigest()==h for f,h in entries.items()),'Hash modificado'
print('OK: identidad, 13 fuentes, 3 idiomas, 5 atractivos, 4 experiencias, 10 fichas/3 categorias, bloqueos/gates y hashes. No aprueba publicacion.')
