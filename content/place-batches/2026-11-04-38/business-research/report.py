#!/usr/bin/env python3
import json,html,collections
from pathlib import Path
P=Path(__file__).resolve().parent
x=json.loads((P/'candidates.json').read_text());cov=json.loads((P/'coverage.json').read_text());cats=json.loads((P/'plan.json').read_text())['categories']
head=''.join('<th>'+html.escape(c)+'</th>' for c in cats)
rows=''
for m in cov['municipalities']:
 rows+='<tr><th><a href="#'+m['destination_slug']+'">'+html.escape(m['name'])+'</a></th><td>'+str(m['located_candidates'])+'</td>'+''.join('<td class="'+('gap' if not m['categories'].get(c) else '')+'">'+str(m['categories'].get(c,0))+'</td>' for c in cats)+'</tr>'
sections=''
for m in cov['municipalities']:
 records=[r for r in x['businesses'] if r['destination_slug']==m['destination_slug']]
 sections+='<section id="'+m['destination_slug']+'"><h2>'+html.escape(m['name'])+' · '+str(len(records))+'</h2><table><thead><tr><th>Negocio</th><th>Categoría</th><th>Coordenadas</th><th>Fuente</th><th>Estado</th></tr></thead><tbody>'
 for r in records:
  sections+='<tr><td>'+html.escape(r['name'])+'</td><td>'+r['category_code']+'</td><td>'+f"{r['latitude']:.7f}, {r['longitude']:.7f}"+'</td><td><a target="_blank" rel="noopener" href="'+html.escape(r['source_url'],quote=True)+'">Ver ficha cartográfica</a></td><td>Pendiente de contraste</td></tr>'
 sections+='</tbody></table></section>'
p='''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Negocios · cobertura de los 38 municipios</title><style>body{font:16px system-ui;margin:32px;color:#172e26;background:#f7f8f5}h1{font-size:30px}table{border-collapse:collapse;background:white;width:100%;margin:20px 0}th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd;font-size:14px}thead{background:#dce8df}.gap{background:#fff0d2}section{margin-top:44px}a{color:#176144}.scroll{overflow:auto}.notice{padding:20px;background:#fff0d2}input{padding:12px;width:min(95%,500px)}section[hidden]{display:none}</style><h1>Negocios de los 38 municipios</h1>'''
p+='<p>Meta: 1.300–1.500 negocios. Investigación: 4 de noviembre de 2026.</p><div class="notice"><strong>'+str(x['candidate_count'])+' candidatos con coordenadas y municipio delimitado.</strong> No son fichas aprobadas para publicar: falta contrastar identidad, ubicación y vigencia. Los 112 negocios iniciales se conservan aparte; no se suman para evitar duplicados.</div><h2>Cobertura por municipio y categoría</h2><div class="scroll"><table><thead><tr><th>Municipio</th><th>Total</th>'+head+'</tr></thead><tbody>'+rows+'</tbody></table></div><h2>Inventario localizado</h2><label>Filtrar municipio o negocio <input id="q" type="search"></label>'+sections+'<footer>Datos cartográficos © OpenStreetMap contributors · <a href="https://www.openstreetmap.org/copyright">ODbL 1.0</a>. Los puntos de polígonos representan el establecimiento, no su entrada.</footer><script>document.getElementById("q").addEventListener("input",function(){let q=this.value.toLocaleLowerCase();document.querySelectorAll("section").forEach(s=>{s.hidden=!s.textContent.toLocaleLowerCase().includes(q)})})</script></html>'
(P/'index.html').write_text(p)
print('Review page written')
