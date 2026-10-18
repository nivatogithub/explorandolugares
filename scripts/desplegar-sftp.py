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


def rewrite_private_app_includes(public_root, remote_base):
    """Point public PHP entrypoints to the private app directory on the host."""
    prefixes = [
        re.compile(r'''dirname\s*\(\s*__DIR__\s*\)\s*\.\s*(['"])/app/'''),
        re.compile(r'''dirname\s*\(\s*__DIR__\s*,\s*2\s*\)\s*\.\s*(['"])/app/'''),
        re.compile(r'''__DIR__\s*\.\s*(['"])/\.\./app/'''),
    ]
    for entry in public_root.rglob('*.php'):
        source = entry.read_text()
        updated = source
        for pattern in prefixes:
            updated = pattern.sub(lambda match: match.group(1) + remote_base + '/app/', updated)
        if updated != source:
            entry.write_text(updated)
        if any(pattern.search(updated) for pattern in prefixes):
            raise ValueError('Quedó una ruta relativa a app/ en un PHP público; se detuvo el empaquetado.')


def remote_path(config, relative):
    path = PurePosixPath(relative)
    if path.is_absolute() or not path.parts or path.parts[0] not in {'public', 'app'} or '..' in path.parts:
        raise ValueError('Ruta de archivo fuera del paquete permitido.')
    if path.parts[0] == 'public':
        return str(PurePosixPath(public_path(config), *path.parts[1:]))
    return str(PurePosixPath(config['remote_base'], *path.parts))


def build(output, config=None):
    source_folders = ['ensayos/v1', 'backend', 'content', 'scripts', 'design/portal', 'preprod']
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
        rewrite_private_app_includes(output / 'public', config['remote_base'])
    files = []
    allowed = {'.html', '.php', '.css', '.js', '.json', '.svg', '.webp', '.png', '.txt', '.xml', '.zip', '.geojson'}
    for kind in ('public', 'app'):
        for source in sorted((output / kind).rglob('*')):
            if source.is_symlink():
                raise ValueError('No transferir enlaces simbólicos.')
            if not source.is_file():
                continue
            if source.name != '.htaccess' and source.suffix not in allowed and not (kind == 'app' and (source.suffix in {'.sql', '.md'} or source.relative_to(output / kind).as_posix() in {'analytics/geoip/country.mmdb', 'analytics/vendor/maxmind/LICENSE'})):
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


def obsolete_files(previous, current):
    """Return only previously manifested files absent from the new full package."""
    current_names = {item['relative'] for item in current}
    return [item for item in (previous or {}).get('files', []) if item['relative'] not in current_names]


def validate_removals(removed, allowed):
    """A missing source must never silently delete a live feature."""
    unauthorized = set(removed) - set(allowed)
    if unauthorized:
        raise ValueError('Despliegue bloqueado: faltan archivos de producción en el paquete. Reconciliar las fuentes o autorizar cada retiro con --allow-remove: ' + ', '.join(sorted(unauthorized)[:8]))


def select_files(files, requested_paths):
    """Limit a deployment to explicit package paths without wildcard matching."""
    if not requested_paths:
        return files
    requested = set(requested_paths)
    available = {item['relative'] for item in files}
    invalid = sorted(requested - available)
    if invalid:
        raise ValueError('Las rutas --only no existen en el paquete: ' + ', '.join(invalid))
    for relative in requested:
        remote_path({'remote_base': '/home/deploy/site', 'public_directory': 'public'}, relative)
    return [item for item in files if item['relative'] in requested]


def create_partial_manifest(config, current, previous, uploaded, commit=None):
    """Record only what is actually live; retain prior hashes for untouched files."""
    old_files = {item['relative']: item for item in (previous or {}).get('files', [])}
    for item in uploaded:
        old_files[item['relative']] = {
            'relative': item['relative'], 'bytes': item['bytes'], 'sha256': item['sha256']
        }
    current_names = {item['relative'] for item in current}
    return {
        'schema_version': 1, 'site': 'explorandolugares', 'host': config['host'],
        'remote_base': config['remote_base'], 'public_root': public_path(config),
        'deployed_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'commit': commit, 'file_count': len(old_files),
        'removed_not_deleted': sorted(set(old_files) - current_names),
        'files': sorted(old_files.values(), key=lambda item: item['relative']),
    }


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
    def upload_order(entry):
        relative=entry[0]['relative']
        # Dependencies first: private services, assets, pages, and routing last.
        priority=0 if relative.startswith('app/') else (1 if relative.startswith('public/assets/') else (3 if relative=='public/.htaccess' else 2))
        return priority,relative
    entries.sort(key=upload_order)
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
    return {'schema_version': 1, 'site': 'explorandolugares', 'host': config['host'],
            'remote_base': config['remote_base'], 'public_root': public_path(config),
            'deployed_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
            'commit': commit, 'file_count': len(files), 'removed_not_deleted': [],
            'files': [{'relative': item['relative'], 'bytes': item['bytes'], 'sha256': item['sha256']} for item in files]}


def deployment_commands(config, files, local_manifest, obsolete=()):
    commands = upload_commands(config, files)
    for item in obsolete:
        commands.append(f'rm {quote(remote_path(config, item["relative"]))}')
    commands.append(f'put {quote(local_manifest)} {quote(config["remote_base"] + "/" + MANIFEST_NAME)}')
    return commands


def require_approved_preprod(approved_ref):
    current_branch = subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()
    current_commit = subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
    if current_branch != 'preprod' or not approved_ref:
        raise ValueError('Producción requiere rama preprod y --approved-preprod COMMIT después de aprobación humana local. Lee docs/PREPRODUCCION.md.')
    approved = subprocess.check_output(['git','rev-parse',approved_ref+'^{commit}'],cwd=ROOT,text=True).strip()
    if approved != current_commit or subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip():
        raise ValueError('El commit aprobado debe ser HEAD y el árbol de trabajo debe estar limpio.')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, default=ROOT / '.local/sftp.json')
    parser.add_argument('--approved-preprod', metavar='COMMIT', help='Commit exacto de preprod aprobado por el propietario para producción.')
    parser.add_argument('--deploy', action='store_true', help='Sube directamente solo archivos nuevos o modificados.')
    parser.add_argument('--only', action='append', default=[], metavar='RUTA',
                        help='Limita una entrega por ruta exacta del paquete (se puede repetir).')
    parser.add_argument('--force-file', action='append', default=[], metavar='RUTA',
                        help='Vuelve a subir una ruta exacta para reparar una diferencia verificada en el hosting.')
    parser.add_argument('--allow-remove', action='append', default=[], metavar='RUTA',
                        help='Autoriza retirar una ruta obsoleta exacta; nunca elimina por omisión silenciosa.')
    args = parser.parse_args(argv)
    if args.deploy:require_approved_preprod(args.approved_preprod)
    config = load_config(args.config.expanduser().resolve())
    subprocess.run([sys.executable, str(ROOT / 'scripts/verificar-antes-de-desplegar.py')], cwd=ROOT, check=True)
    with tempfile.TemporaryDirectory(prefix='explorando-deploy-') as temporary:
        output = Path(temporary) / 'build'
        files = build(output, config)
        previous = latest_local_manifest(ROOT, config)
        if args.only and not previous:
            raise ValueError('Una entrega parcial requiere el manifiesto del último despliegue; primero establece una comparación completa.')
        scoped = select_files(files, args.only)
        forced = {item['relative'] for item in select_files(files, args.force_file)} if args.force_file else set()
        if forced - {item['relative'] for item in scoped}:
            raise ValueError('Una reparación debe estar dentro de las rutas seleccionadas para el despliegue.')
        client = None
        if args.deploy:
            require_approved_preprod(args.approved_preprod)
            known_hosts = Path(config['known_hosts_file']).expanduser()
            if not known_hosts.is_file():
                raise ValueError('Falta el archivo de huellas verificadas.')
            client = SFTP(config)
        changed = changed_files(previous, files) if previous else files
        changed_paths = {item['relative'] for item in changed} | forced
        selected = [item for item in scoped if item['relative'] in changed_paths]
        byte_count = sum(item['bytes'] for item in selected)
        obsolete = [] if args.only else obsolete_files(previous, files)
        removed = sorted(item['relative'] for item in obsolete)
        if args.deploy:
            validate_removals(removed, args.allow_remove)
        print(f'Destino: {config["host"]} → {public_path(config)}')
        print(f'Archivos a subir: {len(selected)} de {len(files)} ({byte_count / 1024 / 1024:.2f} MiB)')
        print(f'Archivos obsoletos del manifiesto que se retirarían: {len(removed)}')
        for relative in removed:
            print(f'  retirar: {relative}')
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
        manifest = (create_partial_manifest(config, files, previous, selected, git_commit())
                    if args.only else create_manifest(config, files, previous, git_commit()))
        manifest_text = json.dumps(manifest, indent=2, ensure_ascii=False) + '\n'
        local_manifest = ROOT / '.local/deployment-manifest.json'
        local_manifest.parent.mkdir(parents=True, exist_ok=True)
        temp_manifest = Path(temporary) / MANIFEST_NAME
        temp_manifest.write_text(manifest_text)
        # Reject stale local inventories before the first remote mutation.
        remote_manifest = Path(temporary) / 'current-remote-manifest.json'
        client.run([f'get {quote(config["remote_base"] + "/" + MANIFEST_NAME)} {quote(remote_manifest)}'])
        actual = parse_manifest(remote_manifest, config)
        if actual != previous:
            raise ValueError('El manifiesto remoto cambió o no coincide con el local; reconciliar antes de publicar. No se subió ningún archivo.')
        # Changed content and its new inventory travel through one SFTP session.
        client.run(deployment_commands(config, selected, temp_manifest, obsolete))
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
