#!/usr/bin/env python3
"""Private lossless trials only. Never copies a candidate into public or changes an original."""
from pathlib import Path
import argparse,hashlib,json,subprocess,tempfile
from concurrent.futures import ThreadPoolExecutor
ROOT=Path(__file__).resolve().parents[1]
def run(public,output):
 public=public.resolve();output=output.resolve()
 if not output.is_relative_to(ROOT/'.local'):raise ValueError('Comparativas privadas solo en .local/.')
 output.mkdir(parents=True,exist_ok=True);groups={}
 for path in sorted(public.rglob('*')):
  if path.is_file() and path.suffix.lower() in {'.webp','.png','.jpg','.jpeg'}:
   sha=hashlib.sha256(path.read_bytes()).hexdigest();groups.setdefault(sha,[]).append(path)
 def trial(item):
  sha,paths=item;source=paths[0];size=source.stat().st_size
  with tempfile.TemporaryDirectory(prefix='el-lossless-') as temp:
   candidate=Path(temp)/'candidate.webp'
   result=subprocess.run(['cwebp','-quiet','-lossless','-m','0','-q','0','-metadata','all',str(source),'-o',str(candidate)],capture_output=True)
   record={'sha256':sha,'paths':[p.relative_to(public).as_posix() for p in paths],'bytes':size,'method':'WebP lossless m0/q0; metadata retained; current decoded pixels; no AI or lossy conversion','promoted':False}
   if result.returncode:record.update(status='ensayo no disponible',reason=result.stderr.decode()[:160]);return record
   size2=candidate.stat().st_size;record.update(candidate_bytes=size2,savings_bytes=size-size2,savings_percent=round((size-size2)/size*100,2))
   if size2<size*.95 and size-size2>=10240:
    ref=Path(temp)/'current.ppm';decoded=Path(temp)/'candidate.ppm'
    subprocess.run(['magick',str(source),'-depth','8',str(ref)],check=True,capture_output=True)
    subprocess.run(['magick',str(candidate),'-depth','8',str(decoded)],check=True,capture_output=True)
    record['decoded_pixels_equal']=ref.read_bytes()==decoded.read_bytes()
    if not record['decoded_pixels_equal']:raise ValueError('Lossless pixel comparison mismatch '+str(source))
    target=output/(sha+'.webp');target.write_bytes(candidate.read_bytes());record.update(status='candidata pendiente',candidate=target.name)
   else:record['status']='sin mejora útil'
   return record
 with ThreadPoolExecutor(max_workers=2) as pool:
  records=[]
  for result in pool.map(trial,groups.items()):
   records.append(result);(output/'index.json').write_text(json.dumps({'schema_version':1,'originals_changed':False,'images':records},ensure_ascii=False,indent=2)+'\n')
 print(f'{len(records)} identidades visuales evaluadas; {sum(r["status"]=="candidata pendiente" for r in records)} candidatas privadas. Ninguna promovida.')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.public,a.output)
