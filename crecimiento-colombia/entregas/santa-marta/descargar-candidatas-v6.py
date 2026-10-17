from pathlib import Path
import subprocess,json,hashlib,urllib.parse
root=Path(__file__).resolve().parent;folder=root/'imagenes/revision-v6/originales';folder.mkdir(parents=True,exist_ok=True)
rows=[
 dict(id='SM-H6-01',component='hero',title='Santa Marta on Sea Coast in Colombia',author='NEHEMIAS GOMEZ FOTOGRAFIA',date='2023-04-18',source='https://www.pexels.com/photo/santa-marta-on-sea-coast-in-colombia-17149414/',url='https://images.pexels.com/photos/17149414/pexels-photo-17149414.jpeg',license='Pexels License',license_url='https://www.pexels.com/license/',source_dimensions=[4032,3024]),
 dict(id='SM-H6-02',component='hero',title='Sunny Skyline View of Santa Marta, Colombia',author='Felipe Jiménez',date=None,source='https://www.pexels.com/photo/sunny-skyline-view-of-santa-marta-colombia-36868490/',url='https://images.pexels.com/photos/36868490/pexels-photo-36868490.jpeg',license='Pexels License',license_url='https://www.pexels.com/license/',source_dimensions=None),
 dict(id='SM-T6-01',component='tayrona',title='CABO SAN JUAN.jpg',author='Giancarlos1234',date='2020-10-08',license='CC BY-SA 4.0',source_dimensions=[3992,2992]),
 dict(id='SM-T6-02',component='tayrona',title='Mirador del Cabo San Juan de Guía.jpg',author='Santiago Augusto Páez Pulido',date='2017-08-21',license='CC BY-SA 4.0',source_dimensions=[4032,3024]),
 dict(id='SM-T6-03',component='tayrona',title='Cabo San Juan, Colombia.jpg',author='OpenAperture',date='2014-01-05',license='CC BY-SA 3.0',source_dimensions=[3850,2567])]
for x in rows:
 if not x.get('url'):
  n=x['title'].replace(' ','_');md5=hashlib.md5(n.encode()).hexdigest();x['url']='https://upload.wikimedia.org/wikipedia/commons/'+md5[0]+'/'+md5[:2]+'/'+urllib.parse.quote(n);x['source']='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(n);x['license_url']='https://creativecommons.org/licenses/by-sa/'+('3.0/' if '3.0' in x['license'] else '4.0/')
 x.update(consulted_at='2026-10-17',approval=False,generation_authorized=False,downloaded=False)
 f=folder/(x['id']+'.jpg');download_url=x['url'];variant='original'
 if not f.exists():
  result=subprocess.run(['curl','--fail','--location','--max-time','30','--user-agent','Mozilla/5.0','--output',str(f)+'.part',download_url],capture_output=True)
  if result.returncode:
   x['download_error']=result.stderr.decode()[-300:]
   if 'wikimedia' in download_url:
    n=x['title'].replace(' ','_');download_url=download_url.replace('/commons/','/commons/thumb/')+'/1280px-'+urllib.parse.quote(n);variant='official_1280px_variant'
    result=subprocess.run(['curl','--fail','--location','--max-time','20','--output',str(f)+'.part',download_url],capture_output=True)
  if result.returncode:
   print(x['id'],'FAILED',flush=True);continue
  Path(str(f)+'.part').replace(f)
  f.with_suffix('.download.json').write_text(json.dumps(dict(download_url=download_url,variant=variant)))
 if f.with_suffix('.download.json').exists():x.update(json.loads(f.with_suffix('.download.json').read_text()))
 x.update(downloaded=True,path=str(f.relative_to(root)),bytes=f.stat().st_size,sha256=hashlib.sha256(f.read_bytes()).hexdigest())
 print(x['id'],x['bytes'],flush=True)
(root/'candidatas-v6.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
