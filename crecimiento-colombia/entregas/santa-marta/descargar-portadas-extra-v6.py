from pathlib import Path
import json,urllib.parse,subprocess,hashlib
r=Path(__file__).resolve().parent;out=r/'imagenes/revision-v6/originales';out.mkdir(parents=True,exist_ok=True)
rows=[]
for i,num in enumerate([1,4],3):
 title=f'20251104País de la Belleza- IV Cumbre CELAC- UE, Santa Marta- Juan Cano ({num}).jpg'
 query='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(dict(action='query',format='json',prop='imageinfo',iiprop='url|size|extmetadata',titles='File:'+title))
 meta=out/f'SM-H6-0{i}-commons.json';res=subprocess.CompletedProcess([],0) if meta.exists() else subprocess.run(['curl','--fail','--location','--max-time','25','--output',str(meta),query],capture_output=True)
 row=dict(id=f'SM-H6-0{i}',component='hero',source='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(title.replace(' ','_')),title=title,approval=False)
 if res.returncode:row['status']='metadata_download_failed';rows.append(row);print(row,flush=True);continue
 try:
  doc=json.loads(meta.read_text());info=next(iter(doc['query']['pages'].values()))['imageinfo'][0];ext=info['extmetadata'];lic=ext.get('LicenseShortName',{}).get('value','');row.update(author=ext.get('Artist',{}).get('value',''),license=lic,license_url=ext.get('LicenseUrl',{}).get('value',''),source_dimensions=[info['width'],info['height']],original_url=info['url'])
  allowed=lic in ['CC BY 2.0','CC BY-SA 2.0','CC BY 3.0','CC BY-SA 3.0','CC BY 4.0','CC BY-SA 4.0','CC0']
  if lic=='Public domain':
   # Reuse already-downloaded imageinfo; inspect underlying licensing template as independent evidence.
   wiki=out/f'SM-H6-0{i}-wikitext.json'
   if not wiki.exists():
    q='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(dict(action='query',format='json',prop='revisions',rvprop='content',rvslots='main',titles='File:'+title))
    got=subprocess.run(['curl','--fail','--location','--max-time','25','--output',str(wiki),q],capture_output=True)
   if wiki.exists():
    wd=json.loads(wiki.read_text());page=next(iter(wd['query']['pages'].values()));wtext=page['revisions'][0]['slots']['main']['*'];low=wtext.lower()
    row['wikitext_evidence']=str(wiki.relative_to(r))
    release=any(t in low for t in ['{{pd-author','{{pd-self','{{flickr-public domain mark','{{flickr-public-domain-mark','{{flickr-public_domain_mark','{{cc-zero','{{pd-usgov'])
    flags=(ext.get('Copyrighted',{}).get('value','').lower()=='false' and ext.get('License',{}).get('value','')=='pd')
    official_source=('flickr.com/photos/197399771' in low)
    allowed=release and flags and official_source
    row['pd_verification']=dict(template_found=release,copyright_flags=flags,official_flickr_source=official_source,scope='visual reference research only; publication and AI require separate review')
  if not allowed:
   row['status']='licensing_basis_pending';rows.append(row);print(row['id'],row['status'],flush=True);continue
  f=out/(row['id']+'.jpg');res=subprocess.CompletedProcess([],0) if f.exists() else subprocess.run(['curl','--fail','--location','--max-time','30','--output',str(f),info['url']],capture_output=True)
  if res.returncode:row['status']='original_download_failed'
  else:row.update(status='downloaded_pending_visual_review',path=str(f.relative_to(r)),sha256=hashlib.sha256(f.read_bytes()).hexdigest())
 except (KeyError,ValueError,StopIteration) as err:row['status']='metadata_parse_failed';row['error']=str(err)
 rows.append(row);print(row['id'],row['status'],flush=True)
(r/'portadas-extra-v6.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
