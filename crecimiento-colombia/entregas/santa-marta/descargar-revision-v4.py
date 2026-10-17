from pathlib import Path
import urllib.request,urllib.parse,hashlib,json,concurrent.futures,subprocess
root=Path(__file__).resolve().parent
items=[('portada-morro','Bahia de Santa Marta (el morro).jpg','Patriarboleda','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/'),('tayrona','El Cabo at Tayrona National Park.JPG','Uhkabu','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/'),('rodadero','Vista general de la playa de El Rodadero.jpg','Sheisale','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/'),('minca','View over Minca.jpg','Stoni111121','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/'),('museo-tairona','Casa de la Aduana Santa Marta.jpg','Jdvillalobos','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/'),('historia-aduana','CASA DE LA ADUANA.JPG','KANDU','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/'),('acuario','Acuario el rodadero.jpg','Petruss','CC0 1.0','https://creativecommons.org/publicdomain/zero/1.0/')]
def get(item):
 ident,title,author,license,licenseurl=item
 name=title.replace(' ','_'); md5=hashlib.md5(name.encode()).hexdigest()
 url='https://upload.wikimedia.org/wikipedia/commons/'+md5[0]+'/'+md5[:2]+'/'+urllib.parse.quote(name)
 path=root/'originales'/('v4-'+ident+'.jpg')
 metadata_path=path.with_suffix(".download.json")
 downloaded_url=json.loads(metadata_path.read_text())["downloaded_url"] if metadata_path.exists() else url
 if not path.exists():
  # Official page lists 1280px resolution for each of these JPEG files.
  thumb=url.replace('/commons/','/commons/thumb/')+'/1280px-'+urllib.parse.quote(name)
  downloaded_url=thumb
  result=subprocess.run(['curl','--fail','--location','--retry','1','--retry-delay','2','--max-time','25','--user-agent','Mozilla/5.0','--output',str(path)+'.part',thumb],capture_output=True)
  if result.returncode:raise RuntimeError(ident+': '+result.stderr.decode()[-500:])
  Path(str(path)+'.part').replace(path)
  metadata_path.write_text(json.dumps({'downloaded_url':downloaded_url,'full_original_downloaded':False}))

 return dict(id=ident,source_page='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(name),original_url=url,downloaded_url=downloaded_url,downloaded_variant='original' if downloaded_url==url else 'official_1280px_thumbnail',full_original_downloaded=downloaded_url==url,author=author,license=license,license_url=licenseurl,original_path=str(path.relative_to(root)),sha256=hashlib.sha256(path.read_bytes()).hexdigest(),bytes=path.stat().st_size,checked_at='2026-10-17',approval='pending',purpose='Original licensed reference for local preview; not AI final')
with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool: result=list(pool.map(get,items))
(root/'referencias-v4.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps([{'id':x['id'],'bytes':x['bytes']} for x in result]))
