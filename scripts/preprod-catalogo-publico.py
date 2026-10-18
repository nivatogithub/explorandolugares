from pathlib import Path
import json,re,html,shutil
R=Path(__file__).resolve().parents[2];P=R/'preprod';m=json.loads((P/'.local/preprod/worker-review/local-public-destinations.json').read_text());v=json.loads((P/'backend/public/assets/destinos-semirrealistas.json').read_text())
for lang,prefix,more in [('es','','Conocer el destino →'),('en','en/','Explore destination →'),('pt-BR','pt-br/','Conhecer o destino →')]:
 cards=[]
 for slug,row in m['destinations'].items():
  d=row[lang];path=v.get(slug,{}).get('hero',{}).get('path',d['hero_path']);
  if slug=='cartagena':path='assets/cartagena-piloto/portada-1200.webp'
  if not(P/'.local/preprod/release/public'/path).is_file():path=d['hero_path']
  esc=html.escape;url='/'+prefix+'destinos/'+slug+'/'
  cards.append(f'<article class="destination-card" data-region="{esc(d["subregion"])}" data-name="{esc(d["name"])}"><a class="destination-card-image" href="{url}"><img src="/{esc(path)}" alt="{esc(d["name"])}" width="800" height="533" loading="lazy" decoding="async"></a><div class="copy"><span class="badge">{esc(d["subregion"])}</span><h2><a href="{url}">{esc(d["name"])}</a></h2><p>{esc(d["intro"])}</p><a class="text-link" href="{url}">{more}</a></div></article>')
 f=P/'.local/preprod/release/public'/prefix/'destinos/index.html';s=f.read_text();s,n=re.subn(r'(<div class="destination-grid" id="destination-grid">).*?(</div><div class="destination-pagination")',lambda match:match[1]+''.join(cards)+match[2],s,count=1,flags=re.S);assert n==1
 # Remove obsolete single-destination fallback text.
 s=re.sub(r'<noscript>.*?</noscript>','',s,flags=re.S)
 regions=sorted({row[lang]['subregion'] for row in m['destinations'].values()});s=re.sub(r'(<select id="destination-region">)(.*?)(</select>)',lambda z:z[1]+re.search(r'<option value="">.*?</option>',z[2])[0]+''.join(f'<option value="{html.escape(x)}">{html.escape(x)}</option>' for x in regions)+z[3],s,count=1,flags=re.S)
 f.write_text(s);out=P/'backend/integration-medicion/public'/prefix/'destinos/index.html';out.parent.mkdir(parents=True,exist_ok=True);out.write_text(s)
print('Local catalogue:',len(m['destinations']),'destinations, three languages.')
