"""Create reversible private preprod package; no database/network/publication."""
from pathlib import Path
import json, zipfile, hashlib, subprocess
r=Path(__file__).resolve().parent
def read(n):return json.loads((r/n).read_text())
def save(n,v):(r/n).write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
assets=read('imagenes.json');content=read('contenido.json')
def image(file):
 a=next(a for a in assets if a['file']==file)
 return dict(file=file,image_origin='external',image_creator=a['author'],image_source_url=a['source_url'],image_license=a['license'],image_license_url=a['license_url'],alt={l:t['alt'] for l,t in a['translations'].items()},caption={l:t['caption'] for l,t in a['translations'].items()})
pkg=read('paquete-destino-v2.borrador.json')
pkg['destination']['destination_type']='city'
pkg['hero_image']=image('images/coltejer-2006.webp')
# Two real references only; no unrelated photograph to fill eight slots.
mapping={'coltejer':'images/coltejer-2013.webp','jardin-botanico':'images/medellin-3.webp'}
pkg['places']=[]
for slug,file in mapping.items():
 p=next(p for p in content['places'] if p['slug']==slug)
 pkg['places'].append(dict(slug=slug,type='place',publication_status='draft',translations=p['translations'],image=image(file)))
pkg['local_research']['notes']=['Carga provisional local expresamente autorizada, no aprobación editorial/publicación. Dos atractivos de28; resto excluido por falta de foto específica. Fotografías técnicas provisionales, sin IA final. Portada Coltejer2006 histórica640x480, reversible, no aprobada. Actividades separadas y sin importación en esta muestra.']
save('paquete-preprod.json',pkg)
files=[pkg['hero_image']['file']]+[p['image']['file'] for p in pkg['places']]
assert len(files)==len(set(files)) and 1<=len(pkg['places'])<=8
zip_path=r/'paquete-preprod.zip'
with zipfile.ZipFile(zip_path,'w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('paquete-destino.json',json.dumps(pkg,ensure_ascii=False,indent=2)+'\n')
 for file in files:z.write(r/file,file)
with zipfile.ZipFile(zip_path) as z:
 assert z.testzip() is None and set(z.namelist())=={'paquete-destino.json',*files}
excluded=[dict(slug=p['slug'],name=p['translations']['es']['name'],reason='Falta referencia específica autorizada apta; se mantiene en inventario amplio, no se omite investigación.') for p in content['places'] if p['slug'] not in mapping]
save('reporte-preprod.json',dict(destination_id='CO-05-medellin',branch='lugar/CO/antioquia/medellin',target='preproduccion_local_privada',authorized_scope='cargar avances locales para revisión; no producción ni aprobación editorial',schema_version=2,zip='paquete-preprod.zip',zip_sha256=hashlib.sha256(zip_path.read_bytes()).hexdigest(),imported_by_worker=False,database_touched=False,production_touched=False,editorial_approved=False,cover=dict(file=files[0],status='provisional_reversible_no_aprobada',limitation='Coltejer2006 histórico,640x480 sin ampliar'),main_places_in_package=list(mapping),main_place_count=len(mapping),broad_inventory_proposed_count=len(content['places']),excluded_count=len(excluded),exclusions=excluded,activities=dict(included_in_package=0,reason='Separadas; referencias de servicio y traducciones ampliadas pendientes; no importar como ofertas activas.'),photographic_references=[dict(file=f,sha256=hashlib.sha256((r/f).read_bytes()).hexdigest()) for f in files],validation=dict(python_zip='passed',php_real_importer='pending',database_import='pending_coordinator'),limitations=['Solo2tarjetas listas de28, máximo8no cuota obligatoria.','No generar ilustraciones antes aprobación referencias/textos.','Pendientes revisión humana por idioma, licencias/usos, fotos restantes, integración visual real y segunda revisión.']))
print('ZIP privado preparado: portada provisional y2atractivos con imagen;26excluidos por faltantes. Sin accesoDB/publicación.')
importer=r.parents[3]/'preprod/backend/app/DestinationPackage.php'
code='require $argv[1]; $p=DestinationPackage::readZip($argv[2]); echo json_encode(["php_version"=>PHP_VERSION,"places"=>count($p["manifest"]["places"]),"images"=>count($p["images"])]);'
result=subprocess.run(['php','-r',code,str(importer),str(zip_path)],capture_output=True,text=True,check=True)
report=read('reporte-preprod.json')
report['validation']['php_real_importer']=dict(status='passed',**json.loads(result.stdout),importer_sha256=hashlib.sha256(importer.read_bytes()).hexdigest(),note='CLI PHP local8.5; validar también PHP/MySQL de preprod por coordinador, sin invocar contenedores niBD desde worker.')
save('reporte-preprod.json',report)
print('PHP real readZip: passed; sin escrituraBD.')
