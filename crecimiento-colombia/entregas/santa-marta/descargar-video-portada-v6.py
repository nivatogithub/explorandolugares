"""Descarga original licenciado para inspección; no genera ni integra activos."""
from pathlib import Path
import subprocess,json,hashlib
p=Path(__file__).resolve().parent
out=p/'imagenes/revision-v6/originales/SM-H6-05.mp4'
url='https://videos.pexels.com/video-files/31559774/13450374_2560_1440_60fps.mp4'
if not out.exists():
 subprocess.run(['curl','--fail','--location','--retry','1','--retry-delay','3','--max-time','40','--output',str(out)+'.part',url],check=True)
 Path(str(out)+'.part').rename(out)
meta={'id':'SM-H6-05','component':'hero','source':'https://www.pexels.com/video/aerial-view-of-santa-marta-s-coastal-skyline-31559774/','download_url':url,'author':'Juan Camilo Trujillo Botero','license':'Pexels License','license_url':'https://www.pexels.com/license/','source_date':'2025-04-09','consulted_at':'2026-10-17','media_type':'video_original','source_description':'Drone footage capturing Santa Marta coastline and urban skyline along Caribbean Sea','status':'downloaded_pending_visual_identification','path':str(out.relative_to(p)),'bytes':out.stat().st_size,'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'reference_approved':False,'generation_authorized':False}
(p/'video-portada-v6.json').write_text(json.dumps(meta,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'downloaded':str(out),'bytes':out.stat().st_size}))
