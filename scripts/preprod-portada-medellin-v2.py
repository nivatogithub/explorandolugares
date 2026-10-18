"""Instala únicamente la propuesta de portada v2 en preproducción local."""
from pathlib import Path
import json,shutil
own=Path(__file__).resolve().parents[1]; pre=own.parent/'preprod'
d=own/'crecimiento-colombia/entregas/medellin/semirrealista-v1'
for target in [pre/'backend/public/assets',pre/'backend/integration-medicion/public/assets',pre/'.local/preprod/release/public/assets']:
 out=target/'medellin-semirrealista/web';out.mkdir(parents=True,exist_ok=True)
 for width in [640,1200]:shutil.copyfile(d/f'web/portada-v2-{width}.webp',out/f'portada-v2-{width}.webp')
 js=target/'medellin-compacta.js'
 if js.exists():js.write_text(js.read_text().replace('web/portada-','web/portada-v2-').replace('Medellín · Coltejer · ilustración editorial','Medellín · Pueblito Paisa · ilustración editorial'))
 manifest=target/'destinos-semirrealistas.json'
 if manifest.exists():
  data=json.loads(manifest.read_text());hero=data['medellin']['hero'];hero.update(path='assets/medellin-semirrealista/web/portada-v2-1200.webp',alt='Ilustración editorial: Medellín · Pueblito Paisa');manifest.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
for f in [pre/'backend/integration-medicion/public/destino.php',pre/'.local/preprod/release/public/destino.php']:
 s=f.read_text();import re
 s=re.sub(r'medellin-compacta.js\?v=[^"<> ]+','medellin-compacta.js?v=3',s);f.write_text(s)
print('Portada v2 instalada solo en preproducción; aprobación pendiente.')
