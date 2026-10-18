"""Usar únicamente tras descargar el original licenciado. No genera imágenes ficticias."""
from pathlib import Path
import hashlib,json
from PIL import Image,ImageOps,__version__
p=Path(__file__).parent
src=p/'imagenes/originales/monserrate-bogota.jpg'
if not src.exists(): raise SystemExit('BLOQUEADO: no hay original descargado. No se crearon derivados.')
b=src.read_bytes()
if hashlib.sha1(b).hexdigest()!='3d8805032ba1a3f071cfdbb4e5a5cce21e294573': raise SystemExit('Original distinto de ficha consultada: revalidar licencia/procedencia.')
Image.MAX_IMAGE_PIXELS=40000000
with Image.open(src) as raw:
 if raw.format!='JPEG': raise SystemExit('MIME inesperado')
 raw.verify()
with Image.open(src) as raw:
 image=ImageOps.exif_transpose(raw).convert('RGB')
 outputs=[]
 for role,width,budget in [('portada',1600,300000),('tarjeta',800,120000)]:
  out=image.copy();out.thumbnail((width,4000))
  path=p/f'imagenes/derivados/monserrate-{role}.webp';path.parent.mkdir(parents=True,exist_ok=True)
  out.save(path,'WEBP',quality=82,method=6,exif=b'')
  data=path.read_bytes()
  outputs.append({'archivo':str(path.relative_to(p)),'dimensiones':out.size,'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest(),'presupuesto_objetivo':budget,'dentro_presupuesto':len(data)<=budget,'calidad':82,'recorte':'ninguno; proporcion original','herramienta':'Pillow '+__version__,'licencia':'CC BY-SA 4.0','cambios':'orientacion RGB redimensionado WebP sin metadatos personales','revision_visual':'pendiente'})
manifest=json.loads((p/'imagenes.json').read_text());asset=manifest['activos'][0]
asset.update(original_local=str(src.relative_to(p)),sha256=hashlib.sha256(b).hexdigest(),derivados=outputs)
manifest['estado']='derivados_pendientes_qa_y_mas_fotos'
(p/'imagenes.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print('Derivados preparados, revisar al 100%, móvil/escritorio y crédito antes de aprobar; regenerar manifiesto de entrega.')
