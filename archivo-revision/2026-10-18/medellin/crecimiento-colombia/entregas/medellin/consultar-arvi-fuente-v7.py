raise SystemExit("INACTIVO: el propietario elegirá URLs; no ejecutar búsquedas o descargas automáticas.")
"""Coordinación: consulta solo metadatos; NO descarga fotografía sin licencia comprobada."""
from pathlib import Path
import subprocess
r=Path(__file__).resolve().parent
url='https://commons.wikimedia.org/w/api.php?action=query&titles=File%3AMetro_Cable_Parque_Arvi_-_panoramio.jpg&prop=imageinfo&iiprop=url%7Cextmetadata&format=json'
p=subprocess.run(['curl','--fail','--location','--max-time','30','--user-agent','ExplorandoLugares-reference-audit/1.0','--output',str(r/'fuente-arvi-panoramio-v7.json'),url],capture_output=True,text=True)
print('Consulta metadata panoramio:',p.returncode)
if p.returncode:print(p.stderr[-800:])
