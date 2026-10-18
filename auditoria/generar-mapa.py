"""Inventario de solo lectura del paquete local y sus fuentes; no consulta producción."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit,unquote
from collections import Counter,defaultdict
import json,re,html
ROOT=Path(__file__).resolve().parents[1]; PUBLIC=ROOT/'.local/preprod/release/public';OUT=Path(__file__).resolve().parent
class Page(HTMLParser):
 def __init__(self):
  super().__init__();self.links=[];self.forms=[];self.controls=[];self.headings=[];self.ids=[];self.title='';self.capture=None;self.buffer='';self.link=None
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if a.get('id'):self.ids.append(a['id'])
  if tag in ['title','h1','h2','h3','button']:
   self.capture=tag;self.buffer='';self.attr=a
  if tag=='a' and a.get('href'):self.link={'href':a['href'],'label':''};self.links.append(self.link)
  if tag=='form':self.forms.append({'action':a.get('action','misma ruta'),'method':a.get('method','get')})
  if tag in ['input','select','textarea']:self.controls.append({'tag':tag,'name':a.get('name'),'type':a.get('type'),'required':'required' in a})
 def handle_data(self,data):
  if self.capture:self.buffer+=data
  if self.link:self.link['label']+=data
 def handle_endtag(self,tag):
  if tag=='a':self.link=None
  if tag==self.capture:
   text=' '.join(self.buffer.split())
   if tag=='title':self.title=text
   else:self.headings.append({'tag':tag,'text':text,'id':self.attr.get('id'),'role':self.attr.get('role')})
   self.capture=None

def group(p):
 p=re.sub(r'^(en|pt-br)/','',p)
 if p.startswith('admin/'):return 'Administración privada'
 if p.startswith('api/'):return 'API / infraestructura'
 if p.startswith('business/') or p.startswith('reclamar'):return 'Cuenta / negocios dinámicos'
 if p.startswith('cobertura/'):return 'Estamos creciendo'
 if p.startswith('negocios-locales/'):return 'Información comercial'
 if p.startswith('destinos/'):return 'Lugares / categorías'
 if p.startswith('privacidad'):return 'Privacidad'
 if p.startswith('planea'):return 'Planea tu visita'
 if p.startswith('etapas'):return 'Conoce el portal'
 return 'General / técnico'
rows=[];inbound=defaultdict(set)
for f in sorted(PUBLIC.rglob('*')):
 if f.suffix not in ['.html','.php'] or not f.is_file():continue
 p=f.relative_to(PUBLIC).as_posix()
 if '/partials/' in p or Path(p).name.startswith('_'):kind='Fragmento interno'
 else:kind='PHP: requiere ejecución y estado' if f.suffix=='.php' else 'HTML del paquete; no implica publicación'
 parser=Page();parser.feed(f.read_text(errors='replace'))
 links=[]
 for link in parser.links:
  href=link['href'];u=urlsplit(urljoin('http://local/'+p,href))
  if u.netloc=='local' and '<?' not in href and not href.startswith('#'):
   target=unquote(u.path.lstrip('/'));target=target+'index.html' if target.endswith('/') else target
   if not target:target='index.html'
   inbound[target].add(p)
  links.append({'href':href,'label':' '.join(link['label'].split())})
 sources=[str(s.relative_to(ROOT)) for s in [ROOT/'ensayos/v1'/p,ROOT/'backend/public'/p,ROOT/'backend/integration-medicion/public'/p] if s.exists()]
 rows.append({'path':p,'group':group(p),'kind':kind,'title':parser.title,'headings_and_buttons':parser.headings,'forms':parser.forms,'controls':parser.controls,'ids':parser.ids,'links':links,'sources_in_overlay_order':sources})
for row in rows:row['literal_inbound_pages']=sorted(inbound[row['path']]);row['no_literal_inbound']=not bool(row['literal_inbound_pages'])
rules=[]
for n,line in enumerate((ROOT/'ensayos/v1/.htaccess').read_text().splitlines(),1):
 if line.startswith('RewriteRule'):rules.append({'line':n,'rule':line})
source_only=[]
for base in [ROOT/'ensayos/v1',ROOT/'backend/public',ROOT/'backend/integration-medicion/public']:
 for f in base.rglob('*'):
  if f.is_file() and f.suffix in ['.html','.php'] and not (PUBLIC/f.relative_to(base)).exists():source_only.append({'source':str(f.relative_to(ROOT)),'path':f.relative_to(base).as_posix(),'status':'Solo en fuente; no incluido en paquete local. No publicar por esta auditoría.'})
result={'source_only':source_only,'scope':'Paquete local existente + fuentes actuales; auditoría estática. No prueba producción ni publicación de contenido en BD. PHP, enlaces generados por JS y controles de permisos requieren validación funcional.','pages':rows,'rewrite_rules':rules,'counts':dict(Counter(r['group'] for r in rows))}
(OUT/'inventario-navegacion.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
e=lambda v:html.escape(str(v))
intro='''<h1>Mapa de navegación y funciones — Explorando Lugares</h1><p>Auditoría del 17 de octubre de 2026. Rama preprod. No se modificó navegación ni producción.</p><p><strong>Alcance:</strong> paquete local preparado para hosting y fuentes actuales. El inventario enumera archivos, no afirma que estén publicados. Las rutas PHP, el contenido en base de datos y las banderas requieren comprobación funcional separada. Los borradores no deben hacerse públicos por reorganizar menús.</p>
<h2>Decisiones vigentes del propietario</h2><ul><li>Principal: Lugares · Planea tu visita · Estamos creciendo · Para negocios. Inicio mediante el logo.</li><li>Experiencias dentro de cada lugar, después de los atractivos; sin página global nueva.</li><li>Acceso a cuenta a la izquierda de idiomas; sin franja adicional. Nombre y Mi cuenta al autenticar.</li><li>Mi cuenta: Personal y Mis negocios; sin selector de espacio ni enlace global Explorar.</li><li>Estamos creciendo permanece público y visible: mapa/cobertura, audiencia/estadísticas y participación/difusión.</li><li>Tres contactos diferenciados: promotor, consulta de negocio y sugerencia/petición/queja/reclamo.</li></ul>
<h2>Correspondencia de páginas y funciones</h2><table><tr><th>Actual</th><th>Ubicación propuesta / conservación</th><th>Estado / cuidado</th></tr>
<tr><td>/ y versiones EN/PT</td><td>Inicio por logo; contenido para viajeros y CTA comercial contextual</td><td>Conservar enlaces e idiomas equivalentes</td></tr>
<tr><td>/destinos/; /destinos/{slug}/; categorías</td><td>Lugares → cada lugar → atractivos, experiencias y oferta local</td><td>Cambiar etiqueta, conservar rutas. Publicación depende de BD; archivos históricos no acreditan vigencia</td></tr>
<tr><td>/agenda/ y versiones EN/PT</td><td>Acceso contextual desde Planea tu visita y cada lugar; conservar ruta</td><td>Página existente de orientación a eventos por lugar. No crear agenda global vacía ni eliminar este acceso</td></tr>
<tr><td>/planea-tu-visita/</td><td>Planea tu visita</td><td>Conservar íntegra</td></tr>
<tr><td>/etapas/</td><td>Conoce el portal, accesible desde footer y contexto del piloto</td><td>No eliminar por quitar entrada principal</td></tr>
<tr><td>/cobertura/colombia/ → Cobertura / Audiencia / Haz parte</td><td>Estamos creciendo en menú principal, mismas tres pestañas</td><td>Mapa, porcentaje, estadísticas y compartir deben conservarse. Visibilidad es nombre usado en conversación; el código dice Audiencia</td></tr>
<tr><td>/negocios-locales (PHP) y /negocios-locales/index.html (histórico)</td><td>Para negocios, entrada comercial única</td><td>El PHP envuelve la página HTML e inyecta acciones según bandera. Son capas del mismo recorrido: conservar contenido, idiomas y acciones</td></tr>
<tr><td>/negocios-locales/por-que-participar/</td><td>Para negocios → Beneficios</td><td>Conservar página y vínculos</td></tr>
<tr><td>/negocios-locales/proceso/</td><td>Para negocios → Cómo participar</td><td>Texto de revisión manual debe alinearse con validación futura; no anunciar automatización inexistente</td></tr>
<tr><td>/negocios-locales/planes/</td><td>Para negocios → Suscripciones</td><td>Conservar URL; comprobar precios/funciones reales antes de publicar cambios comerciales</td></tr>
<tr><td>/negocios-locales/interes/</td><td>Contacto → Información para mi negocio; accesible desde Para negocios</td><td>Captación comercial, no equivale a crear cuenta</td></tr>
<tr><td>/negocios-locales/impulsa-tu-destino/ y /impulsor/</td><td>Para negocios → Promotores: información y postulación</td><td>Dos pasos distintos, conservar ambos; no inventar comisiones o aprobación</td></tr>
<tr><td>/reclamar-negocio/; /negocios/registro/</td><td>Mi cuenta → Mis negocios → Reclamar / Crear</td><td>Conservar búsqueda, coincidencias, categorías, mapas, evidencias y solicitudes; no duplicar fichas</td></tr>
<tr><td>/negocios/acceso/, /salir/, /recuperar-clave/, /cambiar-clave/</td><td>Menú Mi cuenta + acceso modal + recuperación</td><td>Conservar rutas; correo de recuperación depende de configuración; OAuth aplazado</td></tr>
<tr><td>/cuenta/espacio/ y /negocios/portal/</td><td>Mi cuenta con Personal / Mis negocios</td><td>Selector actual pendiente de retirar; conservar compatibilidad de sus enlaces</td></tr>
<tr><td>/negocios/{id}/ y EN/PT</td><td>Fichas comerciales públicas desde cada lugar</td><td>Respetar publicación, suscripción y campos privados; no hacer públicos canales CRM</td></tr>
<tr><td>Privacidad, preferencias, créditos</td><td>Footer global, enlaces contextuales en formularios</td><td>Conservar consentimiento, estadísticas agregadas y controles actuales; no cambiar política al reorganizar</td></tr>
<tr><td>WhatsApp, Instagram, correo</td><td>Footer y Contacto y ayuda</td><td>Canales generales existentes no equivalen a formularios con seguimiento</td></tr>
<tr><td>Sugerencias / peticiones / quejas / reclamos</td><td>Contacto y ayuda → Usuario</td><td>Recorrido aún no implementado/verificado; no prometer radicación o seguimiento</td></tr>
<tr><td>Administración: operación, contenido, dirección, sistema</td><td>Acceso exclusivo de administradores desde cuenta</td><td>Conservar solicitudes, negocios/canales/importación, campañas, reclamos, cuentas, suscripciones, destinos, inteligencia, costos/cancelaciones, salud y BD</td></tr>
</table>
<h2>Hallazgos y controles contra pérdida</h2><ul><li>El documento PLAN_UX_NAVEGACION_V1 es anterior a cuentas y estadísticas: no sirve como especificación completa actual.</li><li>El paquete aplica fuentes HTML, backend PHP y una integración final de medición: esta última puede reemplazar menú y páginas. Auditar solamente ensayos/v1 deja fuera la implementación efectiva.</li><li>El portal de negocios ya incluye pagos, envío de soporte, solicitudes de suscripción, cancelación/no renovación, solicitudes de representación y métricas de 30 días: las pestañas de Mi cuenta deben conservar todas estas funciones.</li><li>El paquete local contiene __cartagena-v3-mobile.html y herramientas __preprod: no son navegación del producto ni contenido aprobado; requieren control de exclusión antes de preparar una entrega pública.</li><li>El panel aún contiene Reportes «Próximamente»: no equivale a un módulo funcional. No confundir con estadísticas públicas ni inteligencia privada.</li><li>Los archivos sin enlaces literales se señalan para revisar: PHP/JS pueden generar el acceso; no son prueba de páginas huérfanas.</li><li>Antes de cambiar: comparar enlaces/acciones por página e idioma; después verificar todos los destinos, pestañas, formularios, sesión, permisos y estados vacíos. Mantener redirecciones y hacer pruebas en preprod antes de aprobación.</li></ul>
<h2>Inventario detallado</h2><p>Filtra por ruta, título o sección. Cada entrada conserva fuentes, enlaces, formularios, campos, encabezados, botones e IDs en el JSON adjunto. Fragmentos internos y APIs no deben convertirse en entradas del menú.</p><input id="filter" placeholder="Filtrar páginas…" aria-label="Filtrar páginas"><table id="inventory"><tr><th>Ruta</th><th>Sección / alcance</th><th>Título / funciones</th><th>Fuentes</th><th>Accesos literales</th></tr>'''
intro+='<h3>Resumen de alcance</h3><p>'+str(len(rows))+' archivos del paquete, '+str(len(rules))+' reglas de rutas y '+str(len(source_only))+' archivos de página presentes solo en fuentes. Todos se conservan en el JSON.</p>'
body=''
for r in rows:
 body+='<tr>'+''.join('<td>'+e(v)+'</td>' for v in [r['path'],r['group']+' — '+r['kind'],r['title']+' | '+'; '.join(x['text'] for x in r['headings_and_buttons'])[:1800]+' | Formularios: '+str(len(r['forms'])),' → '.join(r['sources_in_overlay_order']),len(r['literal_inbound_pages'])])+'</tr>'
(OUT/'mapa-navegacion.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><title>Auditoría de navegación</title><style>body{font:16px/1.55 system-ui;margin:36px;color:#302d29;background:#fffaf4}table{border-collapse:collapse;width:100%;font-size:14px}td,th{padding:12px;border:1px solid #d8cabc;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#eadfd2}input{padding:12px;margin:12px 0;width:70%}h2{margin-top:40px}</style>'+intro+body+'</table><script>document.querySelector("#filter").oninput=e=>{for(const row of document.querySelectorAll("#inventory tr:not(:first-child)"))row.hidden=!row.textContent.toLowerCase().includes(e.target.value.toLowerCase())}</script></html>')
print(json.dumps({'counts':result['counts'],'total':len(rows),'rewrite_rules':len(rules),'html':str(OUT/'mapa-navegacion.html')},ensure_ascii=False))
