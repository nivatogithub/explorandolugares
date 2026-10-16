#!/usr/bin/env python3
"""Control local; no ejecuta agentes, red ni publicación. Solo biblioteca estándar."""
import argparse,hashlib,json,statistics
from datetime import datetime
from pathlib import Path
from validar import ROOT,validate as validate_original,require,WEIGHTS
REPO=ROOT.parent
COMPONENTS={'investigacion','fuentes_oficiales','textos_es','historia','sitios_interes','experiencias','directorio_gratuito','fotos_reales','derivados','traducciones_en','traducciones_pt','integracion','previsualizacion'}
STATES={'sin_iniciar','asignado','produccion','revision','correcciones','bloqueado','aprobado','publicado'}
ACTIVE=STATES-{'sin_iniciar','aprobado','publicado'}
PAIRS={'produccion_inicio':'produccion_fin','revision_solicitada':'revision_inicio','revision_inicio':'revision_fin','correccion_inicio':'correccion_fin'}
def digest(data):return hashlib.sha256(data).hexdigest()
def local(ref):
    p=(REPO/ref).resolve();require(p.is_relative_to(REPO) and p.is_file(),'Evidencia local ausente/fuera de repo: '+ref);return p
def timestamp(s):
    t=datetime.fromisoformat(s.replace('Z','+00:00'));require(t.tzinfo is not None,'Fecha sin zona');return t

def evidence(a):
    require(isinstance(a,dict) and a.get('actor')=='usuario' and a.get('explicit') is True and a.get('at') and a.get('evidence'),'Falta decisión explícita del usuario')
    timestamp(a['at']);local(a['evidence'])

def bundle(q):
    require(q['artifacts'],'Entrega sin archivos')
    paths=[]
    for a in q['artifacts']:
        p=local(a['path']);require(digest(p.read_bytes())==a['sha256'],'Artefacto modificado: '+a['path']);paths.append(a['path'])
    require(len(paths)==len(set(paths)),'Artefacto duplicado')
    return digest(json.dumps(sorted(q['artifacts'],key=lambda a:a['path']),sort_keys=True,separators=(',',':')).encode())

def intervals(q):
    pending={}; durations={k:[] for k in PAIRS};previous=None
    for e in q['events']:
        k=e['type'];require(k in set(PAIRS)|set(PAIRS.values())|{'asignacion','aprobacion'},'Evento desconocido')
        t=timestamp(e['at']);require(previous is None or t>=previous,'Eventos fuera de orden');previous=t
        if k in PAIRS:
            require(k not in pending,'Intervalo duplicado');pending[k]=t
        for start,end in PAIRS.items():
            if k==end:
                require(start in pending,'Fin sin inicio: '+start)
                durations[start].append((t-pending.pop(start)).total_seconds()/3600)
    if q['state'] in {'aprobado','publicado'}:require(not pending,'Entrega con intervalos abiertos')
    return {k:sum(v) if v else None for k,v in durations.items()}

def validate(data=None):
    inv,manifests=validate_original();op=data or json.loads((ROOT/'operacion.json').read_text())
    require(op['inventory_sha256']==digest((ROOT/'inventario.json').read_bytes()),'Inventario cambió: versionar/revisar cola')
    require(op['universe_version']==inv['universe_version'],'Universo distinto')
    expected=[]
    for di,d in enumerate(inv['departments']):
        for pi,p in enumerate(d['places']):expected.append((d,p,di,pi))
    expected.sort(key=lambda x:(x[0]['priority'],x[1]['priority'],-x[0]['score'],x[2],-x[1]['score'],x[3]))
    qs=op['queue'];require(len(qs)==len(expected),'Cola incompleta')
    workers=op['workers'];require([w['name'] for w in workers]==['Lugar 1','Lugar 2','Lugar 3'],'Nombres workers inválidos')
    require(type(op['max_active']) is int and op['max_active'] in (2,3),'Capacidad inválida')
    if op['max_active']==3:
        cap=op['third_worker_capacity_evidence'];evidence(cap);samples=cap.get('samples',[])
        require(len(samples)==3 and len(set(samples))==3,'Se requieren tres muestras distintas')
        approved=[q['place_id'] for q in qs if q['state'] in ('aprobado','publicado')]
        require(samples==approved[-3:],'Muestras deben ser las últimas tres aprobadas en cola')
        for pid in samples:
            q=next(q for q in qs if q['place_id']==pid);m=intervals(q)
            require(m['revision_inicio'] is not None and m['produccion_inicio'] is not None and m['revision_solicitada'] is not None,'Faltan tiempos observados')
            require(m['revision_inicio']<=min(24,m['produccion_inicio']) and m['revision_solicitada']<=24,'Capacidad de revisión no demostrada')
    byid={m['place_id']:m for m in manifests};active=[];used=[]
    if any(q['state']!='sin_iniciar' for q in qs):evidence(op['priority_approval'])
    for rank,(q,(d,p,di,pi)) in enumerate(zip(qs,expected),1):
        require(q['rank']==rank and q['place_id']==p['id'],'Orden alterado o duplicado')
        require(q['source_pointer']==f'/departments/{di}/places/{pi}' and q['manifest']==p['path'],'Trazabilidad alterada')
        for key,value in [('department_priority',d['priority']),('place_priority',p['priority']),('department_score',d['score']),('place_score',p['score']),('priority_basis',p['priority_basis']),('department_priority_basis',d['priority_basis']),('name',p['name']),('department',d['name'])]:require(q[key]==value,'Dato de cola distinto de inventario: '+key)
        require(q['branch']==byid[p['id']]['branch'],'Rama de lugar incorrecta')
        require(q['state'] in STATES,'Estado inválido');require(set(q['components'])==COMPONENTS,'Componentes incompletos')
        for c in q['components'].values():
            require(c['status'] in ('pendiente','en_curso','completo','bloqueado'),'Estado componente inválido')
            if c['status']=='completo':
                require(c['evidence'] and c['checked_at'] and c['reviewer'],'Componente sin evidencia');timestamp(c['checked_at'])
                for ref in c['evidence']:local(ref)
        intervals(q)
        if q['state']=='sin_iniciar':require(q['worker'] is None and not q['events'] and q['approval'] is None,'Lugar sin iniciar tiene asignación/actividad')
        else:
            require(q['worker'] in [w['name'] for w in workers],'Worker ausente')
            require(all(prev['state']!='sin_iniciar' for prev in qs[:rank-1]),'Se saltó un lugar de cola')
            require(all(prev['state'] in ('aprobado','publicado') for prev in qs[:rank-1] if (prev['department_priority'],prev['place_priority'])<(q['department_priority'],q['place_priority'])),'Bloque anterior sin completar/aprobar')
            require(any(e['type']=='asignacion' for e in q['events']),'Asignación sin fecha')
            used.append(q)
        if q['state'] in ACTIVE:active.append(q)
        if q['state'] not in ('sin_iniciar','asignado'):evidence(q['start_authorization'])
        if q['state'] in ('revision','aprobado','publicado'):
            import re
            require(re.fullmatch(r'https://github.com/nivatogithub/explorandolugares/pull/[0-9]+',q['pr_url'] or '') is not None,'Falta PR válido')
            require(q['preview'] and (q['preview'].startswith('https://') or local(q['preview'])),'Falta vista previa')
            require(all(c['status']=='completo' for c in q['components'].values()),'Lugar incompleto en revisión')
            require(q['artifact_hash']==bundle(q),'Hash de entrega inválido')
            paths={a['path'] for a in q['artifacts']}
            require(all(ref in paths for c in q['components'].values() for ref in c['evidence']),'Evidencia de componente fuera de entrega aprobable')
            if not q['preview'].startswith('https://'):require(q['preview'] in paths,'Vista previa fuera de hash')
        if q['state'] in ('aprobado','publicado'):
            evidence(q['approval']);require(q['approval'].get('hash')==bundle(q),'Aprobación de otra versión')
            require(byid[q['place_id']]['state'] in ('approved','published'),'Manifiesto original no aprobado')
        else:require(q['approval'] is None,'Aprobación obsoleta: devolver a revisión')
        if q['state']=='publicado':require(byid[q['place_id']]['state']=='published','No hay publicación comprobada')
    require(len(active)<=op['max_active'],'Exceso WIP')
    for w in workers:
        own=[q for q in used if q['worker']==w['name']];current=[q for q in own if q['state'] in ACTIVE]
        require(len(current)<=1,'Worker tiene dos lugares')
        require(w['current_place_id']==(current[0]['place_id'] if current else None),'Asignación worker inconsistente')
        require(w['history']==[q['place_id'] for q in own if q['state'] in ('aprobado','publicado')],'Historial inconsistente')
        for before,after in zip(own,own[1:]):
            require(before['state'] in ('aprobado','publicado'),'Siguiente antes de aprobación')
            assigned=next(e['at'] for e in after['events'] if e['type']=='asignacion')
            require(timestamp(before['approval']['at'])<=timestamp(assigned),'Reasignación anterior a aprobación')
    if op['max_active']==2:require(not any(q['worker']=='Lugar 3' for q in used),'Tercer worker sin capacidad')
    return inv,manifests,op

def report():
    inv,ms,op=validate();byid={m['place_id']:m for m in ms};qby={q['place_id']:q for q in op['queue']}
    places=[]
    for d in inv['departments']:
        for p in d['places']:
            m=byid[p['id']];q=qby[p['id']]
            places.append(dict(place_id=p['id'],department_id=d['id'],weight=p['weight'],prepared_percent=sum(w for k,w in WEIGHTS.items() if m['gates'][k]['passed']),publishable_percent=100 if m['state'] in ('approved','published') and q['state'] in ('aprobado','publicado') else 0,published_percent=100 if m['state']=='published' and q['state']=='publicado' else 0,depth_percent=100*sum(c['status']=='completo' for c in q['components'].values())/len(COMPONENTS),timings_hours=intervals(q)))
    keys=['prepared_percent','publishable_percent','published_percent','depth_percent'];ds=[]
    for d in inv['departments']:
        ps=[p for p in places if p['department_id']==d['id']];den=sum(p['weight'] for p in ps)
        ds.append(dict(department_id=d['id'],name=d['name'],weight=d['weight'],place_count=len(ps),**{k:sum(p['weight']*p[k] for p in ps)/den if den else 0 for k in keys}))
    den=sum(d['weight'] for d in ds);country={k:sum(d['weight']*d[k] for d in ds)/den for k in keys}
    medians={k:dict(samples=len(v),median_hours=statistics.median(v) if v else None) for k in PAIRS for v in [[p['timings_hours'][k] for p in places if p['timings_hours'][k] is not None]]}
    observed={k:v['median_hours'] for k,v in medians.items() if v['median_hours'] is not None}
    result=dict(universe_version=inv['universe_version'],scope=inv['scope'],administrative_national_coverage_percent=None,coverage_denominator='78 candidatos editoriales / 33 unidades; país ponderado por departamento, no censo nacional',country=country,departments=ds,places=places,phase_metrics=medians,bottleneck_candidate=max(observed,key=observed.get) if observed else None,active=sum(q['state'] in ACTIVE for q in op['queue']),awaiting_review=sum(q['state']=='revision' for q in op['queue']),not_started=sum(q['state']=='sin_iniciar' for q in op['queue']))
    (ROOT/'cobertura-operativa.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    lines=['# Cobertura operativa auditada','', 'Universo CO-editorial-v2: 78 candidatos, 33 unidades. Cobertura administrativa nacional: **no calculable** sin censo completo reconciliado.','', 'Preparación = gates ponderados originales; publicabilidad = versión completa aprobada en ambos controles; publicación = estado y evidencia en ambos controles; profundidad = porcentaje de los 13 componentes completos, no medida de riqueza narrativa. Lugares ponderados P1=3/P2=2/P3=1; departamentos igual.','', '| Unidad | Lugares | Preparación | Publicabilidad | Publicación | Profundidad |','|---|---:|---:|---:|---:|---:|']
    for d in ds:lines.append(f"| {d['name']} | {d['place_count']} | "+' | '.join(f'{d[k]:.2f}%' for k in keys)+' |')
    lines+=['', 'País (universo editorial): '+', '.join(f'{k}={country[k]:.2f}%' for k in keys)+'.',f"Sin iniciar: {result['not_started']}; activos: {result['active']}; esperando revisión: {result['awaiting_review']}.", 'Tiempos: sin muestras observadas; no hay diagnóstico de cuello de botella.' if not observed else 'Medianas por fase: '+json.dumps(medians,ensure_ascii=False), '', 'Detalle por lugar y tiempos: cobertura-operativa.json. Cero significa ausencia de evidencia en este plan; no ausencia de contenido histórico ni verificación de producción.']
    (ROOT/'COBERTURA_OPERATIVA.md').write_text('\n'.join(lines)+'\n');return result
if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--report',action='store_true');args=parser.parse_args()
    if args.report:report()
    inv,ms,op=validate();print(f"OK: {len(ms)} lugares; cola estricta; Lugar 1/2/3; WIP {op['max_active']}; sin lanzamiento ni publicación automática.")
