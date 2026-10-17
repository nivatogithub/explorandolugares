#!/usr/bin/env python3
"""Compatibilidad: el controlador vigente está en preprod/control.py."""
from pathlib import Path
import runpy
runpy.run_path(str(Path(__file__).resolve().parents[1]/'preprod/control.py'),run_name='__main__')
