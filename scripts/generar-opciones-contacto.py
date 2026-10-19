#!/usr/bin/env python3
"""Publica las opciones del formulario desde el catálogo JSON compartido."""
from pathlib import Path
import json,re
from html import escape
ROOT=Path(__file__).resolve().parents[1]
source=ROOT/'backend/app/contact-options.json'
data=json.loads(source.read_text())
taxonomy=json.loads((ROOT/'content/service-categories.json').read_text())
pt=json.loads((ROOT/'content/pt-br.json').read_text())
PT_OVERRIDES={'Hospedaje':'Hospedagem','Alimentación':'Alimentação','Venta de licores':'Venda de bebidas alcoólicas','Bares, discotecas y vida nocturna':'Bares, casas noturnas e vida noturna','Supermercados, tiendas y comercio':'Supermercados, lojas e comércio','Farmacia y salud':'Farmácia e saúde','Experiencias y actividades':'Experiências e atividades','Cultura y entretenimiento':'Cultura e entretenimento','Servicios locales':'Serviços locais','Cómo llegar y moverte':'Como chegar e se locomover','Dónde quedarte y comer':'Onde ficar e comer','Qué hacer y disfrutar':'O que fazer e aproveitar','Compras y servicios':'Compras e serviços'}
def pt_text(value): return PT_OVERRIDES.get(value,pt.get(value,value))
for category in taxonomy['categories']: category['pt']=pt_text(category['label'])
for group in taxonomy['travelGroups']: group['pt']=pt_text(group['label'])
data['groups']=[{'label':'Tipo de servicio','en':'Service type','pt':'Tipo de serviço','categories':taxonomy['categories']}]
data['legacyCategoryAliases']=taxonomy['legacyAliases']
data['travelGroups']=taxonomy['travelGroups']
assigned=[value for group in data['travelGroups'] for value in group['types']]
assert sorted(assigned)==sorted(item['value'] for item in taxonomy['categories'])
catalog=data['municipalityCatalog']
assert len(catalog)==len({item['name'] for item in catalog})
assert all(item['pilotCategory'] in {1,2,3,4} for item in catalog)
data['municipalities']=[item['name'] for item in catalog if item['pilotCategory'] in data['enabledMunicipalityCategories']]
data['municipalities']=list(dict.fromkeys(data['municipalities']+data.get('additionalContactMunicipalities',[])))
assert data['municipalities']
# Persistir la selección activa que valida el servidor.
source.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
values=[c['value'] for group in data['groups'] for c in group['categories']]
assert len(values)==len(set(values))
geo=json.loads((ROOT/'backend/app/content/account-geography.json').read_text())
public={key:data[key] for key in ['department','municipalities','groups','travelGroups','landlinePrefixes','departmentPrefixes']}
public['departments']=geo['regions']['CO']
public['nationalMunicipalities']=geo['municipalities']
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
towns='<option value="">Selecciona tu municipio</option>'+''.join('<option data-landline-prefix="'+escape(data['landlinePrefixes'].get(name,''),quote=True)+'" value="'+escape(name,quote=True)+'">'+escape(name)+'</option>' for name in data['municipalities'])
for field,options in [('lead-category',categories)]:
    html,count=re.subn(r'(<select id="'+field+r'"[^>]*>).*?(</select>)',lambda m:m[1]+options+m[2],html,flags=re.S)
    if count!=1:raise ValueError('No se encontró el selector '+field)
page.write_text(html)
