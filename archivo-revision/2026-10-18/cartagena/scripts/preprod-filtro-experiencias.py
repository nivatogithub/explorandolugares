"""Apply a focused simulator patch to the current preprod sources; preserve unrelated work."""
from pathlib import Path
import shutil,re
W=Path(__file__).resolve().parents[2];P=W/'preprod';own=Path(__file__).resolve().parents[1]
for assets in [P/'backend/public/assets',P/'backend/integration-medicion/public/assets',P/'.local/preprod/release/public/assets']:
 assets.mkdir(parents=True,exist_ok=True)
 for ext in ['js','css']:shutil.copyfile(own/'backend/public/assets'/('subscription-location-filter.'+ext),assets/('subscription-location-filter.'+ext))
for f in [P/'backend/public/api/subscription-prices.php',P/'.local/preprod/release/public/api/subscription-prices.php']:
 s=f.read_text()
 if 'destination_name,d.country_code,d.department' not in s:s=s.replace('p.id,p.destination_id,t.name,dt.display_name AS destination_name','p.id,p.destination_id,t.name,dt.display_name AS destination_name,d.country_code,d.department')
 f.write_text(s)
for f in [P/'ensayos/v1/assets/subscription-simulator.js',P/'.local/preprod/release/public/assets/subscription-simulator.js']:
 s=f.read_text().replace('experience.disabled=free;','experience.disabled=free||!root.querySelector("[data-sim-place]")?.value;')
 s=s.replace("for(const item of data.experiences||[])experience.add(new Option(item.destination_name+' — '+item.name,item.id));","document.dispatchEvent(new CustomEvent('subscription:catalog',{detail:data}));")
 assert 'subscription:catalog' in s
 f.write_text(s)
for prefix in ['', 'en/', 'pt-br/']:
 f=P/'ensayos/v1'/prefix/'negocios-locales/planes/index.html';s=f.read_text()
 hook='<link rel="stylesheet" href="/assets/subscription-location-filter.css?v=1"><script defer src="/assets/subscription-location-filter.js?v=1"></script>'
 if 'subscription-location-filter.js' not in s:s=re.sub(r'(?=<script defer src="[^"]*subscription-simulator.js)',lambda m:hook,s,count=1)
 s=re.sub(r'subscription-simulator.js\?v=[^"<> ]+','subscription-simulator.js?v=4',s);f.write_text(s)
 prepared=P/'.local/preprod/release/public'/prefix/'negocios-locales/planes/index.html';current=prepared.read_text()
 if 'subscription-location-filter.js' not in current:current=re.sub(r'(?=<script defer src="[^"]*subscription-simulator.js)',lambda m:hook,current,count=1)
 current=re.sub(r'subscription-simulator.js\?v=[^"<> ]+','subscription-simulator.js?v=4',current);prepared.write_text(current)
print('Simulator geography filter applied to source and local release (ES/EN/PT).')
