from pathlib import Path
import json, zipfile, hashlib
p=Path(__file__).resolve().parent
d=json.loads((p/'paquete-destino.json').read_text())
provenance=json.loads((p/'PROCEDENCIA_REFERENCIAS_V3.json').read_text())
assets={x['component']:x for x in provenance['corrected_assets']}
for item in [dict(slug='hero', image=d['hero_image'])]+d['places']:
    if item['slug'] in assets:
        item['image'].update(assets[item['slug']]['public_metadata'])
descriptions={
'ruta-del-minero':{'es':'Pica roca salina en un recorrido guiado. Aplican restricciones de edad y salud.','en':'Try salt-rock mining on a guided visit. Age and health restrictions apply.','pt-BR':'Experimente a extração de sal em visita guiada. Há restrições de idade e saúde.'},
'tren-sabana':{'es':'Viaja desde Bogotá hasta Zipaquirá en un tren patrimonial. Consulta las salidas.','en':'Travel from Bogotá to Zipaquirá on a heritage train. Check departures.','pt-BR':'Viaje de Bogotá a Zipaquirá em um trem histórico. Consulte as saídas.'},
'recorrido-historico':{'es':'Conoce el centro histórico con guía y previa inscripción. Confirma la salida.','en':'Explore the historic centre with a guide and prior registration. Confirm departure.','pt-BR':'Conheça o centro histórico com guia e inscrição prévia. Confirme a saída.'}}
for item in d['places']:
    if item['slug'] in descriptions:
        for lang, text in descriptions[item['slug']].items():item['translations'][lang]['description']=text
    if item['slug']=='tren-sabana':
        item['image']['alt']={'es':'Tren turístico en recorrido desde Bogotá · ilustración sobre una fotografía histórica','en':'Sabana tourist train on a journey from Bogotá · illustration based on a historical photograph','pt-BR':'Trem turístico em percurso desde Bogotá · ilustração baseada em fotografia histórica'}
d['local_research']['notes'].append('Auditoría 2026-10-20: tren ilustrado desde foto colombiana histórica, sector no geolocalizado; no garantiza locomotora ni paisaje de una salida actual. Experiencias priorizadas por identidad salinera, transporte patrimonial y complemento urbano; sin demanda medida.')
raw=(json.dumps(d,ensure_ascii=False,indent=2)+'\n').encode()
(p/'paquete-destino-auditoria-tren.json').write_bytes(raw)
with zipfile.ZipFile(p/'paquete-destino.zip') as src, zipfile.ZipFile(p/'paquete-destino-auditoria-tren.zip','w',zipfile.ZIP_DEFLATED) as dst:
    names=src.namelist()
    manifest=[n for n in names if n.endswith('.json') and '/' not in n]
    assert len(manifest)==1,manifest
    for name in names:dst.writestr(name,raw if name==manifest[0] else src.read(name))
print(hashlib.sha256((p/'paquete-destino-auditoria-tren.zip').read_bytes()).hexdigest())
