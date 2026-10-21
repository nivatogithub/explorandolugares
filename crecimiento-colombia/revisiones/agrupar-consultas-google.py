from pathlib import Path
import csv,json,unicodedata
p=Path(__file__).parent
def norm(s):return ''.join(c for c in unicodedata.normalize('NFD',s.lower()) if unicodedata.category(c)!='Mn')
groups={}
ambiguous={'san pedro antioquia','el carmen antioquia','cultura el carmen','santa fe colombia','embalse de san rafael','arboletes cordoba','"antioquia" "turismo"'}
rules=[('El Carmen de Viboral','el-carmen-de-viboral',['viboral']),('Guatapé','guatape',['guatape','guadape']),('Santa Rosa de Osos','santa-rosa-de-osos',['santa rosa de osos']),('Arboletes','arboletes',['arboletes','arbiletes']),('San Rafael','san-rafael',['san rafael']),('Santa Fe de Antioquia','santa-fe-de-antioquia',['santa fe','santafe']),('Necoclí','necocli',['necocli']),('Cocorná','cocorna',['cocorna']),('Jericó','jerico',['jerico']),('Venecia','venecia',['venecia']),('Sopetrán','sopetran',['sopetran']),('San Pedro de los Milagros','san-pedro-de-los-milagros',['san pedro de los milagros']),('Rionegro','rionegro',['rionegro','riongro']),('San Jerónimo','san-jeronimo',['san jeronimo']),('Medellín','medellin',['medellin']),('El Peñol','el-penol',['penol']),('El Retiro','el-retiro',['el retiro']),('Puerto Triunfo','puerto-triunfo',['puerto triunfo']),('Támesis','tamesis',['tamesis']),('San Carlos','san-carlos',['san carlos'])]
unassigned=[];rows=list(csv.DictReader((p/'consultas-google-2026-10-20.tsv').open(),delimiter='\t'))
for row in rows:
 n=norm(row['consulta']);row['clics']=int(row['clics']);row['impresiones']=int(row['impresiones'])
 if n in ambiguous:unassigned.append(row);continue
 match=next(((name,slug) for name,slug,keys in rules if any(k in n for k in keys)),None)
 if not match:unassigned.append(row);continue
 name,slug=match;g=groups.setdefault(slug,{'destination':name,'slug':slug,'impressions':0,'clicks':0,'queries':[],'preprod_http_status':200 if slug in ['jerico','medellin','santa-fe-de-antioquia'] else 404});g['impressions']+=row['impresiones'];g['clicks']+=row['clics'];g['queries'].append(row)
data={'saved_at':'2026-10-20','source':'Consultas Google Search pegadas por propietario; no exportación autenticada','report_period':None,'country_device_filters':None,'raw_rows':len(rows),'impressions':sum(r['impresiones'] for r in rows),'clicks':sum(r['clics'] for r in rows),'scope':'Contraste HTTP local preprod8088, no auditoría producción; borrador en inventario no equivale implementación','groups':sorted(groups.values(),key=lambda x:-x['impressions']),'ambiguous_or_general_queries':unassigned,'work_started':False,'existing_queue_changed':False}
(p/'consultas-google-2026-10-20-agrupadas.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
lines=['# Consultas Google: destinos pendientes','',f"{len(rows)} consultas; {data['impressions']} impresiones; {data['clicks']} clics. Periodo no proporcionado.",'','Guardadas sin iniciar destinos ni cambiar cola. Estado comprobado únicamente en preproducción local: HTTP404 significa ruta no disponible allí; puede existir borrador histórico o implementación en otra rama/producción. HTTP200 no certifica aprobación ni fidelidad.','', '| Destino | Impresiones agrupadas | Preprod |','|---|---:|---|']
for g in data['groups']:lines.append(f"| {g['destination']} | {g['impressions']} | {'Disponible (200)' if g['preprod_http_status']==200 else 'No disponible (404)'} |")
lines+=['','## Ambigüedades excluidas del conteo por destino','']+[f"- {r['consulta']}: {r['impresiones']} impresiones" for r in unassigned]
lines+=['','Las impresiones son exposiciones en búsquedas, no personas ni volumen total de demanda. No sustituir la priorización turística nacional por estos conteos. Variantes ortográficas se agrupan conservando cada consulta original.']
(p/'CONSULTAS_GOOGLE_PENDIENTES_2026-10-20.md').write_text('\n'.join(lines)+'\n')
print('\n'.join(lines[:29]))
