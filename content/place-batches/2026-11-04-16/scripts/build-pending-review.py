"""Build compact per-place review pages containing only unapproved current images."""
from pathlib import Path
import json,re,html
b=Path(__file__).resolve().parents[1];seed=json.loads((b/'reviews/consolidated.json').read_text())['review'];out=b/'review-pending';out.mkdir(exist_ok=True);rows=[];total=0
for p in sorted((b/'review-pages').glob('*.html')):
 text=p.read_text();sections=re.findall(r'<section data-review-key="([^"]+)">(.*?)</section>',text,re.S);pending=[(k,s) for k,s in sections if seed.get(k,{}).get('status')!='approved']
 if not pending:continue
 d=json.loads((b/'places'/p.stem/'research.json').read_text());titles=[];body=[]
 for k,s in pending:
  titles.append(re.search(r'<h2>(.*?)</h2>',s).group(1))
  if seed.get(k,{}).get('status')=='changes_requested':s='<p class="notice">Corrección solicitada: '+html.escape(seed[k].get('comment',''))+'</p>'+s
  body.append('<section data-review-key="'+k+'">'+s+'</section>')
 style=re.search(r'<style>(.*?)</style>',text,re.S).group(1);script=re.search(r'<script>(.*?)</script>',text,re.S).group(1)
 page='<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pendientes · '+html.escape(d['name'])+'</title><style>'+style+'</style><header><a href="index.html">← Todas las pendientes</a><h1>'+html.escape(d['name'])+'</h1><p>Versiones actuales sin aprobación registrada. Exporta antes de cambiar de destino.</p><button id="export">Exportar revisión</button><label>Importar <input id="import" type="file" accept="application/json"></label><p id="save-state"></p></header><main>'+''.join(body)+'</main><script>'+script+'</script></html>'
 (out/p.name).write_text(page);total+=len(pending)
 rows.append('<li><a href="'+p.name+'">'+html.escape(d['name'])+' ('+str(len(pending))+')</a><p>'+ ' · '.join(titles)+'</p></li>')
(out/'index.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Imágenes pendientes</title><style>body{max-width:850px;margin:30px auto;padding:20px;font:18px/1.5 system-ui;background:#f5f2ec;color:#24392e}li{background:white;padding:16px;margin:14px 0;border:1px solid #d6ddd7;border-radius:12px}a{color:#245b42;font-weight:bold}ul{list-style:none;padding:0}p{font-size:15px}</style><h1>Imágenes sin aprobación registrada</h1><p>'+str(total)+' versiones actuales. Incluye las nuevas propuestas y la corrección pendiente del muelle de Leticia. Las aprobaciones anteriores no se transfieren a imágenes nuevas.</p><ul>'+''.join(rows)+'</ul></html>')
print('Pending current images:',total)
