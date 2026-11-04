import json,urllib.request,concurrent.futures
from pathlib import Path
b=Path(__file__).resolve().parent;slugs=sorted(p.parent.name for p in (b/'places').glob('*/manifest.json'))
def check(task):
 slug,locale=task;prefix={'es':'','en':'en/','pt-BR':'pt-br/'}[locale];url=f'http://127.0.0.1:8086/{prefix}lugares/{slug}/'
 try:
  with urllib.request.urlopen(url,timeout=15) as r:body=r.read().decode('utf8','replace');return {'slug':slug,'locale':locale,'status':r.status,'bytes':len(body),'has_reason':any(x in body for x in ['Por qué visitar','Why visit','Por que visitar']),'has_business_section':any(x in body.lower() for x in ['negocios','businesses','negócios'])}
 except Exception as e:return {'slug':slug,'locale':locale,'error':str(e)}
with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:out=list(pool.map(check,[(s,l) for s in slugs for l in ['es','en','pt-BR']]))
(b/'evidence/public-pages-dev1-20261104.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'total':len(out),'http_200':sum(x.get('status')==200 for x in out),'reasons_detected':sum(x.get('has_reason',False) for x in out),'errors':[x for x in out if x.get('status')!=200]},ensure_ascii=False))
