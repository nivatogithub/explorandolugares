#!/usr/bin/env python3
"""Read-only regression: Free plus exactly three paid presentation contracts."""
import urllib.request,urllib.error
from regresion.suscripciones_visuales import run
BASE='http://127.0.0.1:8088';failed=[]
def get(path):
 try:
  with urllib.request.urlopen(BASE+path,timeout=20) as r:return r.status,r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.read().decode()
def check(ok,message):
 if not ok:raise AssertionError(message)
def case(id,title,fn):
 try:fn();print('OK',id,title)
 except Exception as error:failed.append(id);print('FALLO',id,str(error))
run(case,check,get)
raise SystemExit(1 if failed else 0)
