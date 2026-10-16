#!/usr/bin/env python3
"""Valida el subconjunto JSON Schema usado aquí y las invariantes del plan. Sin red."""
from pathlib import Path
import json,re,hashlib
ROOT=Path(__file__).resolve().parents[1]
WEIGHTS={'identidad':5,'fuentes':10,'texto':20,'fotos':20,'idiomas':15,'directorio':10,'tecnica':10,'privacidad':5,'revision':5}
def read(path):return json.loads(path.read_text())
def require(ok,msg):
    if not ok:raise ValueError(msg)
def check(value,s,path='$'):
    # No pretende implementar JSON Schema general: falla ante keywords no soportadas.
    allowed={'$schema','$id','type','properties','required','additionalProperties','items','enum','const','minimum','maximum','minLength','pattern'}
    require(set(s)<=allowed,'Keyword no soportada en '+path)
    if 'type' in s:
        kinds=s['type'] if isinstance(s['type'],list) else [s['type']]
        match={'null':value is None,'object':isinstance(value,dict),'array':isinstance(value,list),'string':isinstance(value,str),'boolean':type(value) is bool,'integer':type(value) is int,'number':type(value) in (int,float)}
        require(any(match[k] for k in kinds),'Tipo inválido '+path)
    if 'enum' in s:require(value in s['enum'],'Enum inválido '+path)
    if 'const' in s:require(value==s['const'],'Const inválida '+path)
    if isinstance(value,dict):
        require(set(s.get('required',[]))<=set(value),'Faltan campos '+path)
        props=s.get('properties',{})
        if s.get('additionalProperties') is False:require(set(value)<=set(props),'Campos desconocidos '+path)
        for k,v in value.items():
            if k in props:check(v,props[k],path+'.'+k)
    if isinstance(value,list) and 'items' in s:
        for i,v in enumerate(value):check(v,s['items'],path+f'[{i}]')
    if isinstance(value,str):
        if 'minLength' in s:require(len(value)>=s['minLength'],'Cadena vacía '+path)
        if 'pattern' in s:require(re.search(s['pattern'],value) is not None,'Patrón inválido '+path)
    if type(value) in (int,float):
        if 'minimum' in s:require(value>=s['minimum'],'Valor bajo '+path)
        if 'maximum' in s:require(value<=s['maximum'],'Valor alto '+path)
def local(path):
    p=(ROOT/path).resolve();require(p.is_relative_to(ROOT),'Ruta fuera del plan: '+path);return p
def evidence_ok(ref):
    if ref.startswith('https://'):return True # Existencia/fidelidad remota requiere revisión humana.
    return local(ref).is_file()
def content_hash(m):
    # Excluye estado/aprobación/publicación para evitar un hash circular.
    payload={k:v for k,v in m.items() if k not in ('approval','publication','state')}
    return hashlib.sha256(json.dumps(payload,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def validate():
    inv=read(ROOT/'inventario.json');check(inv,read(ROOT/'schemas/inventario.schema.json'))
    ds=inv['departments'];require(len(ds)==33,'Se requieren 32 departamentos y Bogotá')
    require(sum(d['unit_type']=='capital_district' for d in ds)==1 and any(d['id']=='CO-11' and d['unit_type']=='capital_district' for d in ds),'Bogotá debe ser unidad especial')
    codes={'05','08','11','13','15','17','18','19','20','23','25','27','41','44','47','50','52','54','63','66','68','70','73','76','81','85','86','88','91','94','95','97','99'}
    require({d['dane_code'] for d in ds}==codes,'Unidades DANE incompletas')
    require(sum(inv['score_weights'].values())==100,'Pesos de puntuación inválidos')
    ids={};departments=set();manifests=[]
    def scored(row):
        expected=round(sum(inv['score_weights'][k]*row['score_inputs'][k]/5 for k in inv['score_weights']),2)
        require(row['score']==expected,'Puntuación inconsistente '+row['id'])
        pri=1 if expected>=80 else 2 if expected>=55 else 3
        require(row['priority']==pri and row['weight']==4-pri,'Prioridad/peso inconsistente '+row['id'])
    for d in ds:
        require(d['id'] not in departments,'Departamento duplicado');departments.add(d['id']);scored(d)
        require(d['places'],'Departamento sin candidatos')
        require(read(ROOT/f"colombia/departamentos/{d['slug']}/departamento.json")=={k:v for k,v in d.items() if k!='places'},'Resumen departamental desactualizado')
        slugs=set()
        for p in d['places']:
            scored(p);require(p['id'] not in ids,'Lugar duplicado');ids[p['id']]=p
            require(p['slug'] not in slugs,'Slug duplicado');slugs.add(p['slug'])
            expected=f"colombia/departamentos/{d['slug']}/lugares/{p['slug']}/lugar.json"
            require(p['path']==expected,'Ruta inconsistente')
            m=read(local(p['path']));check(m,read(ROOT/'schemas/lugar.schema.json'));manifests.append(m)
            require(m['place_id']==p['id'] and m['department_id']==d['id'],'Identidad inconsistente')
            require(m['branch']==f"lugar/CO/{d['slug']}/{p['slug']}",'Rama inconsistente')
            require(local(p['path']).with_name('PROCESO.md').is_file(),'Falta proceso')
            if m['state']=='blocked':require(m['blockers'],'Bloqueo sin motivo')
            for k,g in m['gates'].items():
                if g['passed']:
                    require(g['reviewer'] and g['checked_at'] and g['evidence'],'Gate sin evidencia '+p['id']+'/'+k)
                    require(all(evidence_ok(e) for e in g['evidence']),'Evidencia local ausente')
            if m['state'] in ('approved','published'):
                require(all(g['passed'] for g in m['gates'].values()),'Aprobación sin gates')
                require(m['owner'] and m['reviewer'] and m['pr_url'] and re.match(r'^https://github.com/nivatogithub/explorandolugares/pull/[0-9]+$',m['pr_url']),'Falta responsable/revisor/PR')
                a=m['approval'];require(a and a['hash']==content_hash(m) and evidence_ok(a['evidence']),'Aprobación obsoleta o sin evidencia')
            if m['state']=='published':
                pub=m['publication'];require(pub is not None,'Publicación sin evidencia')
                require(evidence_ok(pub['http_evidence']) and evidence_ok(pub['authorization_evidence']),'Publicación sin comprobación/autorización')
            for ref in m['worker_deliveries']:
                delivery=read(local(ref));check(delivery,read(ROOT/'schemas/entrega.schema.json'))
                require(delivery['place_id']==p['id'],'Entrega de otro lugar')
                if delivery['status']=='accepted':require(delivery['reviewer'] and delivery['base_hash'] and delivery['artifacts'],'Entrega aceptada incompleta')
                for a in delivery['artifacts']:
                    artifact=local(a['path']);require(artifact.is_file(),'Artefacto ausente')
                    require(hashlib.sha256(artifact.read_bytes()).hexdigest()==a['sha256'],'Hash de artefacto incorrecto')
                for photo in delivery['photos']:
                    require(photo['place_id']==p['id'],'Foto de otro lugar')
                    if delivery['status']=='accepted':require(photo['commercial_use_allowed'] and photo['transformations_allowed'] and evidence_ok(photo['permission_evidence']),'Foto sin derechos aptos')
                for b in delivery['business_records']:
                    if any(b[k] in ('corroborated','accepted','checked','agreement_confirmed') for k in ('data_verification','claim_status','identity_status','representation_status','legal_status','rnt_status','alliance_status')):require(b['verification_evidence'],'Negocio corroborado sin evidencia')
    for p in ids.values():
        parent=p['parent_place_id']
        if parent:
            require(parent in ids and parent!=p['id'],'Padre inválido')
            require(parent[:5]==p['id'][:5],'Padre de otro departamento')
            seen={p['id']};cur=parent
            while cur:
                require(cur not in seen,'Ciclo territorial');seen.add(cur);cur=ids[cur]['parent_place_id']
    check(read(ROOT/'plantillas/entrega.json'),read(ROOT/'schemas/entrega.schema.json'))
    return inv,manifests
if __name__=='__main__':
    inv,ms=validate();print(f"OK: {len(inv['departments'])} unidades, {len(ms)} lugares, contratos e invariantes válidos. Evidencia remota y factual requiere revisión humana.")
