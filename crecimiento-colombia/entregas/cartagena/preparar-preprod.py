"""Package current references for authorized local preprod review only; no DB/network actions."""
from pathlib import Path
import json,hashlib,zipfile,copy
P=Path(__file__).resolve().parent
read=lambda name:json.loads((P/name).read_text())
def save(name,data):(P/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
source=read('paquete-v2.json');package=copy.deepcopy(source)
labels={
 'es':('AVANCE LOCAL · fotografías provisionales','Avance para revisión local. Las fotografías son referencias provisionales: las ilustraciones finales siguen pendientes de aprobación. '),
 'en':('LOCAL PREVIEW · provisional photographs','Local review preview. Photographs are provisional references: final illustrations remain pending approval. '),
 'pt-BR':('PRÉVIA LOCAL · fotografias provisórias','Prévia para revisão local. As fotografias são referências provisórias: as ilustrações finais ainda aguardam aprovação. ')}
assert len(package['places'])<=8
for locale,(short,note) in labels.items():
 package['destination']['translations'][locale]['eyebrow']=short
 package['destination']['translations'][locale]['intro']=note+package['destination']['translations'][locale]['intro']
 for row in package['destination']['sources'][locale]:
  row['label']=row.get('label') or row.get('title') or row['url']
  row['section_key']=row.get('section_key','history')
 for image in [package['hero_image'],*[place['image'] for place in package['places']]]:
  image['caption'][locale]=short+' · '+image['caption'][locale]
  assert len(image['caption'][locale])<=500
for place in package['places']:
 assert place['publication_status']=='draft'
 assert place['image']['image_origin']=='external'
package['local_research']['notes'].extend([
 'Autorización actual: cargar avances incompletos exclusivamente en PREPROD local para observaciones. No autoriza producción.',
 'Este ZIP es una variante de avance local con etiquetas provisionales. No sustituye el paquete editorial ni aprueba sus fotografías finales.',
 'Referencia aprobada por componente no significa aprobación del resultado ilustrado. Hay una portada IA candidata pendiente de aprobación; no está integrada en este ZIP.',
 'Importación serial a cargo del coordinador. Este generador no conecta BD, panel ni contenedor.'])
save('paquete-preprod.json',package)
files=[('paquete-destino.json',(P/'paquete-preprod.json').read_bytes())]+[(image['file'],(P/'imagenes/derivados'/Path(image['file']).name).read_bytes()) for image in [package['hero_image'],*[p['image'] for p in package['places']]]]
with zipfile.ZipFile(P/'paquete-preprod.zip','w',compression=zipfile.ZIP_DEFLATED) as archive:
 for name,content in files:
  info=zipfile.ZipInfo(name,date_time=(2026,10,16,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;archive.writestr(info,content)
report=dict(destination_id='CO-13-cartagena',destination_slug='cartagena',intended_environment='preprod_local_only',status='prepared_not_imported',authority='Usuario autoriza cargar destinos incompletos en preprod local para observar avances; no producción.',source_package='paquete-v2.json',source_package_sha256=hashlib.sha256((P/'paquete-v2.json').read_bytes()).hexdigest(),package='paquete-preprod.zip',package_sha256=hashlib.sha256((P/'paquete-preprod.zip').read_bytes()).hexdigest(),manifest='paquete-preprod.json',locales=['es','en','pt-BR'],selected_sites=[p['slug'] for p in package['places']],sites=8,provisional_photographs=9,generated_illustrations=1,approved_final_illustrations=0,publication_approved=False,database_modified=False,container_started=False,browser_used=False,missing=[
 dict(component='ilustraciones_finales',status='portada_candidata_generada',reason='Una portada IA generada pendiente de aprobación. Ocho imágenes de atractivos aún sin generar.'),
 dict(component='revision_editorial_final',status='pendiente',reason='22 aprobaciones históricas y15 intactas activas conservadas; selección y componentes nuevos/modificados no tienen aprobación final.'),
 dict(component='experiencias_publicas',status='no_integradas',count=len(read('borrador.json')['experiences']),reason='Actividades investigadas y operadores trazables en borrador; fotografías de actividad, permisos y condiciones pendientes. Sin alianzas ni reservas inferidas.'),
 dict(component='directorio_publico',status='no_integrado',count=len(read('directorio.json')),reason='Fichas propuestas en investigación privada, no creación automática de negocios publicados.'),
 dict(component='importacion_preprod',status='coordinador_pendiente',reason='Carga serial PHP/MySQLlocal fuera de esta rama; ningún acceso o cambio BD compartida realizado.'),
 dict(component='pr_remoto',status='pendiente',reason='No push/PR remoto creado desde worker.')],importer_notes=['ZIP schema_version2 para DestinationPackage::readZip y ruta de alta de destino existente.','DestinationImporter::import es importador semilla: requiere tablasdestinations/service_categories/businesses vacías y aborta si haydatos. No usar directamente contra preprod poblado.','Conservar estadosdraft al importar y acceder mediante previsualización local autorizada; no convertir autorización de revisión local en publicación de producción.','sources incluye label/url/section_key para compatibilidad con fuentes del importador; los textos turísticos originales y ledger no se alteran.'])
save('reporte-preprod.json',report)
print('Prepared preprod-only ZIP:8 sites,9 provisional photographs,3 languages; no import performed.')
