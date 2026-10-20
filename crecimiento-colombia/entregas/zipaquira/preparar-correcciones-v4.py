from pathlib import Path
import json, zipfile, hashlib, re
from PIL import Image
p=Path(__file__).resolve().parent
h=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
d=json.loads((p/'paquete-destino-auditoria-tren.json').read_text())
rows=[
 ('tren-sabana','tren-patio-fiel-v4','tren-patio-zipaquira.jpg','https://commons.wikimedia.org/wiki/File:Zipaquira_station_yard.JPG','Nils Öberg','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/',
  'Edición directa de patio ferroviario identificado en Zipaquirá. Conserva cerros, arbolado, edificios industriales, vías y tren. Retira publicidad. Fuente histórica: declarada2007/EXIF2003, sin garantía de material actual.',
  {'es':'Tren turístico en el patio ferroviario de Zipaquirá · ilustración basada en foto histórica','en':'Tourist train in the Zipaquirá railway yard · illustration based on a historical photo','pt-BR':'Trem turístico no pátio ferroviário de Zipaquirá · ilustração baseada em foto histórica'}),
 ('recorrido-historico','urbano-centro-fiel-v4','plaza-remi-2010.jpg','https://commons.wikimedia.org/wiki/File:Zipaquira_-_Place_(1).JPG','Rémi Jouan','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/',
  'Edición directa de fotografía identificada de plaza de los Comuneros. Conserva fachada pétrea, edificio blanco con balcón, eje vial y cerro. Grupo de peatones ilustrativo, no prueba de guía/salida.',
  {'es':'Paseo por el centro de Zipaquirá · ilustración basada en una vista identificada','en':'Walk through central Zipaquirá · illustration based on an identified view','pt-BR':'Passeio pelo centro de Zipaquirá · ilustração baseada em vista identificada'}),
 ('ruta-del-minero','minera-tematica-v4',None,'https://www.catedraldesal.gov.co/atracciones/ruta-del-minero/','Explorando Lugares · generación IA','Ilustración temática independiente','',
  'Descripción oficial acredita picar roca salina. Primer plano temático de manos, herramienta y mineral; NO representación exacta del equipo, recinto, guía o túnel del operador. Sin fotografía de entrada ni arquitectura inventada.',
  {'es':'Extracción de roca salina · ilustración temática, no vista del recinto','en':'Salt-rock extraction · thematic illustration, not a view of the venue','pt-BR':'Extração de rocha salina · ilustração temática, não vista do recinto'})]
assets=[]
for slug,stem,ref,url,author,lic,licurl,limits,alts in rows:
    master=p/'imagenes/maestros'/f'{stem}.png';web=p/'imagenes/web'/f'{stem}.webp'
    im=Image.open(master);im.thumbnail((960,640));im.save(web,'WEBP',quality=82,method=6)
    item=next(x for x in d['places'] if x['slug']==slug)
    item['image'].update(file=f'images/{stem}.webp',image_origin='ai_generated',image_creator=author+' · adaptación ilustrada con IA' if ref else author,image_source_url=url if ref else '',image_license=lic,image_license_url=licurl,alt=alts)
    assets.append(dict(component=slug,master=str(master.relative_to(p)),master_sha256=h(master),web=str(web.relative_to(p)),web_sha256=h(web),web_bytes=web.stat().st_size,dimensions=list(im.size),reference_file=f'referencias-autorizadas/{ref}' if ref else None,reference_sha256=h(p/'referencias-autorizadas'/ref) if ref else None,source_url=url,author=author,license=lic,license_url=licurl,source_used_directly=bool(ref),comparison=limits,human_approved=False))
station=next(x for x in d['places'] if x['slug']=='estacion-tren')
for lang,text in {'es':'Estación patrimonial y llegada del tren turístico desde Bogotá. Consulta las salidas.','en':'Heritage station and arrival point for the tourist train from Bogotá. Check departures.','pt-BR':'Estação histórica e chegada do trem turístico de Bogotá. Consulte as saídas.'}.items():station['translations'][lang]['description']=text
raw=(json.dumps(d,ensure_ascii=False,indent=2)+'\n').encode()
(p/'paquete-destino-correcciones-v4.json').write_bytes(raw)
refs=[d['hero_image']['file']]+[x['image']['file'] for x in d['places']]
with zipfile.ZipFile(p/'paquete-destino-auditoria-tren.zip') as old,zipfile.ZipFile(p/'paquete-destino-correcciones-v4.zip','w',zipfile.ZIP_DEFLATED) as z:
    z.writestr('paquete-destino.json',raw)
    for ref in refs:
        local=p/'imagenes/web'/Path(ref).name
        z.writestr(ref,local.read_bytes() if local.exists() else old.read(ref))
overlay=(p/'runtime/visual-gate.js').read_text().replace("'colegio-gabo', 'museo-quevedo', 'ruta-del-minero', 'recorrido-historico'","'colegio-gabo', 'museo-quevedo'")
(p/'runtime/visual-gate-v4.js').write_text(overlay)
credits=(p/'creditos-referencias-v3.html').read_text()
credits=re.sub(r'<article>.*?</article>',lambda m:'' if 'Turistren_Passenger' in m.group() else m.group(),credits,flags=re.S)
extra=''.join(f'<article><h2>{x["component"]}</h2><p>{x["author"]}. <a href="{x["source_url"]}">Fuente</a>. <a href="{x["license_url"]}">{x["license"]}</a>.</p><p>{x["comparison"]}</p></article>' for x in assets)
credits=credits.replace('</body>',extra+'</body>') if '</body>' in credits else credits+extra
(p/'creditos-referencias-v4.html').write_text(credits)
report=dict(schema_version=1,destination='zipaquira',date='2026-10-20',assets=assets,pending_without_valid_image=['colegio-gabo','museo-quevedo'],package_sha256=h(p/'paquete-destino-correcciones-v4.zip'),human_approved=False,preprod_imported=False,production_changed=False)
(p/'CORRECCIONES_REFERENCIA_V4.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(report,ensure_ascii=False,indent=2))
