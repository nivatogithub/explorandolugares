#!/usr/bin/env python3
"""Apply only the three approved editorial Jericó experiences to the existing local DB."""
import json,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
if subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()!='preprod':raise SystemExit('Solo se permite preprod.')
catalog=json.loads((ROOT/'content/destinos-dinamicos.json').read_text())
destination=next(d for d in catalog['destinations'] if d['slug']=='jerico')
slugs=['helicoptero-medellin-jerico','parapente-puerto-arturo','experiencia-finca-cafetera']
data={slug:{locale:next(p for p in tr['places'] if p['slug']==slug) for locale,tr in destination['translations'].items()} for slug in slugs}
source='''<?php
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')exit(1);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod;'))exit(1);
$data=json_decode(hex2bin('PAYLOAD'),true,32,JSON_THROW_ON_ERROR);
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->beginTransaction();try{
 foreach($data as $slug=>$translations){
  $q=$p->prepare('SELECT poi.id FROM points_of_interest poi JOIN destinations d ON d.id=poi.destination_id WHERE d.slug="jerico" AND poi.slug=? AND poi.poi_type="experience" FOR UPDATE');$q->execute([$slug]);$id=$q->fetchColumn();if(!$id)throw new RuntimeException('Falta una experiencia esperada: '.$slug);
  foreach($translations as $locale=>$tr){
   if(($tr['publication_status']??'')!=='published'||trim($tr['description']??'')==='')throw new RuntimeException('La experiencia no está preparada para publicación.');
   $q=$p->prepare('SELECT 1 FROM point_of_interest_translations WHERE point_of_interest_id=? AND locale=?');$q->execute([$id,$locale]);if(!$q->fetchColumn())throw new RuntimeException('Falta una traducción esperada.');
   $p->prepare('UPDATE point_of_interest_translations SET name=?,description=?,external_url=?,external_label=? WHERE point_of_interest_id=? AND locale=?')->execute([$tr['name'],$tr['description'],$tr['external_url']??'',$tr['external_label']??'',$id,$locale]);
  }
  $p->prepare('UPDATE points_of_interest SET publication_status="published" WHERE id=?')->execute([$id]);
 }
 $p->commit();echo "Tres experiencias editoriales de Jericó habilitadas solo en preproducción. No se asociaron operadores.\\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
'''.replace('PAYLOAD',json.dumps(data,ensure_ascii=False).encode().hex())
subprocess.run(['podman','exec','-e','EL_PREPROD_LOCAL=1','-i','explorando-preprod','php'],input=source.encode(),check=True)
