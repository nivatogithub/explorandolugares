#!/usr/bin/env python3
"""Habilita correo transaccional real local para una sola dirección; no envía mensajes."""
import argparse,json,re
from pathlib import Path
root=Path(__file__).resolve().parents[1];private=root/'.local/preprod/private'
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--destinatario');p.add_argument('--desactivar',action='store_true');a=p.parse_args()
if not private.is_dir():raise SystemExit('Primero configura preproducción.')
if not a.desactivar and (not a.destinatario or not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+',a.destinatario)):raise SystemExit('Indica un correo válido con --destinatario.')
file=private/'account-mail-test.json'
if file.is_symlink():raise SystemExit('Archivo privado no válido.')
file.write_text(json.dumps({'enabled':not a.desactivar,'recipient':a.destinatario.lower() if a.destinatario else ''})+'\n');file.chmod(0o600)
print('Prueba real desactivada.' if a.desactivar else 'Prueba real habilitada para el único correo indicado. No se enviaron mensajes.')
