from pathlib import Path
import json,urllib.parse,subprocess,hashlib
r=Path(__file__).resolve().parent;out=r/'imagenes/revision-v6/originales';out.mkdir(parents=True,exist_ok=True)
rows=[]
for i,num in enumerate([1,4],3):
 title=f'20251104País de la Belleza- IV Cumbre CELAC- UE, Santa Marta- Juan Cano ({num}).jpg'
 query='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(dict(action='query',format='json',prop='imageinfo',iiprop='url|size|extmetadata',titles='File:'+title))
 meta=out/f'SM-H6-0{i}-commons.json';res=subprocess.run(['curl','--fail','--location','--max-time','25','--output',str(meta),query],capture_output=True)
 row=dict(id=f'SM-H6-0{i}',component='hero',source='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(title.replace(' ','_')),title=title,approval=False)
 if res.returncode:row['status']='metadata_download_failed';rows.append(row);print(row,flush=True);continue
 try:
  doc=json.loads(meta.read_text());info=next(iter(doc['query']['pages'].values()))['imageinfo'][0];ext=info['extmetadata'];lic=ext.get('LicenseShortName',{}).get('value','');row.update(author=ext.get('Artist',{}).get('value',''),license=lic,license_url=ext.get('LicenseUrl',{}).get('value',''),source_dimensions=[info['width'],info['height']],original_url=info['url'])
  if lic not in ['CC BY 2.0','CC BY-SA 2.0','CC BY 3.0','CC BY-SA 3.0','CC BY 4.0','CC BY-SA 4.0','CC0']:
   row['status']='license_not_approved_for_download';rows.append(row);continue
  f=out/(row['id']+'.jpg');res=subprocess.run(['curl','--fail','--location','--max-time','30','--output',str(f),info['url']],capture_output=True)
  if res.returncode:row['status']='original_download_failed'
  else:row.update(status='downloaded_pending_visual_review',path=str(f.relative_to(r)),sha256=hashlib.sha256(f.read_bytes()).hexdigest())
 except (KeyError,ValueError,StopIteration) as err:row['status']='metadata_parse_failed';row['error']=str(err)
 rows.append(row);print(row['id'],row['status'],flush=True)
(r/'portadas-extra-v6.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
