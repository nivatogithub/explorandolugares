#!/usr/bin/env python3
"""Crea configuración privada sin contraseñas en argumentos ni historial."""
import argparse
import base64
import getpass
import json
import os
from pathlib import Path
import secrets
import subprocess

ROOT = Path(__file__).resolve().parents[1]

def write_config(path, config):
    path = path.resolve()
    if path.name != 'config.php' or path.parent.name != 'private':
        raise ValueError('El destino debe ser private/config.php dentro de una carpeta privada.')
    if any(part in {'public_html', 'public', 'ensayos', 'sources'} for part in path.parts):
        raise ValueError('La configuración debe quedar fuera de las carpetas públicas y sources.')
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    encoded = base64.b64encode(json.dumps(config, ensure_ascii=False).encode()).decode()
    content = "<?php\n// Privado: nunca subir a Git ni a la raíz pública.\nreturn json_decode(base64_decode('" + encoded + "'), true, 512, JSON_THROW_ON_ERROR);\n"
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as handle:
        handle.write(content)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT/'.local/hosting/private/config.php')
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    if args.output.exists():
        parser.error('Ya existe configuración; no se reemplaza.')
    host = input('Servidor MySQL [localhost]: ').strip() or 'localhost'
    database = input('Nombre completo de base de datos cPanel: ').strip()
    user = input('Usuario MySQL: ').strip()
    if not database or not user or any(c in host+database for c in ';\r\n'):
        parser.error('Base, servidor o usuario inválidos.')
    password = getpass.getpass('Contraseña MySQL (oculta): ')
    admin = input('Usuario del administrador: ').strip()
    admin_password = getpass.getpass('Contraseña administrador (14–72 bytes, oculta): ')
    if not admin or not 14 <= len(admin_password.encode()) <= 72:
        parser.error('Usuario o contraseña administrador inválidos.')
    if admin_password != getpass.getpass('Repite la contraseña administrador: '):
        parser.error('Las contraseñas no coinciden.')
    result = subprocess.run([args.php, str(ROOT/'backend/bin/crear-hash.php')], input=admin_password, text=True, capture_output=True, check=True)
    privacy = {key: input(label+': ').strip() for key, label in [('operator','Responsable del tratamiento'),('contact','Contacto para privacidad'),('retention','Plazo de conservación en español'),('retention_en','Plazo de conservación en inglés')]}
    write_config(args.output, {'dsn':f'mysql:host={host};dbname={database};charset=utf8mb4', 'db_user':user, 'db_password':password, 'app_key':secrets.token_hex(32), 'admin_user':admin, 'admin_password_hash':result.stdout.strip(), 'registration_enabled':False, 'local_test':False, 'privacy':privacy})
    print('Configuración privada creada con permisos restringidos. Recepción desactivada. No se subió ningún archivo.')

if __name__ == '__main__':
    main()
