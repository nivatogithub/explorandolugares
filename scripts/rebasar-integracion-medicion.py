#!/usr/bin/env python3
"""Apply only measurement/navigation additions to the verified current public files."""
import json,re,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];base=ROOT/'.local/analytics-base';out=ROOT/'backend/integration-medicion'
m=json.loads((base/'manifest.json').read_text());entries={f['relative']:f for f in m['files']};guards={}
for previous in sorted((out/'public').rglob('*')):
 if not previous.is_file():continue
 relative=str(previous.relative_to(out));source=base/relative
 if not source.is_file():raise ValueError('Current verified base missing: '+relative)
 raw=source.read_bytes();assert hashlib.sha256(raw).hexdigest()==entries[relative]['sha256'];guards[relative]=entries[relative]['sha256'];s=raw.decode();ours=previous.read_text()
 if relative.endswith('menu-principal.js'):
  if 'cobertura/colombia' not in s:
   anchor="  if(!nav.querySelector('a[href*=\"cobertura/colombia\"]')){const link=document.createElement('a');link.href='/cobertura/colombia/';link.textContent=document.documentElement.lang==='en'?'We’re growing':document.documentElement.lang==='pt-BR'?'Estamos crescendo':'Estamos creciendo';nav.insertBefore(link,nav.querySelector('.pill'));}\n"
   assert "  const header = nav.closest('header');" in s;s=s.replace("  const header = nav.closest('header');",anchor+"  const header = nav.closest('header');")
  if 'medicion.js' not in s:s+='\n'+ours[ours.index('/* First-party opt-in analytics;'):]
 elif relative.endswith('.php'):
  if 'cobertura/colombia' not in s:s=s.replace('<a class="pill light"','<a href="/cobertura/colombia/">Estamos creciendo</a><a class="pill light"',1)
  if 'medicion.js' not in s:
   assert '</body>' in s;s=s.replace('</body>','<script defer data-el-measurement="1" src="/assets/cobertura/medicion.js?v=1"></script></body>')
 elif 'privacidad' in relative and '</main>' in s:
  if 'medicion-opcional' not in s:
   section=re.search(r'<section id="medicion-opcional">.*?</section>',ours,re.S)
   assert section;s=s.replace('</main>',section[0]+'</main>')
 elif '/destinos/' in relative:
  assert '</body>' in s
  if 'cobertura/colombia' not in s:
   label='We’re growing' if '/en/' in relative else 'Estamos crescendo' if '/pt-br/' in relative else 'Estamos creciendo'
   s=s.replace('<a class="pill light"',f'<a href="/cobertura/colombia/">{label}</a><a class="pill light"',1)
  if 'menu-principal.js' not in s:s=s.replace('</body>','<script src="/assets/menu-principal.js?v=20261016" defer></script></body>')
  if 'medicion.js' not in s:s=s.replace('</body>','<script defer data-el-measurement="1" src="/assets/cobertura/medicion.js?v=1"></script></body>')
 previous.write_text(s)
(out/'baseline.json').write_text(json.dumps(guards,indent=2)+'\n');print('Rebased integration:',len(guards),'public files; no unrelated changes replaced')
