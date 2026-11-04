#!/usr/bin/env python3
"""Create a browsable RNT research inventory; these records are not a publication list."""
import collections, html, json
from pathlib import Path
P=Path(__file__).resolve().parent
rnt=json.loads((P/'rnt-lote30-2026.json').read_text())
osm=json.loads((P/'osm-lote30-extraido.json').read_text())
matches=json.loads((P/'lote30-cruce-rnt-osm-completo.json').read_text())['rows']
by_rnt={r['rnt']:r for r in matches}; c=collections.Counter(r['destination_slug'] for r in matches)
slugs=sorted({r['destination_slug'] for r in rnt['businesses']})
h=['<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fuentes de negocios · lote de 30</title><style>body{font:16px system-ui;max-width:1300px;margin:auto;padding:25px;background:#f5f7f4;color:#173b2c}table{border-collapse:collapse;width:100%;background:white}td,th{border-bottom:1px solid #ddd;padding:8px;text-align:left}thead{background:#dceade}input,select{padding:10px;font-size:16px;max-width:100%}.note{padding:15px;background:#fff0cb}a{color:#176443}.scroll{overflow:auto}</style><h1>Fuentes para los 30 lugares</h1>']
h.append(f'<p>Registro turístico: <strong>{rnt["total"]:,}</strong> registros con estado activo en datos de 2026. Cartografía: <strong>{len(osm["businesses"]):,}</strong> candidatos. Coincidencias exactas: <strong>{len(matches)}</strong>.</p>')
h.append('<p class="note">Esta es investigación, no el listado de negocios publicados. Un registro activo en algún periodo de 2026 no garantiza operación hoy; una coincidencia por nombre y municipio no confirma la ubicación exacta. Puede haber registros repetidos, cambios de nombre o negocios fuera de servicio. Las coordenadas y direcciones se conservan para revisión.</p>')
h.append('<p><a href="https://www.datos.gov.co/d/thwd-ivmp">Registro Nacional de Turismo</a> · Cartografía © colaboradores de <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>.</p><label>Municipio <select id="municipio"><option value="">Todos</option>')
for slug in slugs:h.append(f'<option value="{html.escape(slug)}">{html.escape(slug.replace("-"," ").title())} · RNT {rnt["counts"].get(slug,0)} · mapa {osm["counts"].get(slug,0)} · cruces {c[slug]}</option>')
h.append('</select></label> <label>Buscar <input id="buscar" type="search" placeholder="Nombre o categoría"></label> <span id="cuenta"></span><div class="scroll"><table><thead><tr><th>Municipio</th><th>Negocio / RNT</th><th>Categoría en RNT</th><th>Dirección en RNT</th><th>Mapa</th></tr></thead><tbody>')
for r in sorted(rnt['businesses'],key=lambda x:(x['destination_slug'],x.get('razon_social_establecimiento',''))):
 slug=r['destination_slug']; name=r.get('razon_social_establecimiento',''); cat=r.get('sub_categoria') or r.get('categoria',''); address=r.get('direcci_n_comercial_establecimiento',''); match=by_rnt.get(r['codigo_rnt']); link=('<a href="'+html.escape(match['source_map'],quote=True)+'">Coincidencia OSM · punto por confirmar</a>') if match else 'Sin cruce exacto'
 h.append(f'<tr data-municipio="{html.escape(slug,quote=True)}"><td>{html.escape(slug.replace("-"," ").title())}</td><td>{html.escape(name)}<br><small>RNT {html.escape(r["codigo_rnt"])}</small></td><td>{html.escape(cat)}</td><td>{html.escape(address)}</td><td>{link}</td></tr>')
h.append('</tbody></table></div><script>const m=document.querySelector("#municipio"),q=document.querySelector("#buscar"),rows=[...document.querySelectorAll("tbody tr")];function f(){let n=0;for(const r of rows){let show=(!m.value||r.dataset.municipio===m.value)&&r.textContent.toLocaleLowerCase().includes(q.value.toLocaleLowerCase());r.hidden=!show;if(show)n++}document.querySelector("#cuenta").textContent=n+" registros"}m.onchange=f;q.oninput=f;f()</script></html>')
(P/'fuentes-lote30.html').write_text(''.join(h),encoding='utf-8')
print(f'{len(rnt["businesses"])} registros, {len(matches)} cruces')
