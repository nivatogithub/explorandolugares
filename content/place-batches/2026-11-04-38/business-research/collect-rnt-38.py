#!/usr/bin/env python3
import json,urllib.parse,urllib.request,collections
from pathlib import Path
p=Path(__file__).resolve().parent
b=json.loads((p/'boundaries.geojson').read_text());codes={f['properties']['tags'].get('divipola'):f['properties']['destination_slug'] for f in b['features'] if f['properties']['tags'].get('divipola')};cov=json.loads((p/'coverage.json').read_text());low={m['destination_slug'] for m in cov['municipalities'] if True};codes.update({'76233':'dagua','20013':'agustin-codazzi','25269':'facatativa','25214':'cota'}); codes.update({str(int(c)):s for c,s in list(codes.items()) if c and c.isdigit()}); ids=[c for c,s in codes.items() if s in low]
params={'$select':'codigo_rnt,cod_mun,municipio,departamento,estado_rnt,razon_social_establecimiento,categoria,sub_categoria,direcci_n_comercial_establecimiento,ano,periodo','$where':"cod_mun in ("+','.join("'"+i+"'" for i in ids)+") AND estado_rnt='ACTIVO' AND ano='2026'",'$limit':'50000'}
url='https://www.datos.gov.co/resource/thwd-ivmp.json?'+urllib.parse.urlencode(params)
req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares research/1.0','Accept':'application/json'})
with urllib.request.urlopen(req,timeout=120) as f:data=json.load(f)
out=[];seen=set()
for d in data:
 k=(d.get('codigo_rnt'),d.get('cod_mun'))
 if k in seen:continue
 seen.add(k);d['destination_slug']=codes[d['cod_mun']];d['status']='registro_activo_fuente_oficial_direccion_no_contrastada';d['coordinates']=None;out.append(d)
result={'source':'https://www.datos.gov.co/d/thwd-ivmp','checked_at':'2026-11-04','scope':'38 municipios','municipalities':sorted(low),'period_filter':'2026','note':'Registro activo en datos publicados; no acredita por sí solo operación actual ni ubicación precisa.','total':len(out),'counts':dict(collections.Counter(x['destination_slug'] for x in out)),'businesses':out}
(p/'rnt-38-municipios.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps({k:v for k,v in result.items() if k!='businesses'},ensure_ascii=False))
