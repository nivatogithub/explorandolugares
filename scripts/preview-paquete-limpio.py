#!/usr/bin/env python3
"""Disposable local preview: fresh build, same public routes, cleanup after server stops."""
from pathlib import Path
import argparse,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1]
def main():
 p=argparse.ArgumentParser();p.add_argument('--port',type=int,default=8011);a=p.parse_args()
 if not 1024<=a.port<=65535:p.error('Puerto local inválido')
 (ROOT/'.local').mkdir(exist_ok=True)
 with tempfile.TemporaryDirectory(prefix='preview-',dir=ROOT/'.local') as folder:
  package=Path(folder)/'release'
  subprocess.run([sys.executable,str(ROOT/'scripts/preparar-hosting.py'),'--output',str(package)],cwd=ROOT,check=True)
  process=subprocess.Popen(['php','-S',f'127.0.0.1:{a.port}','-t',str(package/'public'),str(ROOT/'scripts/php-destinations-router.php')],cwd=ROOT)
  try:process.wait()
  except KeyboardInterrupt:pass
  finally:
   if process.poll() is None:
    process.terminate()
    try:process.wait(timeout=5)
    except subprocess.TimeoutExpired:process.kill();process.wait()
if __name__=='__main__':main()
