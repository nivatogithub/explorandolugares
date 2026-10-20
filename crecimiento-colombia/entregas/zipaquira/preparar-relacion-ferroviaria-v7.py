from pathlib import Path
import json,zipfile,hashlib
p=Path(__file__).resolve().parent
base=p.parents[2].parent/'preprod/.local/preprod/worker-review/lote-20261019/zipaquira.zip'
h=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
base_hash=h(base)
with zipfile.ZipFile(base)as z:
    d=json.loads(z.read('paquete-destino.json'))
changes={
 'estacion-tren':{
  'es':'Aquí llega el tren turístico desde Bogotá. Conoce el edificio y consulta la experiencia ferroviaria.',
  'en':'Arrival station for the tourist train from Bogotá. Explore the building and its railway experience.',
  'pt-BR':'Aqui chega o trem turístico de Bogotá. Conheça o edifício e a experiência ferroviária.'},
 'tren-sabana':{
  'es':'Viaja desde Bogotá y llega a la estación de Zipaquirá. Consulta salidas y condiciones.',
  'en':'Travel from Bogotá to Zipaquirá station on a heritage train. Check departures and conditions.',
  'pt-BR':'Viaje de Bogotá até a estação de Zipaquirá em trem histórico. Consulte saídas e condições.'}}
patch=[]
for slug,translations in changes.items():
    item=next(x for x in d['places']if x['slug']==slug)
    for language,text in translations.items():
        assert len(text.split())<=18
        item['translations'][language]['description']=text
    patch.append({'slug':slug,'translations':{language:{'description':text}for language,text in translations.items()}})
d['local_research']['notes'].append('Relación estación–tren verificada en itinerario propio de Turistren el2026-10-20; punto de llegada y regreso Estación del Tren de Zipaquirá. No implica acceso a vías, andén concreto o boleto incluido en visita al edificio.')
raw=(json.dumps(d,ensure_ascii=False,indent=2)+'\n').encode()
(p/'paquete-destino-relacion-v7.json').write_bytes(raw)
with zipfile.ZipFile(base)as old,zipfile.ZipFile(p/'paquete-destino-relacion-v7.zip','w',zipfile.ZIP_DEFLATED)as out:
    for name in old.namelist():out.writestr(name,raw if name=='paquete-destino.json'else old.read(name))
assert base_hash==h(base),'Stage changed during preparation'
with zipfile.ZipFile(base)as before,zipfile.ZipFile(p/'paquete-destino-relacion-v7.zip')as after:
    assert all(before.read(n)==after.read(n)for n in before.namelist()if n!='paquete-destino.json')
    old=json.loads(before.read('paquete-destino.json'))
    assert d['hero_image']==old['hero_image']
    assert all(a['image']==b['image']for a,b in zip(d['places'],old['places']))
report={'schema_version':1,'destination':'zipaquira','date':'2026-10-20','base_stage_zip':str(base),'base_sha256':base_hash,'candidate_sha256':h(p/'paquete-destino-relacion-v7.zip'),'places':patch,'runtime_asset':'runtime/place-relations.js','runtime_sha256':h(p/'runtime/place-relations.js'),'image_bytes_unchanged':True,'image_metadata_unchanged':True,'approved_colegio_quevedo_unchanged':True,'source_urls':['https://turistren.com.co/','https://turistren.com.co/planifica-tu-viaje/','https://mapcarta.com/es/N761227000'],'evidence':'Itinerario oficial enumera Estación del Tren de Zipaquirá como llegada y partida del regreso. NodoOSM5.02164,-74.00115 corrobora estación y operador, sin certificar geometría del andén.','public_links':'Solo anclas internas recíprocas, icono ferroviario SVG reutilizable; sin marca comercial, reserva o nueva pestaña.','preprod_imported':False,'production_changed':False,'station_component_human_approved':False,'integration_note':'Aplicar textos selectivamente y cargar runtime. Conservar recorteCSSV6 y ajustes del coordinador; imágenes y aprobaciones no se tocan.'}
(p/'PATCH_RELACION_FERROVIARIA_V7.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'base_sha256':base_hash,'candidate_sha256':report['candidate_sha256'],'runtime_sha256':report['runtime_sha256'],'image_bytes_unchanged':True},ensure_ascii=False))
