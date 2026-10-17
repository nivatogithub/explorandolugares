from pathlib import Path
import subprocess,json,hashlib
p=Path(__file__).resolve().parent
url='https://upload.wikimedia.org/wikipedia/commons/5/53/Fincas_cafeteras_-_Sierra_Nevada_-_panoramio.jpg'
out=p/'imagenes/experiencias-v6/originales/SM-E6-07.jpg'
if not out.exists():
 subprocess.run(['curl','--fail','--location','--retry','1','--retry-delay','3','--max-time','40','--output',str(out)+'.part',url],check=True)
 Path(str(out)+'.part').rename(out)
m={'id':'SM-E6-07','experience':'minca-cafe','source':'https://commons.wikimedia.org/wiki/File:Fincas_cafeteras_-_Sierra_Nevada_-_panoramio.jpg','original_url':url,'author':'diego_cue','license':'CC BY-SA 3.0','license_url':'https://creativecommons.org/licenses/by-sa/3.0/','date':'2015-01-06','source_dimensions':[5184,3456],'source_identity':'Fincas cafeteras Sierra Nevada; tags Minca/SantaMarta y coordenadas11.134361,-74.107761. No identifica finca del operador ni operación actual.','license_evidence':'Ficha Commons confirma revisión Panoramio22mayo2017, CC BY-SA3; atribución, cambios y compartirigual.','path':str(out.relative_to(p)),'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'status':'downloaded_pending_visual_review','approval':False,'generation_authorized':False}
(p/'cafe-candidata-v6.json').write_text(json.dumps(m,ensure_ascii=False,indent=2)+'\n')
print(str(out))
