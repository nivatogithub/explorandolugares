"""Instala solo la nueva portada local, sin alterar el resto de lugares."""
from pathlib import Path
import json,shutil,re
own=Path(__file__).resolve().parents[1];pre=own.parent/'preprod'
SLUG='cartagena';FOLDER='cartagena-piloto';KEY='portada-ciudad-amurallada-v3';ALT='Cartagena · ciudad amurallada'
for assets in [pre/'backend/public/assets',pre/'backend/integration-medicion/public/assets',pre/'.local/preprod/release/public/assets']:
 out=assets/FOLDER;out.mkdir(parents=True,exist_ok=True)
 for w in [640,1200]:shutil.copyfile(own/f'backend/public/assets/{FOLDER}/{KEY}-{w}.webp',out/f'{KEY}-{w}.webp')
 manifest=assets/'destinos-semirrealistas.json';d=json.loads(manifest.read_text());d[SLUG]['hero'].update(path=f'assets/{FOLDER}/{KEY}-1200.webp',alt='Ilustración editorial: '+ALT);manifest.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
 if SLUG=='santa-marta':
  js=assets/'santa-marta-compacta.js'
  if js.exists():js.write_text(js.read_text().replace("image('hero-v2','Santa Marta · bahía histórica y Camellón'","image('hero-v3','Santa Marta · bahía histórica y Camellón'"))
if SLUG=='santa-marta':
 for p in [pre/'backend/integration-medicion/public/destino.php',pre/'.local/preprod/release/public/destino.php']:
  p.write_text(re.sub(r'santa-marta-compacta.js\?v=[^"<> ]+','santa-marta-compacta.js?v=4',p.read_text()))
print(SLUG+': nueva portada en preproducción, revisión pendiente.')
