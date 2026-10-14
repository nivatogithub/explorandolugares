#!/usr/bin/env python3
"""Añade los canales públicos de contacto al footer de cada página pública."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "ensayos/v1"

ICON_MAIL = '''<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>'''
ICON_WHATSAPP = '''<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 11.5a8.4 8.4 0 0 1-12.4 7.4L3.5 20l1.2-4.3A8.4 8.4 0 1 1 20.5 11.5Z"></path><path d="M8.8 8.1c.2-.5.5-.5.7-.5h.6c.2 0 .4.1.5.4l.8 1.9c.1.2.1.4 0 .6l-.5.7c-.1.1-.1.3 0 .4.5.9 1.2 1.6 2.1 2.1.1.1.3.1.4 0l.7-.5c.2-.1.4-.1.6 0l1.9.8c.3.1.4.3.4.5v.6c0 .2 0 .5-.5.7-.4.2-1.2.3-2.1 0-1.1-.4-2.4-1.2-3.5-2.3-1.1-1.1-1.9-2.4-2.3-3.5-.3-.9-.2-1.7 0-2.1Z"></path></svg>'''
ICON_INSTAGRAM = '''<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.4" cy="6.6" r="1"></circle></svg>'''

TEXT = {
    "es": {"mail": "Correo", "instagram": "Instagram", "whatsapp": "WhatsApp"},
    "en": {"mail": "Email", "instagram": "Instagram", "whatsapp": "WhatsApp"},
    "pt-BR": {"mail": "E-mail", "instagram": "Instagram", "whatsapp": "WhatsApp"},
}


def language_for(page: Path) -> str:
    parts = page.relative_to(SITE).parts
    if parts and parts[0] == "en":
        return "en"
    if parts and parts[0] == "pt-br":
        return "pt-BR"
    return "es"


def footer_contact(lang: str) -> str:
    labels = TEXT[lang]
    return f'''<nav class="footer-contact" aria-label="{labels['whatsapp']}, {labels['instagram']} y {labels['mail']}">
<a class="footer-contact-link footer-contact-whatsapp" href="https://wa.me/573234870366" target="_blank" rel="noopener noreferrer" aria-label="{labels['whatsapp']}: +57 323 487 0366"><span class="footer-contact-icon" aria-hidden="true">{ICON_WHATSAPP}</span></a>
<a class="footer-contact-link footer-contact-instagram" href="https://www.instagram.com/explorandolugaresco/" target="_blank" rel="noopener noreferrer" aria-label="{labels['instagram']}: @explorandolugaresco"><span class="footer-contact-icon" aria-hidden="true">{ICON_INSTAGRAM}</span></a>
<a class="footer-contact-link footer-contact-email" href="mailto:contacto@explorandolugares.com" aria-label="{labels['mail']}: contacto@explorandolugares.com"><span class="footer-contact-icon" aria-hidden="true">{ICON_MAIL}</span></a>
</nav>'''


def inject(source: str, lang: str) -> str:
    source = re.sub(r'\s*<nav class="footer-contact".*?</nav>', '', source, flags=re.S)
    marker = '<footer><div class="wrap">'
    if marker not in source:
        return source
    return source.replace(marker, marker + footer_contact(lang), 1)


def main() -> None:
    changed = 0
    for page in SITE.rglob("*.html"):
        relative = page.relative_to(SITE)
        if "admin" in relative.parts:
            continue
        source = page.read_text()
        updated = inject(source, language_for(page))
        if updated != source:
            page.write_text(updated)
            changed += 1
    print(f"Footer de contacto actualizado en {changed} páginas públicas.")


if __name__ == "__main__":
    main()
