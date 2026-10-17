from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import unquote,urlsplit
import json,hashlib
p=Path(__file__).parent
r=lambda n:json.loads((p/n).read_text())
a=r('paquete-borrador.json');s=r('fuentes.json');sites=r('sitios.json');ex=r('experiencias.json');m=r('imagenes.json');approval=r('aprobaciones.json')
assert a['destino_id']=='CO-11-bogota' and a['rama']=='lugar/CO/bogota-dc/bogota'
assert a['worker']=='Lugar 2' and not a['publicable'] and not a['aprobado'] and not a['publicado']
ids={x['id'] for x in s};assert len(ids)==len(s)==40
assert len(sites)==20 and len(ex)==18
assert len({x['id'] for x in sites})==20 and len({x['id'] for x in ex})==18
assert all(x['municipio_real']=='Bogotá D.C.' and set(x['textos'])=={'es','en','pt-BR'} and all(i in ids for i in x['fuentes']) for x in sites+ex)
assert sum(x['clase']=='editorial_autonoma' for x in ex)==12
assert sum(x['clase']=='oferta_documentada_operador' for x in ex)==3
assert sum(x['clase']=='candidato_por_validar' for x in ex)==3
assert all(x['public_status']=='oculto' for x in ex if x['clase']=='candidato_por_validar')
assert all(not x['alianza'] and not x['aprobado'] and x['ilustracion']=='no_generada' for x in ex)
assert len(m['activos'])==6 and not approval['eleccion_portada'] and not approval['publicacion']
assert m['activos'][0]['estado']=='rechazado_por_usuario_para_portada'
assert [x['id'] for x in m['activos'] if x.get('uso','').startswith('portada_opcion')]==['opcion-1-monserrate','opcion-2-centro']
for x in m['activos']:
 assert not x['aprobacion_humana']
 b=(p/x['original_local']).read_bytes();assert hashlib.sha256(b).hexdigest()==x['sha256']
 if x.get('sha1_original_declarado_commons'):assert hashlib.sha1(b).hexdigest()==x['sha1_original_declarado_commons']
 for d in x['derivados']:assert hashlib.sha256((p/d['archivo']).read_bytes()).hexdigest()==d['sha256']
class Check(HTMLParser):
 def handle_starttag(self,tag,attrs):
  attrs=dict(attrs)
  for key in ['src','href']:
   v=attrs.get(key,'')
   if v and not v.startswith(('#','http:','https:')): assert (p/unquote(urlsplit(v).path)).is_file(),v
Check().feed((p/'previsualizacion.html').read_text())
html=(p/'previsualizacion.html').read_text();assert 'Opción 1' in html and 'Opción 2' in html and 'noindex,nofollow' in html
for lang in ['es','en','pt-BR']:assert (p/f'contenido.{lang}.md').is_file()
d=r('directorio.json');assert len(d)==10 and len({x['categoria'] for x in d})==3
manifest=r('evidencia-sha256.json');actual={str(x.relative_to(p)) for x in p.rglob('*') if x.is_file() and x.name!='evidencia-sha256.json' and '__pycache__' not in x.parts}
assert actual==set(manifest)
assert all(hashlib.sha256((p/f).read_bytes()).hexdigest()==h for f,h in manifest.items())
selection=r('seleccion-editorial-v3.json');assert len(selection['selection'])==len(set(selection['selection']))==8
assert sum(bool(x.get('editorial_selected')) for x in sites)==8 and not selection['human_approved']
print('OK:40 fuentes,20 sitios,8 principales,18 actividades; originales históricos, enlaces y aprobación separados. No publicable.')
