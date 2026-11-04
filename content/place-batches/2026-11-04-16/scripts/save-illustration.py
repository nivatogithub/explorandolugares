import sys,json,subprocess,hashlib,shutil
from pathlib import Path
root=Path(__file__).resolve().parents[1]
slug,key,source=sys.argv[1:4];p=root/'places'/slug;o=p/'images';o.mkdir(exist_ok=True)
s=Path(source);ref=json.loads((p/'references'/f'{key}.json').read_text())
for w,h in [(1200,800),(640,427)]:
 subprocess.run(['cwebp','-quiet','-q','82','-m','6','-resize',str(w),str(h),str(s),'-o',str(o/f'{key}-{w}.webp')],check=True)
d={'role':'hero' if key=='hero' else 'attraction','kind':'ai_editorial_illustration','reference':ref,'master_path':str(s),'master_sha256':hashlib.sha256(s.read_bytes()).hexdigest(),'generated_at':'2026-11-04','tool':'image_gen','approval':None,'review_status':'pending_human_review','outputs':[{'file':str(f.relative_to(p)), 'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'bytes':f.stat().st_size} for f in o.glob(key+'-*.webp')]}
(o/f'{key}.manifest.json').write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n');print(slug,key,'saved',flush=True)
