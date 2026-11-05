"""Static pages whose markup is maintained directly in each language."""
from pathlib import PurePosixPath

_DIRECT_PAGES = {'agenda/index.html', 'admin/vista-previa.html'}


def is_direct_static_page(relative):
    parts = PurePosixPath(relative).parts
    if parts and parts[0] in {'en', 'pt-br'}:
        parts = parts[1:]
    return '/'.join(parts) in _DIRECT_PAGES
