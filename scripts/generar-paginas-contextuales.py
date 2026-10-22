#!/usr/bin/env python3
"""Initial HTML fragments come from PHP/data, never a client-side replacement."""
from pathlib import Path
import argparse

def apply(public):
 public=Path(public)
 for prefix,locale in [('', 'es'),('en/','en'),('pt-br/','pt-BR')]:
  for suffix,kind in [('', 'home'),('privacidad/','privacy')]:
   folder=public/(prefix+suffix)
   if not (folder/'index.html').is_file():continue
   depth=len(folder.relative_to(public).parts)
   root='__DIR__' if depth==0 else f'dirname(__DIR__,{depth})'
   (folder/'index.php').write_text("<?php\ndeclare(strict_types=1);\nrequire_once "+root+".'/_portal-page.php';\nportal_render_page(__DIR__.'/index.html',"+repr(locale)+','+repr(kind)+");\n")
 htaccess=public/'.htaccess';text=htaccess.read_text();text=text.replace('DirectoryIndex index.html','DirectoryIndex index.php index.html')
 if 'DirectoryIndex ' not in text:text='DirectoryIndex index.php index.html\n'+text
 htaccess.write_text(text)
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
