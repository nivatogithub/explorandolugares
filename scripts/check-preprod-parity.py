#!/usr/bin/env python3
"""Compatibilidad: la comprobación vigente está en preprod/verificar.py."""
from pathlib import Path
import runpy
runpy.run_path(str(Path(__file__).resolve().parents[1]/'preprod/verificar.py'),run_name='__main__')
