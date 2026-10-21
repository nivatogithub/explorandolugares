"""Render approved destination cards from the canonical release catalog."""
import json,re,html
from pathlib import Path
root=Path(__file__).resolve().parents[1]
data=json.loads((root/'content/release-places-businesses.json').read_text())
for locale,prefix in [('es',''),('en','en/'),('pt-BR','pt-br/')]:
 p=root/'backend/integration-medicion/public'/prefix/'destinos/index.html'
 text=p.read_text();cards=[]
 for slug in data['scope']:
  d=data['editorial'][slug];t=d['translations'][locale];esc=lambda s:html.escape(str(s),quote=True)
  url='/'+prefix+'destinos/'+slug+'/'
  image='/'+d['destination_media']['hero']['asset_path'].lstrip('/')
  name=esc(t['display_name']);region=esc(d.get('subregion') or d['department'])
  label={'es':'Conocer el lugar','en':'Explore the place','pt-BR':'Conhecer o lugar'}[locale]
  cards.append(f'<article class="destination-card" data-region="{region}" data-name="{name}"><a class="destination-card-image" href="{url}"><img src="{esc(image)}" alt="{name}" width="800" height="533" loading="lazy" decoding="async"></a><div class="copy"><span class="badge">{region}</span><h2><a href="{url}">{name}</a></h2><p>{esc(t["meta_description"])}</p><a class="text-link" href="{url}">{label} →</a></div></article>')
 text,n=re.subn(r'(<div class="destination-grid" id="destination-grid">).*?(</div><div class="destination-pagination")',lambda m:m[1]+''.join(cards)+m[2],text,count=1,flags=re.S)
 if n!=1:raise RuntimeError('Grid no reconocido: '+str(p))
 # Include every region in the filter.
 regions=sorted({d.get('subregion') or d['department'] for d in data['editorial'].values()})
 text=re.sub(r'(<select id="destination-region"><option value="">.*?</option>).*?(</select>)',lambda m:m[1]+''.join(f'<option value="{esc(r)}">{esc(r)}</option>' for r in regions)+m[2],text,count=1,flags=re.S)
 p.write_text(text)
 local=root/'.local/preprod/release/public'/prefix/'destinos/index.html'
 if local.exists():
  # Preserve local header/footer integrations; replace only the grid and filter.
  served=local.read_text()
  served=re.sub(r'(<div class="destination-grid" id="destination-grid">).*?(</div><div class="destination-pagination")',lambda m:m[1]+''.join(cards)+m[2],served,count=1,flags=re.S)
  local.write_text(served)
 print(locale,len(cards))
