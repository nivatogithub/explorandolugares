"""Check private preview entries and media references without launching Chrome."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit,unquote
import json,subprocess
ROOT=Path(__file__).resolve().parents[2]
class Preview(HTMLParser):
 def __init__(self):super().__init__();self.images=[];self.ids=[];self.errors=[]
 def handle_starttag(self,tag,attrs):
  attrs=dict(attrs)
  if 'id' in attrs:self.ids.append(attrs['id'])
  if tag=='img':
   self.images.append(attrs.get('src',''))
   if not attrs.get('alt','').strip():self.errors.append('Image without alt text')
rows=[]
for n,slug,entry in [(1,'cartagena','preview.html'),(2,'bogota','previsualizacion.html'),(3,'medellin','preview.html'),(4,'santa-marta','index.html')]:
 checkout=ROOT.parent/f'worker-lugar-{n}';base=checkout/'crecimiento-colombia/entregas'/slug;files=[base/entry]
 if slug=='santa-marta':files.append(base/'revision-referencias.html')
 for file in files:
  assert file.is_file(),file
  parser=Preview();parser.feed(file.read_text());assert not parser.errors,(file,parser.errors)
  assert len(set(parser.ids))==len(parser.ids),(file,'duplicate HTML IDs')
  for src in parser.images:
   parsed=urlsplit(src)
   if parsed.scheme or parsed.netloc:continue
   target=(file.parent/unquote(parsed.path)).resolve()
   assert target.is_relative_to(base.resolve()) and target.is_file(),(file,src)
  rows.append(dict(worker=f'Lugar {n}',page=str(file.relative_to(checkout)),local_images=len(parser.images),commit=subprocess.check_output(['git','-C',str(checkout),'rev-parse','--short','HEAD'],text=True).strip()))
print(json.dumps(rows,ensure_ascii=False,indent=2))
