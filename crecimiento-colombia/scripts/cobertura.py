#!/usr/bin/env python3
from validar import ROOT,WEIGHTS,validate,read
import json
inv,ms=validate();byid={m['place_id']:m for m in ms}
def prepared(m):return sum(w for k,w in WEIGHTS.items() if m['gates'][k]['passed'])
def published(m):return 100 if m['state']=='published' else 0
lines=['# Cobertura recalculada', '',f"Universo: `{inv['universe_version']}` · inventario {inv['as_of']}.", '', 'Preparación con gates y publicación comprobada en este plan. Los estados históricos no se convierten automáticamente en evidencia actual.', '', '| Unidad | P | Lugares | Preparación | Publicación |', '|---|---:|---:|---:|---:|']
country=[0,0];den=0;p1=[]
for d in inv['departments']:
    total=sum(p['weight'] for p in d['places']);values=[]
    for fn in (prepared,published):values.append(sum(p['weight']*fn(byid[p['id']]) for p in d['places'])/total if total else 0)
    for i,v in enumerate(values):country[i]+=d['weight']*v
    den+=d['weight'];p1.extend(p for p in d['places'] if p['priority']==1)
    lines.append(f"| {d['name']} | {d['priority']} | {len(d['places'])} | {values[0]:.2f}% | {values[1]:.2f}% |")
lines+=['',f"País: preparación {country[0]/den:.2f}% · publicación comprobada {country[1]/den:.2f}%.",f"MVP: {len(p1)} lugares P1; {sum(byid[p['id']]['state']=='published' for p in p1)} con publicación comprobada.",f"Inventario: {len(ms)} candidatos; {sum(m['state']=='planned' for m in ms)} planificados.",'','No mide territorio, población ni demanda turística. Todo gate inicial está pendiente de auditoría. Cambiar universo o pesos requiere nueva versión y conservar comparación anterior.']
(ROOT/'COBERTURA.md').write_text('\n'.join(lines)+'\n')
print('COBERTURA.md actualizado')

(ROOT/'cobertura.json').write_text(json.dumps({'universe_version':inv['universe_version'],'places':[{'place_id':m['place_id'],'prepared_percent':prepared(m),'published_percent':published(m),'state':m['state']} for m in ms]},ensure_ascii=False,indent=2)+'\n')
