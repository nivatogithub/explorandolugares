"""Lote de investigación: centro/bahía por inspeccionar, no activos aprobados."""
from pathlib import Path
import json,subprocess,hashlib,urllib.parse,re
p=Path(__file__).resolve().parent;out=p/'imagenes/componentes-top8-v7/originales';out.mkdir(parents=True,exist_ok=True)
def fetch(url,dest):
 if not dest.exists():
  subprocess.run(['curl','--fail','--location','--retry','1','--retry-delay','3','--max-time','40','--user-agent','ExplorandoLugares reference audit/1.0','--output',str(dest)+'.part',url],check=True)
  Path(str(dest)+'.part').rename(dest)
 return dest
rows=[{'id':'SM-P7-01','title':'Playa Blanca, Santa Marta 2.jpg','source':'https://commons.wikimedia.org/wiki/File:Playa_Blanca,_Santa_Marta_2.jpg','identity':'SantaMarta municipio47001; foto12abril2015RealChunflaiCCBY-SA4, comprobar bahía y relieve con INDETUR antes de elegir.'},{'id':'SM-E7-01','title':'Ciudad Perdida Hike (5479976823).jpg','source':'https://commons.wikimedia.org/wiki/File:Ciudad_Perdida_Hike_(5479976823).jpg','identity':'CaminataCiudadPerdida; resolver autor/licencia y comprobar personas/encuadre, no atribuir grupo a MagicTour.'}]
saved_path=p/'componentes-top8-v7.json'
saved={r['id']:r for r in json.loads(saved_path.read_text())} if saved_path.exists() else {}
for r in rows:
 previous=saved.get(r['id'],{})
 try:
  if 'title' in r:
   url='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode({'action':'query','format':'json','titles':'File:'+r['title'],'prop':'imageinfo','iiprop':'url|size|extmetadata'})
   meta=fetch(url,out/(r['id']+'-commons.json'));info=next(iter(json.loads(meta.read_text())['query']['pages'].values()))['imageinfo'][0];ext=info['extmetadata'];r.update(download_url=info['url'],author=re.sub('<[^>]+>','',ext.get('Artist',{}).get('value','')),license=ext.get('LicenseShortName',{}).get('value'),license_url=ext.get('LicenseUrl',{}).get('value'),source_dimensions=[info['width'],info['height']],photo_date=ext.get('DateTimeOriginal',{}).get('value'))
   if r['license'] not in ['CC BY 2.0','CC BY 3.0','CC BY-SA 3.0','CC BY-SA 4.0','CC BY 4.0','CC0','Public domain']:raise ValueError('Revisar permiso específico antes de descargar: '+str(r['license']))
  file=fetch(r['download_url'],out/(r['id']+'.jpg'));r.update(path=str(file.relative_to(p)),sha256=hashlib.sha256(file.read_bytes()).hexdigest(),status='downloaded_pending_visual_and_identity_review')
 except Exception as e:r.update(status='download_blocked',error=str(e))
 r.update(approval=previous.get('approval',False),generation_authorized=previous.get('generation_authorized',False),consulted_at='2026-10-17',required_sector='destino_y_rol_identificados_sin_duplicar_original')
 for key in ['visual_review','visual_inspected','identity_evidence','license_review','crop_review','actual_dimensions']:
  if key in previous:r[key]=previous[key]
 if previous.get('visual_inspected') and r['status'].startswith('downloaded'):r['status']=previous['status']
(p/'componentes-top8-v7.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
print(json.dumps([{r['id']:r['status']} for r in rows]))
