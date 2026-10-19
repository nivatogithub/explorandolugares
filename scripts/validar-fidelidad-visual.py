#!/usr/bin/env python3
"""Valida evidencia de identidad; no sustituye inspección visual humana."""
import argparse,json,hashlib
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('record');p.add_argument('--ready',action='store_true');a=p.parse_args();f=Path(a.record);d=json.loads(f.read_text());errors=[]
ref=d['reference'];g=d['generated'];comparison=d['comparison'];approval=d['human_approval']
if not ref.get('source_page') or not ref.get('checked_at'):errors.append('Falta fuente/fecha de consulta')
if not ref.get('input_use_allowed'):errors.append('Uso de referencia como entrada sin comprobar')
if not ref.get('license') or not ref.get('author'):errors.append('Falta procedencia de referencia')
asset=f.parent/g['file']
if not asset.is_file() or hashlib.sha256(asset.read_bytes()).hexdigest()!=g.get('sha256'):errors.append('Activo ausente o hash incorrecto')
blockers=[]
if not ref.get('exact_sector_verified'):blockers.append('Sector/identidad de uso no acreditado')
if not comparison.get('passed'):blockers.append('Comparación no aprobada')
if approval.get('decision')!='approved' or approval.get('sha256')!=g.get('sha256'):blockers.append('Falta aprobación humana del hash actual')
print(json.dumps({'record_valid':not errors,'errors':errors,'ready_for_integration':not errors and not blockers,'blockers':blockers,'note':'No evalúa píxeles ni prueba fidelidad por sí solo'},ensure_ascii=False,indent=2))
raise SystemExit(1 if errors or (a.ready and blockers) else 0)
