#!/usr/bin/env python3
"""Render shared header controls during packaging, before the browser paints."""
from pathlib import Path
import argparse, hashlib, re
COPY={'es':('Iniciar sesión','Registrarse','Tu cuenta','Abrir menú'),'en':('Log in','Sign up','Your account','Open menu'),'pt-BR':('Iniciar sessão','Cadastre-se','Sua conta','Abrir menu')}
LANGS={'es':('🇨🇴','ES','Español'),'en':('🇬🇧','EN','English'),'pt-BR':('🇧🇷','PT-BR','Português')}
def render(text):
    if 'header-language-bar' in text:return text
    head=re.search(r'<header\b[^>]*>.*?</header>',text,re.S)
    if not head:return text
    header=head[0];switch=re.search(r'<span class="language-switch"[^>]*>(.*?)</span>',header,re.S)
    if not switch:return text
    anchors=re.findall(r'<a\b.*?</a>',switch[1],re.S)
    if len(anchors)!=3:return text
    dynamic=bool(re.search(r'<html[^\n]*lang="<\?',text))
    match=re.search(r'<html\s+lang="([^"]+)"',text);locale=match[1] if match and match[1] in COPY else 'es'
    def word(index):
        if dynamic:return "<?=($locale==='en'?"+repr(COPY['en'][index])+":($locale==='pt-BR'?"+repr(COPY['pt-BR'][index])+":"+repr(COPY['es'][index])+"))?>"
        return COPY[locale][index]
    links=[]
    for anchor in anchors:
        lang=re.search(r'hreflang="([^"]+)"',anchor)
        if not lang or lang[1] not in LANGS:return text
        flag,code,name=LANGS[lang[1]]
        # Preserve PHP expressions in attributes: split at the final tag opening.
        closing=anchor.rfind('</a>');opening=anchor.rfind('>',0,closing)
        anchor=anchor[:opening+1]+f'<span class="header-language-flag" aria-hidden="true">{flag}</span><span>{code}</span></a>'
        anchor=anchor.replace('<a ',f'<a aria-label="{name}" ',1)
        links.append(anchor)
    suffix="<?=($locale==='en'?'?lang=en':($locale==='pt-BR'?'?lang=pt-br':''))?>" if dynamic else ('?lang='+locale.lower() if locale!='es' else '')
    bar='<div class="header-language-bar"><span class="language-switch" aria-label="'+word(2)+'"><span class="portal-account-strip portal-account-inline" aria-label="'+word(2)+'"><span class="portal-account-strip-inner"><a href="/negocios/acceso/'+suffix+'">'+word(0)+'</a><a class="header-register" href="/negocios/registro/'+suffix+'">'+word(1)+'</a></span></span></span><nav class="header-language-options" aria-label="Idioma / Language">'+''.join(links)+'</nav></div>'
    header=header.replace(switch[0],'',1)
    if 'cobertura/colombia' not in header:
        growing="<?=($locale==='en'?'We’re growing':($locale==='pt-BR'?'Estamos crescendo':'Estamos creciendo'))?>" if dynamic else {'es':'Estamos creciendo','en':'We’re growing','pt-BR':'Estamos crescendo'}[locale]
        header=header.replace('<a class="pill light"', '<a href="/cobertura/colombia/">'+growing+'</a><a class="pill light"',1)
    menu=re.search(r'<nav\b[^>]*>',header)
    if not menu:return text
    button='<button type="button" class="menu-toggle" aria-controls="menu-principal" aria-expanded="false" aria-label="'+word(3)+'"><span></span><span></span><span></span></button>'
    header=header[:menu.start()]+bar+button+header[menu.start():]
    # Principal navigation must be distinct from the language navigation.
    header=header.replace(menu[0],menu[0][:-1]+' id="menu-principal">',1)
    return text[:head.start()]+header+text[head.end():]
def render_footer(text):
    footer=re.search(r'<footer\b[^>]*>.*?</footer>',text,re.S)
    if not footer:return text
    dynamic=bool(re.search(r'<html[^\n]*lang="<\?',text))
    match=re.search(r'<html\s+lang="([^"]+)"',text);locale=match[1] if match and match[1] in COPY else 'es'
    def word(es,en,pt):
        if dynamic:return "<?=($locale==='en'?"+repr(en)+":($locale==='pt-BR'?"+repr(pt)+":"+repr(es)+"))?>"
        return {'es':es,'en':en,'pt-BR':pt}[locale]
    body=footer[0]
    if 'href="/contacto.php"' not in body:
        help='<span><a class="footer-help-link" href="/contacto.php">'+word('Contacto y ayuda','Contact and help','Contato e ajuda')+'</a></span>'
        end=body.rfind('</div>')
        body=body[:end]+help+body[end:] if end>=0 else body.replace('</footer>',help+'</footer>')
    if 'el-measurement-preferences' not in body:
        button='<button type="button" class="el-measurement-preferences">'+word('Privacidad y estadísticas','Privacy and statistics','Privacidade e estatísticas')+'</button>'
        body=body.replace('</footer>',button+'</footer>')
    return text[:footer.start()]+body+text[footer.end():]
def apply(public):
    public=Path(public)
    versions={name:hashlib.sha256((public/'assets'/name).read_bytes()).hexdigest()[:12] for name in ['header-controls.css','account-entry.css','cobertura/medicion.css']}
    count=0
    for path in public.rglob('*'):
        if path.suffix not in {'.html','.php'}:continue
        text=path.read_text();updated=render_footer(render(text))
        if 'header-language-bar' in updated and '</head>' in updated:
            css=''.join(f'<link rel="stylesheet" href="/assets/{name}?v={version}">' for name,version in versions.items())
            if '/assets/header-controls.css' not in updated:updated=updated.replace('</head>',css+'</head>',1)
        if updated!=text:path.write_text(updated);count+=1
    print(f'Encabezado preparado antes del primer render: {count} archivos')
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
