"""Install a narrowly scoped local-preprod review overlay; never changes ZIP/DB."""
from pathlib import Path
import shutil
import hashlib
import json

here = Path(__file__).resolve().parent
delivery = here.parent
preprod = delivery.parents[2].parent / 'preprod'
hook = '''<!-- zipaquira-reference-review-v3 --><?php if($localShowcase && $slug==='zipaquira'):?><script defer src="/assets/zipaquira-revision/visual-gate.js?v=3"></script><?php endif;?>'''
changed = []
for public in (preprod/'backend/integration-medicion/public', preprod/'.local/preprod/release/public'):
    target = public/'assets/zipaquira-revision'
    target.mkdir(parents=True, exist_ok=True)
    shutil.copy2(here/'visual-gate.js', target/'visual-gate.js')
    shutil.copy2(delivery/'creditos-referencias-v3.html', target/'creditos.html')
    renderer = public/'destino.php'
    text = renderer.read_text()
    if 'zipaquira-reference-review-v3' not in text:
        assert text.count('</head>') == 1
        renderer.write_text(text.replace('</head>', hook+'</head>'))
    changed.append(str(renderer))
receipt = {'environment':'local_preprod_only','package_modified':False,'database_modified':False,
           'hidden_rejected_components':['colegio-gabo','museo-quevedo','ruta-del-minero','recorrido-historico'],
           'js_sha256':hashlib.sha256((here/'visual-gate.js').read_bytes()).hexdigest(), 'renderers':changed}
(delivery/'RECIBO_OVERLAY_VISUAL_V3.json').write_text(json.dumps(receipt,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(receipt,ensure_ascii=False))
