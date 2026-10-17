"""Download only explicitly licensed original candidates; no publishing or generation."""
from pathlib import Path
import urllib.request,urllib.parse,json,re,hashlib,html
P=Path(__file__).resolve().parent/'imagenes'/'candidatas-v3';P.mkdir(exist_ok=True)
candidates=[('centro-aereo','Cartagena - Colombia.jpg'),('centro-panorama','Cartagena Ciudad Amurallada.jpg'),('san-felipe-panorama','Panoramic view Castillo San Felipe de Barajas CTG 11 2019 0093.jpg'),('getsemani-carretero','Calle de Carretero, Getsemaní, Cartagena.jpg'),('getsemani-guerrero','Calle del Guerrero, Getsemaní, Cartagena.jpg'),('rosario-archipielago','Archipielago Islas del Rosario.jpg'),('boquilla-playa','Playa La Boquilla, Cartagena.jpg')]
headers={'User-Agent':'ExplorandoLugares/1.0 (editorial image review; non-automated publication)'}
def fetch(url):return urllib.request.urlopen(urllib.request.Request(url,headers=headers),timeout=40).read()
rows=[]
for key,title in candidates:
 try:
  params=urllib.parse.urlencode(dict(action='query',format='json',prop='imageinfo',iiprop='url|extmetadata',titles='File:'+title))
  page=next(iter(json.loads(fetch('https://commons.wikimedia.org/w/api.php?'+params))['query']['pages'].values()));info=page['imageinfo'][0];meta=info['extmetadata'];value=lambda n:html.unescape(re.sub('<[^>]+>','',meta.get(n,{}).get('value','')))
  license=value('LicenseShortName')
  assert license.startswith(('CC BY','CC0','Public domain')),license
  raw=fetch(info['url']);path=P/(key+'.jpg');path.write_bytes(raw)
  row=dict(id=key,title=title,file=str(path.relative_to(P.parent.parent)),source_page=info['descriptionurl'],original_url=info['url'],author=value('Artist'),license=license,license_url=value('LicenseUrl'),description=value('ImageDescription'),photographed_at=value('DateTimeOriginal'),sha256=hashlib.sha256(raw).hexdigest(),consulted_at='2026-10-17',approved=False,status='pending_visual_review')
  rows.append(row);print(key,license,len(raw))
 except Exception as e:rows.append(dict(id=key,error=str(e)));print(key,type(e).__name__,str(e))
(P/'manifest.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
