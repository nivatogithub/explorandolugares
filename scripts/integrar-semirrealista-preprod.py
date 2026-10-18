"""Integración local de las tres propuestas. Solo archivos de preprod; BD usa el script PHP guardado por separado.
Requiere worktrees worker-lugar-2/3/4 y preprod bajo el mismo directorio trabajo.
No reemplaza la plantilla completa ni realiza publicación.
"""

from pathlib import Path
import shutil
R=Path(__file__).resolve().parents[2];P=R/'preprod';S=P/'backend/integration-medicion/public/destino.php'
s=S.read_text()
for worker,slug in [('worker-lugar-2','bogota'),('worker-lugar-4','santa-marta')]:
 source=R/worker/'backend/public/assets'
 for target in [P/'backend/public/assets',P/'.local/preprod/release/public/assets']:
  for ext in ['js','css']:shutil.copyfile(source/(slug+'-compacta.'+ext),target/(slug+'-compacta.'+ext))
  shutil.copytree(source/(slug+'-semirrealista'),target/(slug+'-semirrealista'),dirs_exist_ok=True)
 marker='<!-- destino-semirrealista-'+slug+' -->'
 if marker not in s:
  hook=marker+"<?php if($preview && $slug==='"+slug+"'):?><link rel=\"stylesheet\" href=\"/assets/"+slug+"-compacta.css?v=1\"><script defer src=\"/assets/"+slug+"-compacta.js?v=1\"></script><?php endif;?>"
  s=s.replace('</body>',hook+'</body>')
S.write_text(s)
r=P/'.local/preprod/release/public/destino.php';rs=r.read_text()
for slug in ['bogota','santa-marta']:
 marker='<!-- destino-semirrealista-'+slug+' -->'
 if marker not in rs:
  hook=s.split(marker)[1].split('</body>')[0]
  # Inject only this hook, not subsequent city hooks.
  hook=hook.split('<!-- destino-semirrealista-')[0]
  rs=rs.replace('</body>',marker+hook+'</body>')
r.write_text(rs)
shutil.copyfile(R/'worker-lugar-4/crecimiento-colombia/entregas/santa-marta/semirrealista-v1/contenido-cierre-semirrealista-v1.json',P/'.local/preprod/worker-review/santa-marta-semirrealista-v1.json')
print('Two city assets integrated; source and prepared release preserved.')


from pathlib import Path
import shutil,json,hashlib
R=Path(__file__).resolve().parents[2];D=R/'worker-lugar-3/crecimiento-colombia/entregas/medellin/semirrealista-v1';P=R/'preprod'
for A in [P/'backend/public/assets',P/'.local/preprod/release/public/assets']:
 for ext in ['js','css']:shutil.copyfile(D/('medellin-compacta.'+ext),A/('medellin-compacta.'+ext))
 shutil.copytree(D/'web',A/'medellin-semirrealista/web',dirs_exist_ok=True)
for f in [P/'backend/integration-medicion/public/destino.php',P/'.local/preprod/release/public/destino.php']:
 s=f.read_text();marker='<!-- destino-semirrealista-medellin -->'
 if marker not in s:s=s.replace('</body>',marker+'''<?php if($preview && $slug==='medellin'):?><link rel="stylesheet" href="/assets/medellin-compacta.css?v=1"><script defer src="/assets/medellin-compacta.js?v=1"></script><?php endif;?></body>''')
 f.write_text(s)
f=P/'.local/preprod/worker-review/medellin-semirrealista-v1.json';shutil.copyfile(D/'integracion-medellin.json',f);print(hashlib.sha256(f.read_bytes()).hexdigest())


from pathlib import Path
import json
R=Path(__file__).resolve().parents[2];P=R/'preprod';result={}
def media(path,name,hero=False):return dict(path='assets/'+path,alt='Ilustración editorial: '+name,origin='ai_generated',width=1200 if hero else 800,height=800 if hero else 533,caption='')
b=json.loads((R/'worker-lugar-2/crecimiento-colombia/entregas/bogota/semirrealista-v1/contenido.json').read_text());result['bogota']={'hero':media('bogota-semirrealista/portada-v4-1200.webp','Bogotá · Torre Colpatria',True),'places':{}}
for p in b['places']:
 key='bogota-'+p['slug'];path=key+'-800.webp' if key!='bogota-jardin' else 'bogota-jardin-aprobado.webp';result['bogota']['places'][p['slug']]=media('bogota-semirrealista/'+path,p['translations']['es']['name'])
for e in b['experiences']:
 if e['review_ready']:result['bogota']['places'][e['id']]=media('bogota-semirrealista/'+e['id']+'-800.webp',e['nombre']['es'])
s=json.loads((R/'worker-lugar-4/backend/public/assets/santa-marta-semirrealista/contenido.json').read_text());result['santa-marta']={'hero':media('santa-marta-semirrealista/hero-1200.webp','Santa Marta · centro y bahía',True),'places':{}}
for p in s['places']+s['experiences']:
 result['santa-marta']['places'][p.get('slug',p.get('id'))]=media('santa-marta-semirrealista/'+p['asset_id']+'-800.webp',p['translations']['es']['name'])
m=json.loads((R/'worker-lugar-3/crecimiento-colombia/entregas/medellin/semirrealista-v1/integracion-medellin.json').read_text());result['medellin']={'hero':media('medellin-semirrealista/web/portada-1200.webp','Medellín · Coltejer',True),'places':{}}
for p in m['places']+m['experiences']:result['medellin']['places'][p['slug']]=media('medellin-semirrealista/web/'+p['slug']+'-800.webp',p['translations']['es']['name'])
for A in [P/'backend/public/assets',P/'.local/preprod/release/public/assets']:
 (A/'destinos-semirrealistas.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
 for d in result.values():
  for v in [d['hero'],*d['places'].values()]:assert (A.parent/v['path']).is_file(),v['path']
for f in [P/'backend/integration-medicion/public/destino.php',P/'.local/preprod/release/public/destino.php']:
 text=f.read_text();needle="$locale=$d['locale'];$t=langCopy($locale);$name=$d['display_name'];"
 block='''
// Private illustration proposals replace old media before HTML rendering, avoiding duplicate photo downloads.
if($preview && in_array($slug,['bogota','medellin','santa-marta'],true)){
 $visualPath=__DIR__.'/assets/destinos-semirrealistas.json';
 if(is_file($visualPath)){
  $visual=json_decode(file_get_contents($visualPath),true,32,JSON_THROW_ON_ERROR)[$slug]??null;
  if($visual){$d['hero']=$visual['hero'];$d['history_media']=null;
   foreach($d['places'] as &$entry)if(isset($visual['places'][$entry['slug']]))$entry['media']=[$visual['places'][$entry['slug']]];
   unset($entry);
  }
 }
}
'''
 if 'Private illustration proposals replace' not in text:text=text.replace(needle,needle+block)
 f.write_text(text)
print('60 image roles mapped; private only, files verified.')


# Shared open sections, responsive pagination and private-language links.
for target in [P/'backend/public/assets',P/'.local/preprod/release/public/assets']:
 for name in ['place-sections.js','cargar-mas-tarjetas.js']:
  shutil.copyfile(R/'worker-lugar-2/backend/public/assets'/name,target/name)
for f in [P/'backend/integration-medicion/public/destino.php',P/'.local/preprod/release/public/destino.php']:
 text=f.read_text();needle="$langEsUrl=u('/destinos/'.$slug.'/');$langEnUrl=u('/en/destinos/'.$slug.'/');$langPtUrl=u('/pt-br/destinos/'.$slug.'/');"
 if 'Keep private preview when switching language' not in text:text=text.replace(needle,needle+"\n// Keep private preview when switching language; authentication remains required.\nif($preview){$langEsUrl.='?preview=1';$langEnUrl.='?preview=1';$langPtUrl.='?preview=1';}")
 f.write_text(text)

# Strict parity with Jericó/Cartagena: no expandable sections or extra history media.
for target in [P/'backend/public/assets',P/'.local/preprod/release/public/assets']:
 for ext in ['js','css']:
  name='destino-lectura-directa.'+ext
  shutil.copyfile(R/'worker-lugar-2/backend/public/assets'/name,target/name)
for f in [P/'backend/integration-medicion/public/destino.php',P/'.local/preprod/release/public/destino.php']:
 text=f.read_text();marker='<!-- destino-lectura-directa -->'
 if marker not in text:text=text.replace('</body>',marker+"<?php if($preview && in_array($slug,['bogota','medellin','santa-marta'],true)):?><link rel=\"stylesheet\" href=\"/assets/destino-lectura-directa.css?v=2\"><script defer src=\"/assets/destino-lectura-directa.js?v=2\"></script><?php endif;?></body>")
 for city in ['bogota','medellin','santa-marta']:
  for ext in ['js','css']:text=text.replace(city+'-compacta.'+ext+'?v=1',city+'-compacta.'+ext+'?v=2')
 text=text.replace('/assets/place-sections.js?v=1','/assets/place-sections.js?v=2')
 f.write_text(text)
