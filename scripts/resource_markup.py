"""Apply browser-URL transforms without rewriting PHP filesystem paths/runtime expressions."""
import re

def transform_markup(text, transform):
    parts=re.split(r'(<\?(?:php|=).*?\?>)',text,flags=re.S)
    return ''.join(part if part.startswith('<?') else transform(part) for part in parts)

def stamp_url(text, pattern, replacement):
    def one(part):
        return re.sub(pattern,lambda match:match[0] if part[match.end():].startswith('?v=') else replacement(match),part)
    return transform_markup(text,one)
