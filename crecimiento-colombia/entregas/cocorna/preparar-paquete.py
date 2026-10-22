#!/usr/bin/env python3
"""Prepara ZIP solo con imágenes revisadas; nunca fabrica archivos pendientes."""
from pathlib import Path
import hashlib,json,sys,zipfile
root=Path(__file__).resolve().parent
manifest=json.loads((root/'paquete-destino.borrador.json').read_text())
review=root/'revision-imagenes.json'
reviews=json.loads(review.read_text()) if review.exists() else {}
issues=[]
images=[manifest['hero_image']]+[p['image'] for p in manifest['places']]
for img in images:
 file=root/img['file']; evidence=reviews.get(img['file'],{})
 if not file.is_file():issues.append(img['file']+': falta archivo');continue
 digest=hashlib.sha256(file.read_bytes()).hexdigest()
 if evidence.get('sha256')!=digest or evidence.get('identidad_comparada') is not True or not evidence.get('referencia'):issues.append(img['file']+': falta comparación contra referencia para este hash')
 if 'todavía no generado' in img.get('image_creator','') or img.get('image_license','').startswith('Pendiente'):issues.append(img['file']+': actualizar metadatos reales tras generación')
result={'structure_only':True,'images_expected':len(images),'ready_to_package':not issues,'issues':issues,'approval':'not_inferred','production':False}
(root/'verificacion-paquete.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(result,ensure_ascii=False,indent=2))
if issues:sys.exit(2)
with zipfile.ZipFile(root/'paquete-destino-cocorna.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('paquete-destino.json',json.dumps(manifest,ensure_ascii=False,indent=2))
 for img in images:z.write(root/img['file'],img['file'])
