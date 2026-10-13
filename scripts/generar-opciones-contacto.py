#!/usr/bin/env python3
"""Publica las opciones del formulario desde el catálogo JSON compartido."""
from pathlib import Path
import json,re
from html import escape
ROOT=Path(__file__).resolve().parents[1]
source=ROOT/'backend/app/contact-options.json'
data=json.loads(source.read_text())
taxonomy=json.loads((ROOT/'content/service-categories.json').read_text())
data['groups']=[{'label':'Tipo de servicio','en':'Service type','categories':taxonomy['categories']}]
data['legacyCategoryAliases']=taxonomy['legacyAliases']
data['travelGroups']=taxonomy['travelGroups']
assigned=[value for group in data['travelGroups'] for value in group['types']]
assert sorted(assigned)==sorted(item['value'] for item in taxonomy['categories'])
catalog=data['municipalityCatalog']
assert len(catalog)==len({item['name'] for item in catalog})
assert all(item['pilotCategory'] in {1,2,3,4} for item in catalog)
data['municipalities']=[item['name'] for item in catalog if item['pilotCategory'] in data['enabledMunicipalityCategories']]
assert data['municipalities']
# Persistir la selección activa que valida el servidor.
source.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
values=[c['value'] for group in data['groups'] for c in group['categories']]
assert len(values)==len(set(values))
public={key:data[key] for key in ['department','municipalities','groups','travelGroups']}
(ROOT/'ensayos/v1/assets/contact-options.json').write_text(json.dumps(public,ensure_ascii=False,indent=2)+'\n')
print('OK: opciones JSON compartidas para formulario y servidor.')
# Respaldo sin dependencia de la descarga: se genera del mismo catálogo.
page=ROOT/'ensayos/v1/negocios-locales/interes/index.html'
html=page.read_text()
categories='<option value="">Selecciona una opción</option>'
for group in data['groups']:
    categories+='<optgroup label="'+escape(group['label'],quote=True)+'">'
    categories+=''.join('<option value="'+escape(item['value'],quote=True)+'">'+escape(item['label'])+'</option>' for item in group['categories'])
    categories+='</optgroup>'
towns='<option value="">Selecciona tu municipio</option>'+''.join('<option value="'+escape(name,quote=True)+'">'+escape(name)+'</option>' for name in data['municipalities'])
for field,options in [('lead-category',categories),('lead-town',towns)]:
    html,count=re.subn(r'(<select id="'+field+r'"[^>]*>).*?(</select>)',lambda m:m[1]+options+m[2],html,flags=re.S)
    if count!=1:raise ValueError('No se encontró el selector '+field)
page.write_text(html)
