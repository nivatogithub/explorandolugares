"""Assemble local dev1 inputs from the three pinned place commits."""
import hashlib,json,re,subprocess,unicodedata
from pathlib import Path
root=Path(__file__).resolve().parents[1]
locales=['es','en','pt-BR']
pins={'san-andres':'ac1c91e1','palomino':'ceb3f5c4875313d235fe7315bcc6382079fa1785','uribia':'ec6da471a055285904740ef2924fb54fb234cc08'}
def digest(data): return hashlib.sha256(data).hexdigest()
def slug(s): return re.sub('[^a-z0-9]+','-',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()).strip('-')
def write(path,data):
 path.parent.mkdir(parents=True,exist_ok=True)
 path.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
batch=[]
for n,pin in pins.items():
 prefix=f'content/place-batches/2026-11-04-{n}/'
 def blob(rel):return subprocess.check_output(['git','show',pin+':'+prefix+rel])
 def obj(rel):return json.loads(blob(rel))
 base=f'places/{n}/'
 r=obj(base+'research.json'); loc=obj(base+'localized-copy.json'); ls=loc.get('locales') or loc['translations']
 ls={k.lower():v for k,v in ls.items()}
 if n=='palomino':r['businesses']=obj(base+'businesses.json')['businesses']
 department='San Andrés, Providencia y Santa Catalina' if n=='san-andres' else 'La Guajira'
 dest=dict(slug=n,destination_type='other' if n=='palomino' else 'municipality',country_code='CO',department=department,subregion='',latitude=r['geolocation']['latitude'],longitude=r['geolocation']['longitude'],translations={},history={},reasons={},sources={})
 context={'san-andres':['La cultura raizal tiene una presencia histórica en La Loma y San Luis. La Primera Iglesia Bautista de La Loma forma parte del patrimonio religioso de la isla.','Raizal culture has historical roots in La Loma and San Luis. The First Baptist Church in La Loma is part of the island’s religious heritage.','A cultura raizal tem raízes históricas em La Loma e San Luis. A Primeira Igreja Batista de La Loma faz parte do patrimônio religioso da ilha.'],'palomino':['El río Palomino marca el límite entre La Guajira y Magdalena. El poblado pertenece a Dibulla y su paisaje costero está ligado a los ríos que descienden de la Sierra Nevada de Santa Marta.','The Palomino River marks the boundary between La Guajira and Magdalena. The settlement belongs to Dibulla, and its coast is shaped by rivers flowing down from the Sierra Nevada de Santa Marta.','O rio Palomino marca a divisa entre La Guajira e Magdalena. O povoado pertence a Dibulla e sua paisagem costeira é marcada pelos rios que descem da Sierra Nevada de Santa Marta.']}
 for i,l in enumerate(locales):
  c=ls[l.lower()]; dest['translations'][l]=dict(display_name=r['name'],eyebrow=department,headline=c.get('headline') or c.get('title') or c['seo_title'],intro=c['intro'],meta_description=c.get('meta_description') or c.get('seo_description') or c['intro'][:240])
  dest['history'][l]=[c.get('history') or context[n][i]];dest['reasons'][l]=c['why_visit'];dest['sources'][l]=[dict(section='places',label=a['name'],url=a['source_url']) for a in r['attractions'] if str(a.get('source_url','')).startswith('https://')]
 md='review-v2/uribia-generated-v2/' if n=='uribia' else 'generated-review-v1/'
 manifest=obj(md+'manifest.json')
 def image(num,names):
  m=manifest[num-1]; f=m['file'];raw=blob(md+f);assert digest(raw)==m['sha256'];path=Path('media')/n/f;(root/path).parent.mkdir(parents=True,exist_ok=True);(root/path).write_bytes(raw)
  return dict(file=str(path),sha256=digest(raw),image_origin='ai_generated',image_creator='Explorando Lugares',image_source_url=m.get('original_source',''),image_license='Ilustración editorial para revisión en dev1',image_license_url='',alt=names,caption=dict(zip(locales,['Ilustración editorial','Editorial illustration','Ilustração editorial'])))
 pois=[]
 for group in ['attractions','experiences']:
  for i,a in enumerate(r[group]):
   ident=a.get('slug') or slug(a['name'])
   if group=='attractions' and n=='uribia' and ident not in r['selected_attraction_slugs']:continue
   tr={}
   for l in locales:
    c=ls[l.lower()];z=c[group];t=z[ident] if isinstance(z,dict) else dict(name=c['attraction_titles' if group=='attractions' else 'experience_titles'][i],description=z[i]);tr[l]=dict(name=t['name'],description=t['description'],map_url=a.get('public_map_url') or '',external_url='',external_label='')
   g=(a.get('geolocation') or {}) if group=='attractions' else {}
   pt=dict(slug=ident,type='place' if group=='attractions' else 'experience',publication_status='published',latitude=g.get('latitude'),longitude=g.get('longitude'),translations=tr)
   if group=='attractions':
    assert pt['latitude'] is not None
    num=({'pilon-de-azucar':4,'faro-cabo-de-la-vela':2,'ojo-de-agua':3,'dunas-de-taroa':9,'faro-punta-gallinas':1,'bahia-hondita':6,'parque-nacional-natural-macuira':5,'jepirachi':7}.get(ident) if n=='uribia' else ([2,3,4,5,6,8,7][i] if n=='san-andres' else ([2,3,None][i])))
    approved = next((idx for idx, item in enumerate(manifest, 1) if item.get('attraction_slug') == ident and item.get('status') == 'owner_approved_for_dev1'), None)
    num = approved or num
    if num:pt['image']=image(num,{l:tr[l]['name'] for l in locales})
   pois.append(pt)
 biz=[]
 for b in r['businesses']:
  if 'hold' in b.get('status','') or 'candidate' in b.get('status','') or not b.get('source_url'):continue
  cat=b.get('category') or b['categories'][0];cat={'restaurant':'alimentacion','restaurante':'alimentacion','accommodation':'hospedaje','tour_operator':'experiencias','compras':'comercio'}.get(cat,cat)
  biz.append(dict(name=b['name'],category_code=cat,information_status='PUBLICO',summary='',description='',sources=[dict(url=b['source_url'],checked_at='2026-11-04',note='Fuente de la ficha pública.')]))
 research=Path('sources')/(n+'.json');write(root/research,r)
 batch.append(dict(destination=dest,hero_image=image(10 if n=='uribia' else 1,{l:r['name'] for l in locales}),places=pois,business_package=dict(schema_version='business-import-v1',destination_slug=n,businesses=biz),research_file=str(research),research_sha256=digest((root/research).read_bytes()),local_research=dict(municipality_code=r['municipality_code'],department_code=r['department_code'],source_commit=pin)))
write(root/'release-input.json',dict(schema_version='dev1-place-content-v1',environment='dev1',places=batch))
write(root/'business-location-exceptions.json',dict(records=[]))
