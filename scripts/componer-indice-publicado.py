#!/usr/bin/env python3
"""Render exactly one card per canonical published destination after legacy composition."""
import argparse,json,re,html
from pathlib import Path
R=Path(__file__).resolve().parents[1];p=argparse.ArgumentParser();p.add_argument('--release',type=Path,required=True);a=p.parse_args();public=a.release/'public';catalog=json.loads((R/'content/release-places-businesses.json').read_text());slugs=catalog['public_places'];assert len(slugs)==len(set(slugs))
for locale,prefix in [('es',''),('en','en/'),('pt-BR','pt-br/')]:
 esc=lambda x:html.escape(str(x),quote=True);cards=[];regions=set()
 for slug in slugs:
  d=catalog['editorial'][slug];t=d['translations'][locale];name=esc(t['display_name']);region=esc(d.get('subregion')or d['department']);regions.add(region);url='/'+prefix+'lugares/'+slug+'/';m=d['destination_media']['hero'];image='/'+m['asset_path'];label={'es':'Conocer el lugar','en':'Explore the place','pt-BR':'Conhecer o lugar'}[locale]
  cards.append(f'<article class="destination-card" data-region="{region}" data-name="{name}"><a class="destination-card-image" href="{url}"><img src="{esc(image)}" alt="{name}" width="800" height="533" loading="lazy" decoding="async"></a><div class="copy"><span class="badge">{region}</span><h2><a href="{url}">{name}</a></h2><p>{esc(t["meta_description"])}</p><a class="text-link" href="{url}">{label} →</a></div></article>')
 for route in ['lugares','destinos']:
  f=public/prefix/route/'index.html'
  if not f.exists():continue
  s=f.read_text();s,n=re.subn(r'(<div class="destination-grid" id="destination-grid">).*?(</div><div class="destination-pagination")',lambda m:m[1]+''.join(cards)+m[2],s,count=1,flags=re.S);assert n==1,f
  s=re.sub(r'(<select id="destination-region"><option value="">.*?</option>).*?(</select>)',lambda m:m[1]+''.join(f'<option value="{r}">{r}</option>'for r in sorted(regions))+m[2],s,count=1,flags=re.S);f.write_text(s)
# Dynamic PHP guides have no static HTML; include their canonical language routes explicitly.
from xml.etree import ElementTree as ET
f=public/'sitemap.xml';tree=ET.parse(f);root=tree.getroot();ns='http://www.sitemaps.org/schemas/sitemap/0.9';existing={e.text for e in root.findall('{'+ns+'}url/{'+ns+'}loc')}
for prefix in ['', 'en/','pt-br/']:
 for slug in slugs:
  url='https://explorandolugares.com/'+prefix+'lugares/'+slug+'/'
  if url not in existing:ET.SubElement(ET.SubElement(root,'{'+ns+'}url'),'{'+ns+'}loc').text=url
ET.register_namespace('',ns);ET.indent(tree);tree.write(f,encoding='UTF-8',xml_declaration=True);print('Canonical published catalog:',len(slugs),'unique destinations, three languages')
