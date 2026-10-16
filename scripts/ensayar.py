#!/usr/bin/env python3
"""Sirve únicamente ensayos/v1/ en la interfaz local, sin dependencias externas."""
import argparse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "ensayos" / "v1"


class Handler(SimpleHTTPRequestHandler):
    def translate_path(self, path):
        target = Path(super().translate_path(path)).resolve()
        if not target.is_relative_to(ROOT.resolve()):
            return str(ROOT / "__fuera_del_entorno__")
        return str(target)

    def end_headers(self):
        self.send_header("X-Robots-Tag", "noindex, nofollow")
        self.send_header("Cache-Control", "no-store")
        super().end_headers()

    def list_directory(self, path):
        self.send_error(403, "Listado de carpetas deshabilitado")
        return None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8000)
    args = parser.parse_args()
    with ThreadingHTTPServer(("127.0.0.1", args.port), partial(Handler, directory=str(ROOT))) as server:
        print(f"Ensayos locales: http://127.0.0.1:{args.port}/ — detener con Ctrl+C", flush=True)
        server.serve_forever()


if __name__ == "__main__":
    main()
