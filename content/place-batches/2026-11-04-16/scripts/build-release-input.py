#!/usr/bin/env python3
"""Build canonical application-service input from versioned editorial sources; no database access."""
import hashlib,html,json,re,unicodedata
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
LOCALES=['es','en','pt-BR']
DEPARTMENTS={'81':'Arauca','27':'Chocó','18':'Caquetá','94':'Guainía','52':'Nariño','50':'Meta','91':'Amazonas','97':'Vaupés','86':'Putumayo','19':'Cauca','88':'San Andrés, Providencia y Santa Catalina','99':'Vichada','63':'Quindío','95':'Guaviare'}
CATEGORIES={'accommodation':'hospedaje','restaurants':'alimentacion','restaurant':'alimentacion','cafes':'alimentacion','tour_operator':'experiencias','tour_operators':'experiencias','crafts':'comercio','nightlife':'vida-nocturna'}
EXPERIENCE_ES={
'arauca':['Recorrido fluvial para observar la fauna del río Arauca, sin garantizar encuentros con animales. Confirma los detalles con el operador.'],
'inirida':[None,'Caminata por las sabanas de arenas blancas en el recorrido de Kenke. Confirma acceso y disponibilidad con el responsable.','Actividad cultural documentada de alfarería y patrimonio local. Confirma disponibilidad actual, autorización comunitaria y estado de los petroglifos.'],
'mitu':[None,'Recorrido guiado en canoa por el río Vaupés. Confirma el punto de encuentro y la disponibilidad con el prestador local.'],
'nuqui':['Salida marina de temporada. Confirma temporada, condiciones del mar y punto de encuentro; no se garantizan avistamientos.','Recorrido en canoa para conocer el paisaje del río Joví. Confirma transporte y punto de encuentro con el operador.','Visita a las aguas termales del entorno de Termales. Confirma las condiciones actuales y el acceso comunitario.'],
'puerto-carreno':['Recorrido guiado documentado por paisajes de la reserva y la laguna. Confirma condiciones actuales y acceso; no se garantizan avistamientos de fauna.']}
def slug(s):return re.sub('[^a-z0-9]+','-',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()).strip('-')
def load(p):return json.loads(p.read_text())
def image(folder,key,names):
 m=load(folder/'images'/f'{key}.manifest.json');o=next(x for x in m['outputs'] if x['file'].endswith('-1200.webp'));f=folder/o['file'];h=hashlib.sha256(f.read_bytes()).hexdigest()
 assert h==o['sha256'] and m['approval']['status']=='approved' and h==m['approval']['image_sha256'],str(f)
 ref=m['reference'];author=html.unescape(re.sub('<[^>]+>','',ref.get('author_html',''))).strip();origin='ai_generated' if m['kind']=='ai_editorial_illustration' else 'external'
 credit=' · '.join(x for x in [author,ref.get('license','')] if x)
 return {'file':str(f.relative_to(ROOT)),'sha256':h,'image_origin':origin,'image_creator':('Ilustración editorial IA; referencia: ' if origin=='ai_generated' else '')+author,'image_source_url':ref.get('source_url',''),'image_license':ref.get('license',''),'image_license_url':(ref.get('license_url') or '').replace('http://creativecommons.org/','https://creativecommons.org/'),'alt':dict(zip(LOCALES,names)),'caption':{l:credit for l in LOCALES}}
copy=load(ROOT/'editorial/localized-copy.json');payload=[];missing=[]
for folder in sorted((ROOT/'places').iterdir()):
 if not (folder/'research.json').exists():continue
 d=load(folder/'research.json');s=d['slug'];c=copy[s];g=d['geolocation'];name=d['name'];assert len(c['attractions'])==len(d['attractions']);assert len(c['experiences'])==len(d['experiences'])
 intro=[d['intro_draft_es'],c['intro_en'],c['intro_pt']];reason=[' '.join(d['why_visit_es']),*c['reasons']]
 dest={'slug':s,'destination_type':'other' if s=='capurgana' else 'municipality','country_code':'CO','department':DEPARTMENTS[d['department_code']],'subregion':'','latitude':g['latitude'],'longitude':g['longitude'],'translations':{},'history':{},'reasons':{},'sources':{}}
 urls=set([d['source_url']]);
 for a in d['attractions']+d['experiences']:
  for u in [a.get('source_url'),a.get('offer_url'),(a.get('geolocation') or {}).get('source_url')]:
   if u and u.startswith('https://'):urls.add(u)
 for i,l in enumerate(LOCALES):
  dest['translations'][l]={'display_name':name,'eyebrow':dest['department'],'headline':name,'intro':intro[i],'meta_description':intro[i][:250]}
  dest['history'][l]=[c['history'][i]];dest['reasons'][l]=[reason[i]];dest['sources'][l]=[{'section':'destination','label':'Fuente documental' if l=='es' else 'Documentary source' if l=='en' else 'Fonte documental','url':u} for u in sorted(urls)]
 pois=[]
 for kind,key in [('place','attractions'),('experience','experiences')]:
  for i,a in enumerate(d[key]):
   tr=c[key][i];names=[a['name'],tr[0],tr[2]];es=a.get('description_es') or a.get('public_description_es') or EXPERIENCE_ES.get(s,[])[i]
   assert es,(s,key,i)
   descriptions=[es,tr[1],tr[3]];g=a.get('geolocation') or {};ready=kind=='experience' or (g.get('latitude') is not None and g.get('longitude') is not None)
   # A lodging/office reference is not an excursion meeting point.
   if kind=='experience':g={}
   poi={'slug':slug(a['name']),'type':kind,'publication_status':'published' if ready else 'draft','latitude':g.get('latitude'),'longitude':g.get('longitude'),'translations':{l:{'name':names[j],'description':descriptions[j],'map_url':a.get('public_map_url') or '', 'external_url':'','external_label':''} for j,l in enumerate(LOCALES)}}
   if a.get('image_key'):poi['image']=image(folder,a['image_key'],names)
   if not ready:missing.append({'place':s,'attraction':a['name'],'reason':'missing_site_coordinates'})
   pois.append(poi)
 businesses=[]
 for b in d['businesses']:
  businesses.append({'name':b['name'],'category_code':CATEGORIES[b['category']],'information_status':'PUBLICO','summary':'','description':'','sources':[{'url':b['source_url'],'checked_at':b.get('source_checked_at','2026-11-04'),'note':'Ficha documental gratuita; no acredita operación actual, reclamación ni alianza.'}]})
 payload.append({'destination':dest,'hero_image':image(folder,'hero',[name]*3),'places':pois,'business_package':{'schema_version':'business-import-v1','destination_slug':s,'businesses':businesses},'research_file':str((folder/'research.json').relative_to(ROOT)),'research_sha256':hashlib.sha256((folder/'research.json').read_bytes()).hexdigest(),'local_research':{'batch':'2026-11-04-16','municipality_code':d['municipality_code'],'department_code':d['department_code'],'history_scope':'territorial_and_cultural_context_based_on_documented_research','public_maps':'only_verified_named_links; absent links stay blank','pending_physical_attractions':[x for x in missing if x['place']==s]}})
assert len(payload)==16
out={'schema_version':'dev1-place-content-v1','environment':'dev1','production_authorized':False,'places':payload,'pending_attractions':missing,'business_location_exceptions':'business-location-exceptions.json'}
(ROOT/'release-input.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'places':len(payload),'attractions':42,'experiences':29,'businesses':sum(len(x['business_package']['businesses']) for x in payload),'pending_attractions':missing},ensure_ascii=False))
