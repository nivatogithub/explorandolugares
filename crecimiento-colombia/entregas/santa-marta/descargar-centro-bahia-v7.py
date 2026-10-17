"""Lote de investigación: centro/bahía por inspeccionar, no activos aprobados."""
from pathlib import Path
import json,subprocess,hashlib,urllib.parse,re
p=Path(__file__).resolve().parent;out=p/'imagenes/centro-bahia-v7/originales';out.mkdir(parents=True,exist_ok=True)
def fetch(url,dest):
 if not dest.exists():
  subprocess.run(['curl','--fail','--location','--retry','1','--retry-delay','3','--max-time','40','--user-agent','ExplorandoLugares reference audit/1.0','--output',str(dest)+'.part',url],check=True)
  Path(str(dest)+'.part').rename(dest)
 return dest
rows=[{'id':'SM-H7-01','source':'https://www.pexels.com/it-it/foto/vista-panoramica-del-porto-e-dello-skyline-di-santa-marta-38037048/','download_url':'https://images.pexels.com/photos/38037048/pexels-photo-38037048.jpeg','author':'Juan Camilo Trujillo Botero','license':'Pexels License','license_url':'https://www.pexels.com/license/','identity':'Ficha puerto/skyline SantaMarta; identificar marina, centro y bahía por hitos antes de proponer.'},{'id':'SM-H7-02','title':'Panoramica de Santa Marta.JPG','source':'https://commons.wikimedia.org/wiki/File:Panoramica_de_Santa_Marta.JPG','identity':'Fuente describe vista hacia SantaMarta desde víaRodadero; no equivale a representarRodadero. Foto2009: revisar antigüedad e hitos.'},{'id':'SM-H7-03','title':'Vista de la ciudad desde el morro.jpg','source':'https://commons.wikimedia.org/wiki/File:Vista_de_la_ciudad_desde_el_morro.jpg','identity':'Título vista desdeMorro; verificar ciudad y marco real, no asumir por título.'}]
for r in rows:
 try:
  if 'title' in r:
   url='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode({'action':'query','format':'json','titles':'File:'+r['title'],'prop':'imageinfo','iiprop':'url|size|extmetadata'})
   meta=fetch(url,out/(r['id']+'-commons.json'));info=next(iter(json.loads(meta.read_text())['query']['pages'].values()))['imageinfo'][0];ext=info['extmetadata'];r.update(download_url=info['url'],author=re.sub('<[^>]+>','',ext.get('Artist',{}).get('value','')),license=ext.get('LicenseShortName',{}).get('value'),license_url=ext.get('LicenseUrl',{}).get('value'),source_dimensions=[info['width'],info['height']],photo_date=ext.get('DateTimeOriginal',{}).get('value'))
   if r['license'] not in ['CC BY 3.0','CC BY-SA 3.0','CC BY-SA 4.0','CC BY 4.0','CC0','Public domain']:raise ValueError('Revisar permiso específico antes de descargar: '+str(r['license']))
  file=fetch(r['download_url'],out/(r['id']+'.jpg'));r.update(path=str(file.relative_to(p)),sha256=hashlib.sha256(file.read_bytes()).hexdigest(),status='downloaded_pending_visual_and_identity_review')
 except Exception as e:r.update(status='download_blocked',error=str(e))
 r.update(approval=False,generation_authorized=False,consulted_at='2026-10-17',required_sector='centro_y_bahia_historica_exclusivamente')
(p/'centro-bahia-v7.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
print(json.dumps([{r['id']:r['status']} for r in rows]))
