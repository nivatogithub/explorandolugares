import urllib.request,urllib.parse,hashlib,pathlib,json,re,html
p=pathlib.Path(__file__).resolve().parent/'referencias'
files=[('catedral-plaza','Catedral Nuestra Señora de la Inmaculada Concepción.JPG'),('catedral-fachada','Catedral de Santa Fe de Antioquia-Fachada.jpg'),('plaza','Marco de la plaza. Santa Fe de Antioquia. Colombia.JPG'),('museo-juan','Casa Museo Juan del Corral - 01.jpg'),('santa-barbara','Iglesia de Santa Barbara-Santa Fe de Antioquia.jpg'),('museo-religioso','Museo Arte Religioso-Santa Fe de Antioquia.jpg')]
results=[]
for key,title in files:
 name=title.replace(' ','_'); h=hashlib.md5(name.encode()).hexdigest();url='https://upload.wikimedia.org/wikipedia/commons/'+h[0]+'/'+h[:2]+'/'+urllib.parse.quote(name)
 page='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(name)
 row={'id':key,'title':title,'page_url':page,'file_url':url,'checked_at':'2026-10-20'}
 try:
  req=urllib.request.Request(page,headers={'User-Agent':'ExplorandoLugaresResearch/1.0 (reference metadata audit)'})
  page_data=urllib.request.urlopen(req,timeout=35).read();(p/(key+'.html')).write_bytes(page_data)
  text=page_data.decode();row['license_mentions']=list(dict.fromkeys(re.findall(r'https?://creativecommons.org/(?:licenses|publicdomain)/[^"<>\s]+',text)))
  req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugaresResearch/1.0 (reference audit)'})
  b=urllib.request.urlopen(req,timeout=50).read(); f=p/(key+'.jpg');f.write_bytes(b);row.update(file=f.name,sha256=hashlib.sha256(b).hexdigest(),bytes=len(b),status='downloaded')
 except Exception as e:row.update(status='failed',error=str(e))
 results.append(row);print(key,row['status'],row.get('error',''),flush=True)
 (p/'DESCARGAS.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
