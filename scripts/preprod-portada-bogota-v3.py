"""Actualiza solo portada de Bogotá en las fuentes y preproducción local."""
from pathlib import Path
import json,shutil,re
own=Path(__file__).resolve().parents[1];pre=own.parent/'preprod'
for target in [pre/'backend/public/assets',pre/'backend/integration-medicion/public/assets',pre/'.local/preprod/release/public/assets']:
 out=target/'bogota-semirrealista';out.mkdir(parents=True,exist_ok=True)
 for width in [640,1200]:shutil.copyfile(own/f'backend/public/assets/bogota-semirrealista/portada-v3-{width}.webp',out/f'portada-v3-{width}.webp')
 js=target/'bogota-compacta.js'
 if js.exists():js.write_text(js.read_text().replace("image('portada-v2','Bogotá · Plaza de Bolívar'","image('portada-v3','Bogotá · Torre Colpatria'"))
 manifest=target/'destinos-semirrealistas.json'
 if manifest.exists():
  data=json.loads(manifest.read_text());data['bogota']['hero'].update(path='assets/bogota-semirrealista/portada-v3-1200.webp',alt='Ilustración editorial: Bogotá · Torre Colpatria');manifest.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
for f in [pre/'backend/integration-medicion/public/destino.php',pre/'.local/preprod/release/public/destino.php']:
 f.write_text(re.sub(r'bogota-compacta.js\?v=[^"<> ]+','bogota-compacta.js?v=4',f.read_text()))
print('Portada Bogotá v3 instalada solo en preproducción.')
