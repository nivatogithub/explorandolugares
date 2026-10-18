"""Evaluación editorial turística versionada. No infiere afluencia de la preparación."""
import json,hashlib
from validar import ROOT,require
WEIGHTS={'alcance':40,'producto':30,'ecosistema':20,'acceso':10}
def validate_tourism(inv):
    data=json.loads((ROOT/'turismo.json').read_text())
    require(data['version']==inv['universe_version'],'Versión turística distinta')
    require(data['weights']==WEIGHTS,'Pesos turísticos alterados sin versión de método')
    ds={d['id']:d for d in inv['departments']};ps={p['id']:p for d in ds.values() for p in d['places']}
    rows=data['places'];departments=data['departments'];sources=data['sources']
    require(len(rows)==len(ps) and {r['id'] for r in rows}==set(ps),'Evaluación turística incompleta/duplicada')
    require(len(departments)==33 and {d['id'] for d in departments}==set(ds),'Revisión territorial incompleta')
    require({d['tourism_order'] for d in departments}==set(range(1,34)),'Orden departamental inválido')
    for row in departments+rows:
        require(row['sources'] and all(k in sources for k in row['sources']),'Fuentes turísticas ausentes')
        require(row['reason'] and row['confidence'] in ('editorial_media','editorial_baja'),'Falta criterio/límite editorial')
        if row in departments:require(row['priority']==ds[row['id']]['priority'],'Grupo departamental desincronizado')
    for row in rows:
        require(set(row['inputs'])==set(WEIGHTS),'Dimensiones turísticas incompletas')
        require(all(type(v) is int and 0<=v<=5 for v in row['inputs'].values()),'Entrada turística fuera de rango')
        score=round(sum(w*row['inputs'][k]/5 for k,w in WEIGHTS.items()),2)
        require(score==row['tourism_score'],'Puntuación turística inconsistente')
        p=1 if score>=70 else 2 if score>=45 else 3
        require(row['priority']==p==ps[row['id']]['priority'],'Grupo turístico no corresponde al método')
        require(row['comparable_visitor_count'] is None,'Afluencia sin modelo estadístico aprobado')
    for source in sources.values():
        require(source['checked_at'] and source['limits'] and source['access_method'],'Fuente sin límites/fecha')
        require(source['url'].startswith('https://') or (ROOT.parent/source['url']).is_file(),'Fuente inválida')
    return data

def ordered_rows(inv):
    data=validate_tourism(inv);places={p['id']:p for p in data['places']};departments={d['id']:d for d in data['departments']}
    rows=[(d,p,di,pi) for di,d in enumerate(inv['departments']) for pi,p in enumerate(d['places'])]
    rows.sort(key=lambda x:(x[0]['priority'],x[1]['priority'],-places[x[1]['id']]['tourism_score'],places[x[1]['id']]['tie_order'],departments[x[0]['id']]['tourism_order'],x[1]['id']))
    return rows,data

def report():
    from validar import validate
    inv,_=validate();rows,data=ordered_rows(inv);tby={t['id']:t for t in data['places']};ds={d['id']:d for d in data['departments']}
    from alcance import scoped_rows
    active,deferred,policy=scoped_rows(rows,data);active_ids={p['id'] for d,p,di,pi in active}
    lines=['# Orden turístico nacional propuesto','',f"Versión {data['version']} · consulta {data['checked_at']} · {len(rows)} candidatos / 33 unidades.",'', 'Evaluación editorial, no ranking oficial de visitantes. Ver REVISION_TURISTICA_NACIONAL.md. El score turístico no mide contenido realizado; preparación excluida del orden. Todos sin iniciar.','', '## Departamentos revisados','', '| Orden editorial | Departamento | Grupo anterior → propuesto | Base | Fuentes |','|---:|---|---|---|---|']
    for d in sorted(data['departments'],key=lambda d:d['tourism_order']):lines.append(f"| {d['tourism_order']} | {d['name']} | P{d['previous_priority']} → P{d['priority']} | {d['reason']} | {', '.join(d['sources'])} |")
    lines+=['', f'## Inventario ordenado: {len(active)} candidatos en alcance P1–P2; {len(deferred)} diferidos; P4 excluida','', '| Orden | Lugar | Departamento | Bloque depto/lugar | Turismo editorial /100 | Lugar anterior → propuesto | Categoría histórica Antioquia | Alcance | Criterio | Fuentes |','|---:|---|---|---|---:|---|---|---|---|']
    for rank,(d,p,di,pi) in enumerate(rows,1):
        t=tby[p['id']];before='nuevo' if t['previous_priority'] is None else f"P{t['previous_priority']}";legacy=t['legacy_pilot_category'] or '—'
        lines.append(f"| {rank} | {p['name']} | {d['name']} | P{d['priority']}/P{p['priority']} | {t['tourism_score']:g} | {before} → P{p['priority']} | {legacy} | {'P1–P2 elegible' if p['id'] in active_ids else 'Diferido fuera de cola'} | {t['reason']} | {', '.join(t['sources'])} |")
    lines+=['', '## Fuentes y límites','', 'Cada código de las tablas corresponde a una fuente consultada. Fuente promocional no prueba afluencia; fuentes solo históricas o locales tienen menor confianza. El registro turismo.json distingue lectura de página/PDF, resultado de búsqueda y archivo local.']
    for code,s in data['sources'].items():lines+=['',f"- **{code}**: [{s['publisher_title']}]({s['url']}). Consulta {s['checked_at']}; {s['access_method']}. {s['limits']}"]
    lines+=['', 'Alcance vigente: ALCANCE.md y alcance.json. Numeración de esta tabla = inventario, no asignación ejecutable. Puntuaciones e inputs: turismo.json; IDs y punteros al inventario: operacion.json. Estados/gates anteriores intactos; sin aprobación de prioridades ni ejecución automática.']
    (ROOT/'ORDEN_TURISTICO.md').write_text('\n'.join(lines)+'\n')
if __name__=='__main__':
    import argparse
    parser=argparse.ArgumentParser();parser.add_argument('--report',action='store_true');args=parser.parse_args()
    if args.report:report()
    else:
        inv=json.loads((ROOT/'inventario.json').read_text());validate_tourism(inv)
    print('OK: evaluación turística y orden nacional coherentes; no es ranking de afluencia.')
