#!/usr/bin/env python3
"""Inspecciona y limpia releases antiguos de Explorando Lugares por SFTP."""
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
import argparse
import importlib.util
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
DEPLOY_SCRIPT = ROOT / 'scripts/desplegar-sftp.py'
RELEASES = '.el-releases'
TRASH = '.el-trash'
RELEASE_ID = re.compile(r'^\d{8}T\d{6}Z-[A-Za-z0-9_-]{1,32}$')
BATCH_ID = re.compile(r'^\d{8}T\d{6}Z$')
SAFE_NAME = re.compile(r'^[A-Za-z0-9._-]+$')


def deployment_module():
    spec = importlib.util.spec_from_file_location('explorando_deploy', DEPLOY_SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def parse_listing(output):
    """Parsea nombres de `sftp ls -la`; rechaza formatos o rutas ambiguas."""
    entries = []
    for raw in output.splitlines():
        line = raw.strip()
        if not line or line.startswith('sftp>') or line in {'.', '..'}:
            continue
        fields = line.split(maxsplit=8)
        if len(fields) != 9 or not re.fullmatch(r'[dl-][rwxstST-]{9}', fields[0]):
            continue
        name = fields[8].split(' -> ', 1)[0] if fields[0].startswith('l') else fields[8]
        if name in {'.', '..'}:
            continue
        if not SAFE_NAME.fullmatch(name):
            raise ValueError('El hosting contiene un nombre de archivo ambiguo; se detuvo sin cambios.')
        kind = 'directory' if fields[0].startswith('d') else 'file' if fields[0].startswith('-') else 'other'
        entries.append({'name': name, 'kind': kind, 'bytes': int(fields[4])})
    return entries


def list_directory(client, absolute):
    return parse_listing(client.run([f'ls -la {deployment_module().quote(absolute)}']))


def list_directories(client, paths):
    """Lista un nivel completo con una sola conexión SFTP."""
    deploy = deployment_module()
    expected = set(paths)
    output = client.run([f'ls -la {deploy.quote(path)}' for path in paths])
    sections, current, lines = {}, None, []
    for line in output.splitlines():
        if line.startswith('sftp> ls -la '):
            if current is not None:
                sections[current] = parse_listing('\n'.join(lines))
            quoted = line[len('sftp> ls -la '):].strip()
            current = quoted[1:-1] if quoted.startswith('"') and quoted.endswith('"') else quoted
            if current not in expected:
                raise ValueError('La respuesta SFTP no coincide con las carpetas solicitadas.')
            lines = []
        elif current is not None:
            lines.append(line)
    if current is not None:
        sections[current] = parse_listing('\n'.join(lines))
    if set(sections) != expected:
        raise ValueError('No se pudieron listar todas las carpetas de forma segura.')
    return sections


def tree_commands(client, directory):
    """Produce borrado SFTP bottom-up, restringido a una carpeta de papelera."""
    files, directories, total_bytes = [], [], 0
    pending = [directory]
    while pending:
        batch = list_directories(client, pending)
        next_level = []
        for path, entries in batch.items():
            directories.append(path)
            for entry in entries:
                child = str(PurePosixPath(path, entry['name']))
                if entry['kind'] == 'directory':
                    next_level.append(child)
                elif entry['kind'] == 'file':
                    files.append(child)
                    total_bytes += entry['bytes']
                else:
                    raise ValueError('La papelera contiene enlaces u objetos no admitidos; no se borró nada.')
        pending = next_level
    quote = deployment_module().quote
    commands = [f'rm {quote(path)}' for path in files] + [f'rmdir {quote(path)}' for path in reversed(directories)]
    return commands, total_bytes


def run(config_path, action=None, target=None):
    deploy = deployment_module()
    config = deploy.load_config(config_path)
    client = deploy.SFTP(config)
    base = config['remote_base']
    releases_path = str(PurePosixPath(base, RELEASES))
    trash_path = str(PurePosixPath(base, TRASH))

    if action is None:
        for label, path in (('Releases antiguos', releases_path), ('Papelera temporal', trash_path)):
            print(label + ':')
            try:
                items = list_directory(client, path)
            except Exception as error:
                if 'not found' in str(error).lower() or 'no such file' in str(error).lower():
                    print('  (no existe)')
                    continue
                raise
            if not items:
                print('  (vacío)')
            for item in items:
                print(f"  {item['name']}  {item['kind']}  {item['bytes']} bytes")
        print('Solo se manipulan releases fechados dentro de estas dos carpetas; nunca public/ ni app/ activos.')
        return

    if action == 'quarantine':
        if not target or not RELEASE_ID.fullmatch(target):
            raise ValueError('Indica un ID de release fechado que aparezca en el listado.')
        candidates = {item['name'] for item in list_directory(client, releases_path)
                      if item['kind'] == 'directory' and RELEASE_ID.fullmatch(item['name'])}
        if target not in candidates:
            raise ValueError('Ese release no existe como carpeta directa de .el-releases; no se cambió nada.')
        batch = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        batch_path = str(PurePosixPath(trash_path, batch))
        client.run([f'-mkdir {deploy.quote(trash_path)}', f'mkdir {deploy.quote(batch_path)}',
                    f'rename {deploy.quote(str(PurePosixPath(releases_path, target)))} '
                    f'{deploy.quote(str(PurePosixPath(batch_path, target)))}'])
        print(f'Release apartado temporalmente: {batch}/{target}')
        print('La papelera sigue usando espacio hasta que la restaures o la purgues explícitamente.')
        return

    if action == 'restore':
        if not target or not BATCH_ID.fullmatch(target):
            raise ValueError('Indica el ID de papelera que apareció al apartar un release.')
        batch_path = str(PurePosixPath(trash_path, target))
        items = list_directory(client, batch_path)
        releases = [item['name'] for item in items if item['kind'] == 'directory' and RELEASE_ID.fullmatch(item['name'])]
        if len(releases) != 1 or len(items) != 1:
            raise ValueError('La papelera debe contener exactamente un release reconocido para restaurarlo.')
        release_path = str(PurePosixPath(releases_path, releases[0]))
        try:
            list_directory(client, release_path)
        except Exception as error:
            if 'not found' not in str(error).lower() and 'no such file' not in str(error).lower():
                raise
        else:
            raise ValueError('Ya existe una carpeta con ese ID en .el-releases; no se sobrescribió.')
        client.run([f'-mkdir {deploy.quote(releases_path)}',
                    f'rename {deploy.quote(str(PurePosixPath(batch_path, releases[0])))} {deploy.quote(release_path)}',
                    f'rmdir {deploy.quote(batch_path)}'])
        print(f'Release restaurado: {RELEASES}/{releases[0]}')
        return

    if action == 'purge':
        if not target or not BATCH_ID.fullmatch(target):
            raise ValueError('Indica el ID exacto de papelera que quieres purgar.')
        batch_path = str(PurePosixPath(trash_path, target))
        items = list_directory(client, batch_path)
        if not items or any(item['kind'] != 'directory' or not RELEASE_ID.fullmatch(item['name']) for item in items):
            raise ValueError('La papelera no contiene únicamente releases reconocidos; no se borró nada.')
        commands, total_bytes = [], 0
        for item in items:
            subtree_commands, subtree_bytes = tree_commands(client, str(PurePosixPath(batch_path, item['name'])))
            commands.extend(subtree_commands)
            total_bytes += subtree_bytes
        commands.append(f'rmdir {deploy.quote(batch_path)}')
        client.run(commands)
        print(f'Papelera purgada: {target}. Se liberaron {total_bytes / 1024 / 1024:.2f} MiB; '
              'solo se borró su contenido, no la carpeta pública ni app activa.')
        return

    raise ValueError('Acción desconocida.')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, default=ROOT / '.local/sftp.json')
    actions = parser.add_mutually_exclusive_group()
    actions.add_argument('--quarantine', metavar='RELEASE_ID', help='Mueve un release antiguo a la papelera temporal.')
    actions.add_argument('--restore', metavar='BATCH_ID', help='Restaura el release de una papelera.')
    actions.add_argument('--purge', metavar='BATCH_ID', help='Borra permanentemente el contenido de una papelera.')
    args = parser.parse_args(argv)
    action = 'quarantine' if args.quarantine else 'restore' if args.restore else 'purge' if args.purge else None
    target = args.quarantine or args.restore or args.purge
    run(args.config.expanduser().resolve(), action, target)
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print('Interrumpido; revisa el estado de SFTP antes de repetir.', file=sys.stderr)
        sys.exit(130)
    except (ValueError, OSError, RuntimeError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
