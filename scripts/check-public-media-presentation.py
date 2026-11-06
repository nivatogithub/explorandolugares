"""Block public image-credit pages and raw provenance links before publication."""
import argparse,re
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--release',required=True,type=Path);a=p.parse_args()
public=a.release/'public';app=a.release/'app';bad=[]
for locale in ['', 'en', 'pt-br']:
 for section in ['creditos-fotograficos','creditos-imagenes-caribe']:
  if (public/locale/section).exists():bad.append(str(Path(locale)/section))
pattern=re.compile(r'<a\b[^>]*(?:commons\.wikimedia\.org/wiki/File:|creativecommons\.org/licenses/|rel=["\x27]license)|class=["\x27]credit["\x27]',re.I)
for page in public.rglob('*.html'):
 if 'assets' in page.relative_to(public).parts or 'admin' in page.relative_to(public).parts:continue
 if pattern.search(page.read_text()):bad.append(str(page.relative_to(public)))
card=(app/'views/home-attraction-card.php').read_text()
if any(key in card for key in ['source_url','license_url','license_name','creator']):bad.append('home card prints private provenance fields')
if bad:raise ValueError('Procedencia de imágenes expuesta en páginas públicas: '+', '.join(bad))
print('OK: private image provenance has no public credit pages, homepage fields or static attribution links')
