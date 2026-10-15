#!/usr/bin/env python3
"""Despliegue directo e incremental de Explorando Lugares por SFTP."""
from pathlib import Path, PurePosixPath
from datetime import datetime, timezone
import argparse
import hashlib
import json
import re
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
MANIFEST_NAME = '.explorandolugares-deploy.json'
SAFE_PATH = re.compile(r'^/[A-Za-z0-9_./-]+$')


def load_config(path):
    data = json.loads(path.read_text())
    allowed = {'site', 'host', 'port', 'user', 'remote_base', 'public_directory', 'identity_file', 'known_hosts_file', 'public_root'}
    if set(data) - allowed:
        raise ValueError('La configuración contiene campos desconocidos. No guardar contraseñas aquí.')
    required = {'site', 'host', 'port', 'user', 'remote_base', 'public_directory', 'known_hosts_file'}
    if not required.issubset(data):
        raise ValueError('Configuración incompleta. Usa deploy/sftp.example.json como plantilla.')
    if data.get('site') != 'explorandolugares':
        raise ValueError('La configuración debe pertenecer a Explorando Lugares.')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', data.get('host', '')) or data['host'] == 'SERVIDOR_SFTP':
        raise ValueError('Completa el servidor SFTP real.')
    if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]*', data.get('user', '')) or data['user'] == 'USUARIO_SFTP':
        raise ValueError('Completa el usuario SFTP real.')
    if type(data.get('port')) is not int or not 1 <= data['port'] <= 65535:
        raise ValueError('Puerto inválido.')
    base = data.get('remote_base', '')
    parts = PurePosixPath(base).parts
    if not SAFE_PATH.fullmatch(base) or '..' in parts or '.' in base.split('/') or len(parts) < 4 or base.endswith('/') or 'USUARIO' in base:
        raise ValueError('Usa una carpeta remota absoluta, exclusiva y sin segmentos relativos.')
    if any(part.lower() in {'public_html', 'www', 'htdocs', 'httpdocs'} for part in parts):
        raise ValueError('remote_base debe estar fuera de la raíz web.')
    directory = data.get('public_directory', '')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_-]*', directory) or directory in {'app', 'private'}:
        raise ValueError('Nombre de carpeta pública inválido.')
    public_root = data.get('public_root')
    if public_root is not None:
        root_parts = PurePosixPath(public_root).parts if isinstance(public_root, str) else ()
        if (not isinstance(public_root, str) or not SAFE_PATH.fullmatch(public_root)
                or '..' in root_parts or '.' in public_root.split('/') or public_root.endswith('/')
                or root_parts[-2:] != ('public_html', 'explorandolugares.com')
                or len(root_parts) < 5 or root_parts[:3] != parts[:3]):
            raise ValueError('public_root debe ser public_html/explorandolugares.com dentro de la misma cuenta.')
    for key in ('identity_file', 'known_hosts_file'):
        value = data.get(key, '')
        if not isinstance(value, str) or any(char in value for char in '\r\n\x00'):
            raise ValueError('Ruta local inválida.')
    return data


def quote(value):
    value = str(value)
    if any(char in value for char in '\r\n\x00*?[]'):
        raise ValueError('Ruta SFTP inválida.')
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"') + '"'


class SFTP:
    def __init__(self, config):
        self.config = config
        self.command = [
            'sftp', '-F', '/dev/null', '-b', '-', '-P', str(config['port']),
            '-oBatchMode=yes', '-oStrictHostKeyChecking=yes', '-oConnectTimeout=20',
            '-oServerAliveInterval=15', '-oServerAliveCountMax=3',
            '-oForwardAgent=no', '-oClearAllForwardings=yes', '-oPermitLocalCommand=no',
            '-oUserKnownHostsFile=' + str(Path(config['known_hosts_file']).expanduser().resolve()),
        ]
        if config.get('identity_file'):
            key = Path(config['identity_file']).expanduser().resolve()
            if not key.is_file():
                raise ValueError('No existe la clave SSH configurada.')
            self.command += ['-oIdentitiesOnly=yes', '-i', str(key)]
        self.command += [config['user'] + '@' + config['host']]

    def run(self, commands):
        if not shutil.which('sftp'):
            raise ValueError('OpenSSH sftp no está disponible.')
        result = subprocess.run(self.command, input='\n'.join(commands) + '\n', text=True, capture_output=True, timeout=1800)
        if result.returncode:
            raise RuntimeError('SFTP no terminó. Revisa conexión, huella y permisos.\n' + result.stderr[-2000:])
        return result.stdout


def public_path(config):
    return config.get('public_root') or config['remote_base'] + '/' + config['public_directory']


def remote_path(config, relative):
    path = PurePosixPath(relative)
    if path.is_absolute() or not path.parts or path.parts[0] not in {'public', 'app'} or '..' in path.parts:
        raise ValueError('Ruta de archivo fuera del paquete permitido.')
    if path.parts[0] == 'public':
        return str(PurePosixPath(public_path(config), *path.parts[1:]))
    return str(PurePosixPath(config['remote_base'], *path.parts))


def build(output, config=None):
    source_folders = ['ensayos/v1', 'backend', 'content', 'scripts']
    for folder in source_folders:
        source = ROOT / folder
        if source.is_symlink() or any(item.is_symlink() for item in source.rglob('*')):
            raise ValueError('La fuente contiene enlaces simbólicos; no se empaqueta.')
    source_temp = Path(tempfile.mkdtemp(prefix='explorando-deploy-source-'))
    source_root = source_temp / 'project'
    try:
        for folder in source_folders:
            shutil.copytree(ROOT / folder, source_root / folder, ignore=shutil.ignore_patterns('__pycache__', '.DS_Store'))
        subprocess.run([sys.executable, str(source_root / 'scripts/preparar-hosting.py'), '--output', str(output)], check=True)
    finally:
        shutil.rmtree(source_temp, ignore_errors=True)
    if config and config.get('public_root'):
        routes = ['api/participacion.php', 'admin/index.php', 'admin/base-datos.php', 'admin/destinos.php', 'admin/negocios.php',
                  'admin/suscripciones.php', 'admin/usuarios.php', 'admin/importar-negocios.php', 'admin/reclamaciones.php',
                  'admin/solicitudes-cuenta.php', 'business/index.php', 'business/register.php', 'business/login.php',
                  'business/dashboard.php', 'business/logout.php']
        for relative in routes:
            entry = output / 'public' / relative
            text = entry.read_text()
            old = "require dirname(__DIR__,2).'/app/bootstrap.php';"
            if old not in text:
                raise ValueError('Entrada PHP inesperada; se detuvo el empaquetado.')
            entry.write_text(text.replace(old, "require '" + config['remote_base'] + "/app/bootstrap.php';", 1))
        claim = output / 'public/reclamar-negocio.php'
        text = claim.read_text()
        old = "require __DIR__.'/../app/bootstrap.php';"
        if old not in text:
            raise ValueError('Entrada pública inesperada; se detuvo el empaquetado.')
        claim.write_text(text.replace(old, "require '" + config['remote_base'] + "/app/bootstrap.php';", 1))
    files = []
    allowed = {'.html', '.php', '.css', '.js', '.json', '.svg', '.webp', '.png', '.txt', '.xml', '.zip'}
    for kind in ('public', 'app'):
        for source in sorted((output / kind).rglob('*')):
            if source.is_symlink():
                raise ValueError('No transferir enlaces simbólicos.')
            if not source.is_file():
                continue
            if source.name != '.htaccess' and source.suffix not in allowed and not (kind == 'app' and source.suffix in {'.sql', '.md'}):
                raise ValueError('Archivo no permitido: ' + str(source))
            files.append({'relative': source.relative_to(output).as_posix(), 'local': str(source),
                          'bytes': source.stat().st_size, 'sha256': hashlib.sha256(source.read_bytes()).hexdigest()})
    if not (output / 'public/index.html').is_file() or not (output / 'app/bootstrap.php').is_file():
        raise ValueError('Entrega incompleta.')
    return files


def latest_local_manifest(root=ROOT, config=None):
    path = root / '.local/deployment-manifest.json'
    if path.is_file():
        try:
            data = json.loads(path.read_text())
        except (OSError, json.JSONDecodeError):
            data = None
        if data and data.get('site') == 'explorandolugares' and data.get('files'):
            return parse_manifest(path, config) if config else data
    # One-time bridge from the previous deploy script; after this, one small
    # manifest replaces the per-release builds and local copies.
    candidates = []
    for old_manifest in (root / '.local/deploy').glob('????????T??????Z-*/manifest.json'):
        try:
            data = json.loads(old_manifest.read_text())
        except (OSError, json.JSONDecodeError):
            continue
        if (data.get('site') == 'explorandolugares' and data.get('status') == 'activated'
                and data.get('files') and config and data.get('host') == config['host']
                and data.get('remote_base') == config['remote_base']):
            candidates.append((old_manifest.stat().st_mtime, data))
    if candidates:
        old = max(candidates, key=lambda item: item[0])[1]
        return {'site': old['site'], 'deployed_at': old.get('release'), 'host': old.get('host'),
                'remote_base': old.get('remote_base'), 'files': old['files']}
    return None


def parse_manifest(path, config):
    if not path.is_file():
        return None
    try:
        data = json.loads(path.read_text())
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError('El manifiesto remoto/local no se puede leer; no se subió ningún archivo.') from error
    if (data.get('site') != 'explorandolugares' or data.get('host') != config['host']
            or data.get('remote_base') != config['remote_base'] or not isinstance(data.get('files'), list)):
        raise ValueError('El manifiesto pertenece a otro sitio o destino; no se subió ningún archivo.')
    for item in data['files']:
        if not isinstance(item, dict) or not isinstance(item.get('relative'), str) or not re.fullmatch(r'[a-f0-9]{64}', item.get('sha256', '')):
            raise ValueError('El manifiesto contiene una entrada inválida.')
        remote_path(config, item['relative'])
    return data


def changed_files(previous, current):
    old = {item['relative']: item['sha256'] for item in (previous or {}).get('files', [])}
    return [item for item in current if old.get(item['relative']) != item['sha256']]


def upload_commands(config, files):
    directories = set()
    entries = []
    for item in files:
        target = PurePosixPath(remote_path(config, item['relative']))
        root = PurePosixPath(public_path(config) if item['relative'].startswith('public/') else config['remote_base'])
        parent = target.parent
        while parent != root and str(parent) != '/':
            directories.add(str(parent))
            parent = parent.parent
        entries.append((item, str(target)))
    entries.sort(key=lambda entry: (0 if entry[0]['relative'].startswith('app/') else 1, entry[0]['relative']))
    commands = [f'-mkdir {quote(path)}' for path in sorted(directories, key=lambda value: (value.count('/'), value))]
    commands += [f'put {quote(item["local"])} {quote(target)}' for item, target in entries]
    return commands


def git_commit():
    try:
        result = subprocess.run(['git', 'rev-parse', '--short', 'HEAD'], cwd=ROOT, capture_output=True, text=True, check=True)
        return result.stdout.strip()
    except (OSError, subprocess.CalledProcessError):
        return None


def create_manifest(config, files, previous, commit=None):
    old_names = {item['relative'] for item in (previous or {}).get('files', [])}
    names = {item['relative'] for item in files}
    return {'schema_version': 1, 'site': 'explorandolugares', 'host': config['host'],
            'remote_base': config['remote_base'], 'public_root': public_path(config),
            'deployed_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
            'commit': commit, 'file_count': len(files), 'removed_not_deleted': sorted(old_names - names),
            'files': [{'relative': item['relative'], 'bytes': item['bytes'], 'sha256': item['sha256']} for item in files]}


def deployment_commands(config, files, local_manifest):
    commands = upload_commands(config, files)
    commands.append(f'put {quote(local_manifest)} {quote(config["remote_base"] + "/" + MANIFEST_NAME)}')
    return commands


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, default=ROOT / '.local/sftp.json')
    parser.add_argument('--deploy', action='store_true', help='Sube directamente solo archivos nuevos o modificados.')
    args = parser.parse_args(argv)
    config = load_config(args.config.expanduser().resolve())
    subprocess.run([sys.executable, str(ROOT / 'scripts/verificar-antes-de-desplegar.py')], cwd=ROOT, check=True)
    with tempfile.TemporaryDirectory(prefix='explorando-deploy-') as temporary:
        output = Path(temporary) / 'build'
        files = build(output, config)
        previous = latest_local_manifest(ROOT, config)
        client = None
        if args.deploy:
            known_hosts = Path(config['known_hosts_file']).expanduser()
            if not known_hosts.is_file():
                raise ValueError('Falta el archivo de huellas verificadas.')
            client = SFTP(config)
        selected = changed_files(previous, files) if previous else files
        byte_count = sum(item['bytes'] for item in selected)
        old_names = {item['relative'] for item in (previous or {}).get('files', [])}
        removed = sorted(old_names - {item['relative'] for item in files})
        print(f'Destino: {config["host"]} → {public_path(config)}')
        print(f'Archivos a subir: {len(selected)} de {len(files)} ({byte_count / 1024 / 1024:.2f} MiB)')
        print(f'Archivos obsoletos que se conservarán: {len(removed)}')
        print(f'Manifiesto local: {ROOT / ".local/deployment-manifest.json"}')
        old_hashes = {item['relative']: item['sha256'] for item in (previous or {}).get('files', [])}
        for item in selected:
            change = 'nuevo' if item['relative'] not in old_hashes else 'modificado'
            print(f'  {change}: {item["relative"]}')
        if not args.deploy:
            print('Vista previa: no se conectó ni modificó el hosting. Para publicar, ejecuta con --deploy.')
            return 0
        if not selected:
            print('El contenido ya coincide; no se subieron archivos del sitio.')
        manifest = create_manifest(config, files, previous, git_commit())
        manifest_text = json.dumps(manifest, indent=2, ensure_ascii=False) + '\n'
        local_manifest = ROOT / '.local/deployment-manifest.json'
        local_manifest.parent.mkdir(parents=True, exist_ok=True)
        temp_manifest = Path(temporary) / MANIFEST_NAME
        temp_manifest.write_text(manifest_text)
        # Changed content and its new inventory travel through one SFTP session.
        client.run(deployment_commands(config, selected, temp_manifest))
        local_manifest.write_text(manifest_text)
        print(f'Despliegue directo completado. Manifiesto actualizado en el hosting y en {local_manifest}.')
        print('Verifica visualmente el sitio y las acciones críticas; el envío SFTP no prueba PHP ni la base de datos.')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print('Interrumpido. El manifiesto no se actualizó; vuelve a ejecutar para enviar los archivos pendientes.', file=sys.stderr)
        sys.exit(130)
    except (ValueError, OSError, RuntimeError, subprocess.SubprocessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
