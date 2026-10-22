#!/usr/bin/env python3
"""Read-only source/package inventory for presentation consolidation."""
from pathlib import Path
import argparse, hashlib, json, re
ROOT=Path(__file__).resolve().parents[1]
RULES=['AGENTS.md','PROYECTO_INDEPENDIENTE.md','docs/CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md','docs/GUIA_ESTILO_PORTAL.md','docs/REGLAS_OPERATIVAS_DESPLIEGUE.md','docs/PREPRODUCCION.md','preprod/README.md','docs/PARIDAD_PRODUCCION.md','docs/PRODUCCION_IMAGENES_IA.md','docs/REGLAS_FIDELIDAD_VISUAL_DESTINOS.md','.agents/skills/imagenes-destinos/SKILL.md','docs/GOBERNANZA_AGENTES.md','docs/POLITICA_EXPERIENCIAS_DE_OPERADORES.md','crecimiento-colombia/GUIA_GENERACION_DESTINOS.md','docs/ENTORNO_LOCAL.md']
def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def family(path):
 p=path.as_posix()
 for term,label in [('admin/','administración'),('business/','cuentas/negocios'),('negocios/','cuentas/negocios'),('privacidad','privacidad'),('contacto','contacto'),('negocios-locales','comercial'),('destinos/','lugares')]:
  if term in p:return label
 return 'general'
def inventory(public):
 routes=[]
 for path in sorted(public.rglob('*')):
  if not path.is_file() or path.suffix not in {'.html','.php'}:continue
  text=path.read_text();relative=path.relative_to(public)
  routes.append({'file':relative.as_posix(),'family':family(relative),'sha256':digest(path),'headers':len(re.findall(r'<header\b',text)),'footers':len(re.findall(r'<footer\b',text)),'resources':re.findall(r'(?:src|href)=[\"\x27]([^\"\x27]+\.(?:js|css)(?:\?[^\"\x27]*)?)',text),'dynamic':'<?php' in text or '<?=' in text})
 rules=[]
 for name in RULES:
  path=ROOT/name
  if not path.is_file():rules.append({'file':name,'missing':True});continue
  items=[];heading=''
  for number,line in enumerate(path.read_text().splitlines(),1):
   if line.startswith('#'):heading=line.lstrip('# ')
   if re.match(r'^(?:- |\d+\. )',line):items.append({'line':number,'section':heading,'rule':line})
  rules.append({'file':name,'sha256':digest(path),'obligations':items})
 return {'schema_version':1,'routes':routes,'rules':rules}
if __name__=='__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--public',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(inventory(a.public),ensure_ascii=False,indent=2)+'\n')
