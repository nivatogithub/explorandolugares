"""Public pages whose canonical implementation is a PHP component."""
from pathlib import Path

ROUTES = (
    '', 'etapas/', 'planea-tu-visita/', 'privacidad/',
    'negocios-locales/', 'negocios-locales/por-que-participar/',
    'negocios-locales/impulsa-tu-destino/', 'negocios-locales/proceso/',
    'negocios-locales/suscripciones/', 'negocios-locales/interes/',
    'negocios-locales/impulsor/', 'cobertura/colombia/',
)
PREFIXES = ('', 'en/', 'pt-br/')

def legacy_html_paths():
    return {prefix + route + 'index.html' for prefix in PREFIXES for route in ROUTES + ('negocios-locales/planes/',)}

def php_page_exists(public: Path, prefix: str, route: str) -> bool:
    if route == 'negocios-locales/':
        return (public / 'business/index.php').is_file()
    return (public / prefix / route / 'index.php').is_file()
