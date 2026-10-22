"""Small CSS block walker: keeps strings/comments intact; only splits selector lists at top level."""
import re
SHELL=re.compile(r'(?<![\w-])(?:header|footer)(?![\w-])|\.(?:brand(?:-logo)?|menu-toggle|header-note|header-language-[\w-]+|language-switch[\w-]*|portal-account-(?:strip[\w-]*|inline|fallback)|account-name|footer-contact[\w-]*)(?![\w-])')
def items(css):
 start=0;i=0;quote=None
 while i<len(css):
  if css.startswith('/*',i):
   end=css.find('*/',i+2);i=len(css) if end<0 else end+2;continue
  ch=css[i]
  if quote:
   if ch=='\\':i+=2;continue
   if ch==quote:quote=None
   i+=1;continue
  if ch in '\"\x27':quote=ch;i+=1;continue
  if ch==';':yield css[start:i+1],None;i+=1;start=i;continue
  if ch=='{':
   prelude=css[start:i];opening=i;depth=1;i+=1
   while i<len(css) and depth:
    if css.startswith('/*',i):
     end=css.find('*/',i+2);i=len(css) if end<0 else end+2;continue
    ch=css[i]
    if quote:
     if ch=='\\':i+=2;continue
     if ch==quote:quote=None
    elif ch in '\"\x27':quote=ch
    elif ch=='{':depth+=1
    elif ch=='}':depth-=1
    i+=1
   yield prelude,css[opening+1:i-1];start=i;continue
  i+=1
 if start<len(css):yield css[start:],None

def selectors(prelude):
 start=0;depth=0;quote=None
 for i,ch in enumerate(prelude):
  if quote:
   if ch==quote and (i==0 or prelude[i-1]!='\\'):quote=None
  elif ch in '\"\x27':quote=ch
  elif ch in '([':depth+=1
  elif ch in ')]':depth-=1
  elif ch==',' and depth==0:yield prelude[start:i];start=i+1
 yield prelude[start:]

def split(css):
 keep=[];owned=[]
 for prelude,body in items(css):
  if body is None:keep.append(prelude);continue
  clean=re.sub(r'/\*.*?\*/','',prelude,flags=re.S).strip()
  if clean.startswith(('@media','@supports','@layer','@container')):
   a,b=split(body)
   if a.strip():keep.append(prelude+'{'+a+'}')
   if b.strip():owned.append(clean+'{'+b+'}')
  elif clean.startswith('@'):keep.append(prelude+'{'+body+'}')
  else:
   groups=list(selectors(clean));yes=[p for p in groups if SHELL.search(p)];no=[p for p in groups if not SHELL.search(p)]
   if no:keep.append(','.join(no)+'{'+body+'}')
   if yes:owned.append(','.join(yes)+'{'+body+'}')
 return ''.join(keep),'\n'.join(owned)
