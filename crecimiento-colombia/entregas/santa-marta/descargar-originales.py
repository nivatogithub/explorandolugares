"""Solo originales Commons con licencias previamente comprobadas; no publica."""
from pathlib import Path
import urllib.request,urllib.parse,hashlib,json
root=Path(__file__).resolve().parent
rows=[('catedral','Catedral-santamarta.jpg','Lombana','3.0'),('taganga','Taganga Sunset.jpg','Tisquesusa','4.0'),('quinta','4 quinta de san pedro.jpg','Santy cardenas','4.0')]
manifest=[]
for slug,title,author,version in rows:
 normalized=title.replace(' ','_');h=hashlib.md5(normalized.encode()).hexdigest();url=f'https://upload.wikimedia.org/wikipedia/commons/{h[0]}/{h[:2]}/'+urllib.parse.quote(normalized)
 path=root/'originales'/f'{slug}.jpg';row=dict(id=slug,place_id='CO-47-santa-marta',original_url=url,source_page='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(title),author=author,license='CC BY-SA '+version,license_url=f'https://creativecommons.org/licenses/by-sa/{version}/',commercial_use_allowed=True,transformations_allowed=True,checked_at='2026-10-16',status='pendiente')
 try:
  if not path.is_file():
   req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugaresDraft/1.0 (licensed editorial research)'})
   with urllib.request.urlopen(req,timeout=30) as response:path.write_bytes(response.read())
  row.update(status='original_descargado',original_path=str(path.relative_to(root)),sha256=hashlib.sha256(path.read_bytes()).hexdigest(),bytes=path.stat().st_size)
 except Exception as e:row['error']=str(e)
 manifest.append(row)
(root/'imagenes.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print([(m['id'],m['status'],m.get('error')) for m in manifest])
