from pathlib import Path
import urllib.request,urllib.parse,json,hashlib
p=Path(__file__).parent
rows=[('portada-colpatria','Skyline downtown Bogota.jpg','Tijs Zwinkels','2009-07-30','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','portada skyline urbano Torre Colpatria'),('historia-bolivar','Estatua de Simon Bolivar - Plaza de Bolivar.jpg','Andresilmedia','2012-09-29','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/','historia monumento Simón Bolívar Plaza Bolívar'),('chorro-quevedo','Bogota - Plazza del Chorro de Quevedo (1).JPG','Remi Jouan','2010-12-19','CC BY-SA 3.0 elegida entre licencias múltiples','https://creativecommons.org/licenses/by-sa/3.0/','atractivo La Candelaria / Chorro de Quevedo'),('museo-oro','BOG Museo del Oro.JPG','Felipe Restrepo Acosta','2010-04-06','CC BY-SA 3.0 elegida entre licencias múltiples','https://creativecommons.org/licenses/by-sa/3.0/','atractivo Museo del Oro entrada exterior'),('museo-botero','2019 Bogotá - Entrada del Museo Botero.jpg','Felipe Restrepo Acosta','2019-09-10','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/','atractivo Museo Botero entrada exterior'),('usaquen-mercado','Mercado de Pulgas, Usaquén, Bogotá, Colombia.jpg','Edgar Zuniga Jr.','2015-08-02','CC BY 2.0','https://creativecommons.org/licenses/by/2.0/','atractivo Usaquén mercado'),('parque-simon','Bogota- Parque Simón Bolivar.jpg','Carlosfpardo','2006-12-28','CC BY 2.0','https://creativecommons.org/licenses/by/2.0/','atractivo Parque Simón Bolívar')]
records=[]
for id,title,author,date,lic,lurl,use in rows:
 name=title.replace(' ','_');h=hashlib.md5(name.encode()).hexdigest();url='https://upload.wikimedia.org/wikipedia/commons/'+h[0]+'/'+h[:2]+'/'+urllib.parse.quote(name,safe='')
 out=p/'originales'/f'{id}.jpg'
 record={'id':id,'titulo_original':title,'uso_propuesto':use,'autor':author,'fecha_toma_declarada':date,'consulta_licencia':'2026-10-16','licencia':lic,'licencia_url':lurl,'pagina_evidencia':'https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(title.replace(' ','_'),safe=''),'url_descarga_solicitada':url,'comercial_permitido':True,'adaptacion_permitida':True,'obligaciones':['atribuir autor/título y enlazar fuente/licencia','indicar modificaciones','ShareAlike si licencia BY-SA'],'estado':'pendiente_revision_visual_y_humana','aprobado':False}
 try:
  if not out.exists():
   req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugaresResearch/1.0'})
   with urllib.request.urlopen(req,timeout=60) as res: data=res.read(30000000);record['url_original_resuelta']=res.url
   out.write_bytes(data)
  data=out.read_bytes();record.update(archivo=str(out.relative_to(p)),bytes=len(data),sha256=hashlib.sha256(data).hexdigest(),sha1=hashlib.sha1(data).hexdigest());print(id,'downloaded',len(data),flush=True)
 except Exception as e:record.update(estado='bloqueado_descarga',error=str(e));print(id,type(e).__name__,flush=True)
 records.append(record)
 (p/'manifest.json').write_text(json.dumps({'destino_id':'CO-11-bogota','rama':'lugar/CO/bogota-dc/bogota','alcance':'solo_referencias_no_sustituye_activos','activos':records,'faltantes':['Monserrate desde arriba con santuario completo: búsqueda en curso; no sustituir por vista desde abajo.']},ensure_ascii=False,indent=2)+'\n')
