import concurrent.futures,html,json,pathlib,re,urllib.request
root=pathlib.Path(__file__).resolve().parents[1]
data=json.loads((root/'places/san-andres/research.json').read_text())
urls=sorted({b['source_url'] for b in data['businesses']}|{'https://bluelifedive.com/contacto/','https://www.hotelcasablancasanandres.com/es/ubicacion','https://www.decameron.com/es/hoteles/san-andres-islas/decameron-isleno/','https://www.decameron.com/es/hoteles/san-andres-islas/decameron-maryland/','https://www.decameron.com/es/hoteles/san-andres-islas/decameron-los-delfines/'})
def fetch(u):
 r={'url':u,'checked_at':'2026-11-04'}
 try:
  response=urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':'Mozilla/5.0'}),timeout=18)
  s=response.read().decode(errors='replace');r.update(status=response.status,final_url=response.url,title=re.findall(r'<title[^>]*>(.*?)</title>',s,re.S)[:1])
  r['map_links']=sorted(set(re.sub(r'([?&])key=[^&]*',r'\1key=REDACTED',html.unescape(a)) for a in re.findall(r'(?:href|src)=[\"\']([^\"\']+)',s) if 'maps.googleapis.com/maps/api/js' not in a and any(v in a for v in ['google.com/maps','goo.gl/maps','maps.app.goo.gl','maps.google','waze.com'])))
  r['geo_snippets']=list(dict.fromkeys(re.findall(r'.{0,50}(?:latitude|longitude|latitud|longitud|data-lat|data-lng|map_lat|map_lng).{0,120}',s,re.I)))[:20]
 except Exception as e:r['error']=str(e)
 return r
with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool: results=list(pool.map(fetch,urls))
(root/'business-source-audit.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
for r in results:
 print(r['url'],r.get('status',r.get('error')))
 for v in r.get('map_links',[]):print(v[:550])
 for v in r.get('geo_snippets',[]):print(v[:250])
