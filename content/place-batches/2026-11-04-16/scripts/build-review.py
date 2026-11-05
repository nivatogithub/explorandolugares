#!/usr/bin/env python3
"""Canonical entry point for the static, self-contained review pages."""
from pathlib import Path
import runpy
runpy.run_path(str(Path(__file__).with_name('build-static-review.py')),run_name='__main__')
