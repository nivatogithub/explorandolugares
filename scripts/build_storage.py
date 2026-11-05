"""Bound local build storage without deleting another process's artifacts."""
from contextlib import contextmanager
from pathlib import Path
import fcntl
import json
import os
import signal
import shutil
import subprocess
import tempfile
import time

GIB = 1024 ** 3
RESERVE_BYTES = 2 * GIB
MAX_RETAINED_PACKAGES = 3
MAX_RETAINED_BYTES = GIB


def run_owned_command(command, **options):
    """Wait for a new child; on interruption stop only its own process group."""
    process = subprocess.Popen(command, start_new_session=True, **options)
    try:
        return process.wait()
    finally:
        if process.poll() is None:
            try:
                os.killpg(process.pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                process.wait()


def tree_bytes(root):
    total = 0
    for directory, dirs, files in os.walk(root, followlinks=False):
        dirs[:] = [d for d in dirs if d not in {'.local', '__pycache__', 'node_modules', '.git'}
                   and not (Path(directory) / d).is_symlink()]
        for name in files:
            path = Path(directory) / name
            if not path.is_symlink():
                total += path.stat().st_size
    return total


def state_directory(root):
    # All worktrees on this machine cooperate; production credentials are unused.
    for parent in (root, *root.parents):
        if (parent / '.local/environments/reservations.json').is_file():
            return parent / '.local/build-storage'
    return root / '.local/build-storage'


@contextmanager
def exclusive_build(state, timeout=60):
    state.mkdir(parents=True, exist_ok=True)
    if state.is_symlink():
        raise ValueError('El estado de construcciones no puede ser un enlace.')
    lock = state / 'build.lock'
    descriptor = os.open(lock, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'a') as stream:
        deadline = time.monotonic() + timeout
        while True:
            try:
                fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
                break
            except BlockingIOError:
                if time.monotonic() >= deadline:
                    raise ValueError('Hay otra construcción en curso. Reintenta cuando termine; no se borró nada.')
                time.sleep(0.1)
        try:
            yield
        finally:
            fcntl.flock(stream, fcntl.LOCK_UN)


def read_registry(path):
    if not path.exists():
        return []
    if path.is_symlink():
        raise ValueError('El registro de construcciones no puede ser un enlace.')
    rows = json.loads(path.read_text())
    if not isinstance(rows, list) or any(not isinstance(r, dict) or
            not isinstance(r.get('path'), str) or not isinstance(r.get('root'), str) for r in rows):
        raise ValueError('Registro de construcciones inválido; no se borró ningún paquete.')
    return [row for row in rows if Path(row['path']).is_dir()]


def write_registry(path, rows):
    with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, prefix='registry-', delete=False) as stream:
        temporary = Path(stream.name)
        json.dump(rows, stream, indent=2)
        stream.write('\n')
    try:
        os.replace(temporary, path)
    finally:
        temporary.unlink(missing_ok=True)


@contextmanager
def reserve_build(root, output, inputs, *, retained=True, state=None):
    """Serialize cooperating builders and limit *new* retained packages per checkout.

    The registry never grants deletion permission. Old/unregistered packages,
    active releases, uploads, databases and other worktrees are never pruned.
    """
    root, output = Path(root).resolve(), Path(output).resolve()
    state = state or state_directory(root)
    with exclusive_build(state):
        registry = state / 'retained.json'
        rows = read_registry(registry)
        own = [r for r in rows if r['root'] == str(root)]
        retained_bytes = sum(tree_bytes(Path(r['path'])) for r in own)
        if retained and (len(own) >= MAX_RETAINED_PACKAGES or retained_bytes >= MAX_RETAINED_BYTES):
            raise ValueError('Este checkout ya conserva el máximo de paquetes de revisión '
                             '(3 paquetes o 1 GiB). Para pruebas usa scripts/run-test-package.py; '
                             'no se elimina ningún candidato automáticamente.')
        source_bytes = sum(tree_bytes(root / name) for name in inputs)
        estimate = max(256 * 1024**2, source_bytes * 3)
        # Snapshot and output may live on different filesystems.
        for folder in (output.parent, Path(tempfile.gettempdir())):
            while not folder.exists():
                folder = folder.parent
            if shutil.disk_usage(folder).free < RESERVE_BYTES + estimate:
                raise ValueError('Espacio insuficiente para construir conservando 2 GiB libres '
                                 'y el espacio estimado del paquete. No se inició la copia ni se borró nada.')
        yield
        if retained:
            size = tree_bytes(output)
            rows.append({'root': str(root), 'path': str(output), 'bytes': size,
                         'created_at': time.time()})
        write_registry(registry, rows)


@contextmanager
def temporary_package(root):
    """Only this invocation's unique test directory is removed, even on failure."""
    directory = Path(root) / '.local/test-runs'
    directory.mkdir(parents=True, exist_ok=True)
    if directory.is_symlink():
        raise ValueError('La carpeta de pruebas no puede ser un enlace.')
    with tempfile.TemporaryDirectory(prefix='test-', dir=directory) as temporary:
        yield Path(temporary) / 'package'
