#!/usr/bin/env python3
"""Usa ↗ solo para enlaces externos; distingue páginas internas y anclas."""
from pathlib import Path
from html.parser import HTMLParser
from html import escape
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / 'ensayos/v1'

class Normalizador(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.output=[]
        self.anchor_href=None
        self.anchor_parts=[]
    def handle_decl(self, decl): self.output.append('<!'+decl+'>')
    def handle_starttag(self, tag, attrs):
        rendered='<'+tag+(' '+' '.join(k if v is None else f'{k}="{escape(v, quote=True)}"' for k,v in attrs) if attrs else '')+'>'
        if tag=='a':
            self.anchor_href=dict(attrs).get('href','')
            self.anchor_parts=[rendered]
        elif self.anchor_href is not None:
            self.anchor_parts.append(rendered)
        else: self.output.append(rendered)
    def handle_startendtag(self, tag, attrs):
        rendered='<'+tag+(' '+' '.join(k if v is None else f'{k}="{escape(v, quote=True)}"' for k,v in attrs) if attrs else '')+'/>'
        (self.anchor_parts if self.anchor_href is not None else self.output).append(rendered)
    def handle_endtag(self, tag):
        if self.anchor_href is not None:
            self.anchor_parts.append('</'+tag+'>')
            if tag=='a':
                href=self.anchor_href
                parsed=urlsplit(href)
                if not parsed.scheme and not parsed.netloc:
                    arrow='↓' if href.startswith('#') else '→'
                    self.anchor_parts=[part.replace('↗',arrow) for part in self.anchor_parts]
                self.output.extend(self.anchor_parts)
                self.anchor_href=None; self.anchor_parts=[]
        else: self.output.append('</'+tag+'>')
    def handle_data(self, data): (self.anchor_parts if self.anchor_href is not None else self.output).append(data)
    def handle_entityref(self, name): (self.anchor_parts if self.anchor_href is not None else self.output).append('&'+name+';')
    def handle_charref(self, name): (self.anchor_parts if self.anchor_href is not None else self.output).append('&#'+name+';')
    def handle_comment(self, data): (self.anchor_parts if self.anchor_href is not None else self.output).append('<!--'+data+'-->')

for page in SITE.rglob('*.html'):
    parser=Normalizador(); parser.feed(page.read_text()); parser.close()
    page.write_text(''.join(parser.output))
