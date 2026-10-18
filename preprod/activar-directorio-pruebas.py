#!/usr/bin/env python3
"""Explicitly publish local Jericó directory and synthetic subscription fixtures.
Run cuentas-prueba.sh first. Never connects to production or sends payments.
"""
from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
if subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()!='preprod':
    raise SystemExit('Usar únicamente desde preprod.')
result=subprocess.run(['podman','exec','-e','EL_PREPROD_LOCAL=1','-i','explorando-preprod','php'],input=(ROOT/'preprod/activar-directorio-pruebas.php').read_bytes())
raise SystemExit(result.returncode)
