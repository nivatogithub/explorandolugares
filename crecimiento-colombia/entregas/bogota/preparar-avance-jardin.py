"""Overlay reproducible sobre paquete v3 existente; no ejecuta generador ni importa BD."""
from pathlib import Path
import json,hashlib,shutil,zipfile,copy
P=Path(__file__).resolve().parent
r=lambda n:json.loads((P/n).read_text())
h=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
base=r('preprod-v3/paquete-destino.json');out=copy.deepcopy(base)
approval=r('aprobacion-ilustracion-tropicario-v1.json');assert approval['decision']=='approved'
source=P/'imagenes/ia/bogota-tropicario-v1.webp';assert h(source)==approval['approved_hashes'][source.name]
selection=[x['slug'] for x in base['places']];assert len(selection)==8
Q=P/'avance-jardin-aprobado';(Q/'images').mkdir(parents=True,exist_ok=True)
for image in [base['hero_image'],*[x['image'] for x in base['places']]]:shutil.copyfile(P/'preprod-v3'/image['file'],Q/image['file'])
x=next(x for x in out['places'] if x['slug']=='jardin');old=copy.deepcopy(x['image']);target=Q/old['file'];shutil.copyfile(source,target)
alts={'es':'Ilustración del conjunto de invernaderos de vidrio del Tropicario del Jardín Botánico de Bogotá.','en':'Illustration of the glass greenhouse complex at Bogotá Botanical Garden’s Tropicario.','pt-BR':'Ilustração do conjunto de estufas de vidro do Tropicario do Jardim Botânico de Bogotá.'}
captions={'es':'Ilustración generada con IA, aprobada para esta tarjeta. Basada en fotografía CC0 de Camiloskyyy; no es fotografía ni prueba de estado actual.','en':'AI-generated illustration approved for this card. Based on a CC0 photograph by Camiloskyyy; not a photograph or evidence of current conditions.','pt-BR':'Ilustração gerada com IA, aprovada para este cartão. Baseada em fotografia CC0 de Camiloskyyy; não é fotografia nem comprovação do estado atual.'}
x['image']={**old,'image_origin':'ai_generated','image_creator':'Explorando Lugares · OpenAI image_gen','image_source_url':'','image_license':'Ilustración IA; referencia fotográfica CC0, condiciones de salida según proveedor','image_license_url':'','alt':alts,'caption':captions}
assert [x['slug'] for x in out['places']]==selection
assert out['destination']==base['destination'] and out['hero_image']==base['hero_image']
for b,a in zip(base['places'],out['places']):
 if b['slug']!='jardin':assert b==a and h(Q/a['image']['file'])==h(P/'preprod-v3'/b['image']['file'])
 else:assert {k:v for k,v in b.items() if k!='image'}=={k:v for k,v in a.items() if k!='image'}
out['local_research']['notes']=[n for n in base['local_research']['notes'] if 'ninguna aprobación' not in n and 'ilustraciones finales no generadas' not in n]
out['local_research']['notes']+=['Ilustración Jardín aprobada por hash para esta tarjeta; textos y demás imágenes siguen en revisión. No autoriza publicación.','Jardín usa ilustración IA aprobada; demás fotografías son provisionales. Procedencia y aprobación se conservan en sidecars locales.','Orden de ocho conservado provisionalmente mientras se revisa AUDITORIA_TOP8; no ranking aprobado.']
(Q/'paquete-destino.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
Z=P/'paquete-avance-jardin-aprobado.zip'
with zipfile.ZipFile(Z,'w',zipfile.ZIP_DEFLATED) as z:
 for f in [Q/'paquete-destino.json',*[Q/a['file'] for a in [out['hero_image'],*[x['image'] for x in out['places']]]]]:z.write(f,str(f.relative_to(Q)))
report={'scope':'preparacion_local_no_importada','base':'preprod-v3/paquete-destino.json','base_sha256':h(P/'preprod-v3/paquete-destino.json'),'generator_untouched':'preparar-v3.py','selection_preserved':selection,'only_changed_component':'jardin.image y notas de avance veraces; orden/textos de lugares intactos','approved_image_sha256':h(source),'zip_sha256':h(Z),'approval_file':'aprobacion-ilustracion-tropicario-v1.json','metadata_approval_scope':'Aprobación ilustración, no nuevosalt/caption/traducciones ni puestoTOP8','provenance_sidecar':'imagenes/ia/procedencia-tropicario-v1.json','provenance_not_embedded_in_zip_schema2':True,'size_bytes':source.stat().st_size,'limit_300kb_met':source.stat().st_size<=300000,'reason_no_reencode':'Preservar exactamente hash aprobado;358534bytes superaobjetivo300KB pero bajo máximoimportador5MB. Nuevaoptimización requiere revisión delderivado.','production_authorized':False,'database_imported':False,'other_assets_unchanged':True,'notes':'Notas de avance actualizadas para no negar aprobación Jardín; consumidor debe revisar procedencia sidecar antes de importar.'}
(P/'reporte-avance-jardin-aprobado.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(report,ensure_ascii=False,indent=2))
