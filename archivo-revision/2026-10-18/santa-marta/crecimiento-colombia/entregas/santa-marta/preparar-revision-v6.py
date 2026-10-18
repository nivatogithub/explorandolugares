from pathlib import Path
from PIL import Image,ImageOps,ImageDraw
import json,hashlib
r=Path(__file__).resolve().parent;out=r/'imagenes/revision-v6/revision';out.mkdir(parents=True,exist_ok=True)
d=json.loads((r/'candidatas-v6.json').read_text())
notes={
'SM-H6-01':('condicional_sector_rodadero','El Rodadero / bahía de Gaira. Torres, playa y cerros identificables; no centro histórico. Mejor dirección visual que Camellón, pero edificios de primer plano ocupan bastante espacio y bases laterales ya cortadas en el original. No aprobar como vista del centro.'),
'SM-H6-02':('descartada','Vista vertical con mucho cielo, edificios de primer plano cortados y mar en franja estrecha. No cumple el encargo de tejido urbano completo y costa dominante. No proponer como alternativa buena.'),
'SM-T6-01':('recomendada_para_eleccion','Vista de dron con quiosco de palma completo en rocas, arena, agua turquesa y palmeras. Excelente identificación de Cabo San Juan. En recortes centrados el quiosco se conserva; primera opción.'),
'SM-T6-02':('descartada','Quiosco pequeño, luz gris y bastante cielo/agua sin detalle; foto auténtica pero inferior a las otras candidatas. No proponer para elegir solo por tener licencia.'),
'SM-T6-03':('alternativa_para_eleccion','Quiosco grande y reconocible, rocas y playa con agua turquesa. Perspectiva a nivel de tierra; personas ocupan parte derecha inferior. Buena alternativa al dron, revisar preferencia del usuario.')}
for x in d:
 if not x.get('downloaded'):continue
 im=ImageOps.exif_transpose(Image.open(r/x['path'])).convert('RGB');x['actual_dimensions']=list(im.size);x['sector_identification']='El Rodadero / bahía de Gaira, identificación visual por bahía y perfil urbano' if x['id']=='SM-H6-01' else ('Cabo San Juan de Guía, PNN Tayrona' if x['component']=='tayrona' else 'Santa Marta según ficha; sector exacto no comprobado, descartada')
 x['quality_status'],x['visual_review']=notes[x['id']]
 x['approved_reference']=False;x['generation_authorized']=False
 x['preview_roles']=['Original proporcional','Recorte ancho centrado','Recorte móvil centrado']
 full=im.copy();full.thumbnail((1200,1000));full.save(out/(x['id']+'-original.webp'),'WEBP',quality=88)
 x['review_original']=str((out/(x['id']+'-original.webp')).relative_to(r))
 sizes=[(600,330),(342,240)] if x['component']=='hero' else [(560,380),(342,240)]
 paths=[]
 for label,size in zip(['desktop','mobile'],sizes):
  crop=ImageOps.fit(im,size,centering=(.5,.5));p=out/(x['id']+'-'+label+'.webp');crop.save(p,'WEBP',quality=88);paths.append(str(p.relative_to(r)))
 x['review_crops']=paths
 x['crop_basis']='Simulación técnica object-fit:cover centrado: hero600×330/342×240; tarjeta560×380/342×240. No es evidencia de integración en navegador.'
 x['ia_conditions']='Adaptación comercial con atribución, enlace, cambios y licencia compatible de derivado CC BY-SA; no elimina derechos adicionales ni aprobación humana.' if 'CC BY-SA' in x['license'] else 'LicenciaPexels permite uso/modificación comercial sujeto a términos, derechos adicionales y sin insinuar patrocinio. Ilustración puntual, no entrenamiento; registrar proveedor/resultado al generar.'
 # Diagnostic comparison sheet, no aesthetic edits to source.
 sheet=Image.new('RGB',(1000,640),'#fff8ed');dr=ImageDraw.Draw(sheet);dr.text((20,15),x['id']+' | '+x['quality_status'],fill='#172b42');dr.text((20,40),'ORIGINAL (proporcional)   /   RECORTE ANCHO   /   MOVIL',fill='#172b42')
 a=im.copy();a.thumbnail((460,500));sheet.paste(a,(20,80));wide=Image.open(r/paths[0]);wide.thumbnail((480,280));sheet.paste(wide,(500,80));mob=Image.open(r/paths[1]);sheet.paste(mob,(500,380));sheet.save(out/(x['id']+'-comparacion.jpg'),quality=91)
 x['comparison']=str((out/(x['id']+'-comparacion.jpg')).relative_to(r))
(r/'candidatas-v6.json').write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
print([(x['id'],x.get('quality_status'),x.get('actual_dimensions')) for x in d])
