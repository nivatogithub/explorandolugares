"""Entrega una sola imagen vigente por componente; solo preproducción."""
from pathlib import Path
import json,re
own=Path(__file__).resolve().parents[1];p=own.parent/'preprod'
places={'centro-historico':'Centro histórico y Torre del Reloj','san-felipe':'Castillo de San Felipe','getsemani':'Getsemaní','islas-rosario':'Islas del Rosario','playa-blanca-baru':'Playa Blanca, Barú','cerro-popa':'Convento de La Popa','san-pedro-claver':'San Pedro Claver','bocagrande':'Bocagrande'}
experiences={'caminhada-patrimonial':'Caminata patrimonial','visita-muhca':'Visita al Museo Histórico','bus-panoramico':'Bus turístico','buceo-rosario':'Buceo','catamaran-cartagena':'Catamarán','pesca-boquilla':'Manglares y pesca tradicional'}
def media(key,label,hero=False):return dict(path='assets/cartagena-piloto/'+key+('-1200.webp' if hero else '-800.webp'),alt='Ilustración editorial: '+label,origin='ai_generated',width=1200 if hero else 800,height=800 if hero else 533,caption='')
cart={'hero':media('portada','Cartagena desde La Popa',True),'places':{key:media(key,label) for key,label in (places|experiences).items()}}
for assets in [p/'backend/public/assets',p/'backend/integration-medicion/public/assets',p/'.local/preprod/release/public/assets']:
 manifest=assets/'destinos-semirrealistas.json'
 data=json.loads(manifest.read_text()) if manifest.exists() else {};data['cartagena']=cart;manifest.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
for f in [p/'backend/integration-medicion/public/destino.php',p/'.local/preprod/release/public/destino.php']:
 s=f.read_text().replace("in_array($slug,['bogota','medellin','santa-marta'],true)","in_array($slug,['bogota','medellin','santa-marta','cartagena'],true)",1)
 s=re.sub(r'<script defer src="/assets/experience-art.js\?v=[^"]+" data-destination="cartagena"></script>','',s)
 s=re.sub(r'<script defer src="/assets/cartagena-piloto-semirrealista.js\?v=[^"]+"></script>','',s)
 f.write_text(s)
print('Cartagena: imágenes vigentes desde servidor; eliminada la sustitución duplicada en JavaScript.')
