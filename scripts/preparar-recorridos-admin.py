#!/usr/bin/env python3
"""Add deterministic synthetic walkthrough cases to this worker's local SQLite only."""
from pathlib import Path
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
import json
import sqlite3
import uuid

ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / '.local/revision-admin-operativa/private'
DB_PATH = PRIVATE / 'review.sqlite'
if DB_PATH.is_symlink() or not DB_PATH.is_file() or PRIVATE.is_symlink():
    raise SystemExit('Se requiere la base SQLite de revisión local ya creada; no se acepta otra ruta.')
db = sqlite3.connect(DB_PATH)
db.execute('PRAGMA foreign_keys=ON')
now = datetime.now(ZoneInfo('America/Bogota'))
backup = PRIVATE / ('before-walkthrough-' + now.strftime('%Y%m%d-%H%M%S-%f') + '.sqlite')
with sqlite3.connect(backup) as target:
    db.backup(target)
backup.chmod(0o600)
destination = db.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()
if not destination:
    raise SystemExit('No se encontró el destino de prueba; no se modifica el catálogo.')
destination_id = destination[0]
namespace = uuid.UUID('e381047f-5635-4c69-9a19-90655674732d')
manifest = []
cases = [
 ('01','Importado Gratis sin cuenta','public_import','draft','free','pending_publication',None,False),
 ('02','Registrado Gratis publicado','owner_submitted','published','free','active',None,True),
 ('03','Pago vence en cinco días','owner_submitted','published','featured','active',5,True),
 ('04','Página vencida','owner_submitted','published','page','expired',-3,True),
 ('05','Premium vigente','owner_submitted','published','page_premium','active',60,True),
 ('06','Pago confirmado pendiente de publicación','owner_submitted','draft','page','paid_pending_publication',None,True),
 ('07','Importado reclamado con cuenta','public_import','published','free','active',None,True),
 ('08','Solicitud sin documentos','owner_submitted','draft','free','pending_publication',None,True),
]
try:
    with db:
        for code, label, origin, publication, plan, status, days, associated in cases:
            name = 'RECORRIDO ' + code + ' · ' + label
            public_id = str(uuid.uuid5(namespace, 'business-' + code))
            existing = db.execute('SELECT id FROM businesses WHERE public_id=?', (public_id,)).fetchone()
            if existing:
                business_id = existing[0]
            else:
                business_id = db.execute('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?,?)', (public_id,destination_id,'hospedaje',origin,'recorrido-admin-'+code,'ACTIVO' if associated else 'PUBLICO','RECLAMADO' if associated else 'NO_RECLAMADO','ABIERTO',publication)).lastrowid
                db.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)',(business_id,'es',name,'Datos ficticios para revisión local.','Escenario sintético de administración. No representa un negocio real.'))
                starts = (now-timedelta(days=25)).strftime('%Y-%m-%d %H:%M:%S') if days is not None else None
                ends = (now+timedelta(days=days)).strftime('%Y-%m-%d %H:%M:%S') if days is not None else None
                db.execute('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,currency) VALUES(?,?,?,?,?,?,?)',(business_id,plan,'monthly' if plan!='free' else None,status,starts,ends,'COP'))
                email = 'recorrido-'+code+'@example.invalid'
                db.execute('INSERT INTO business_research_contacts(business_id,channel,contact_value,source_url,consulted_at,evidence_status,source_note,legal_basis_status,record_hash) VALUES(?,?,?,?,?,?,?,?,?)',(business_id,'email',email,'https://example.invalid/recorrido/'+code,now.date().isoformat(),'PUBLICADO_PARA_CLIENTES','Contacto ficticio. Sin autorización de campañas.','PENDIENTE','recorrido-admin-'+code))
                if associated:
                    account_id = db.execute('INSERT INTO accounts(public_id,account_type,display_name,email,status,email_verified_at) VALUES(?,?,?,?,?,?)',(str(uuid.uuid5(namespace,'account-'+code)),'traveler','Persona ficticia '+code,email,'active',now.strftime('%Y-%m-%d %H:%M:%S'))).lastrowid
                    db.execute('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,?)',(account_id,business_id,'owner','active'))
                    if code == '08':
                        db.execute('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version,status,business_details_json) VALUES(?,?,?,?,?,?,?,?,?,?)',(account_id,'claim_existing',business_id,destination_id,'hospedaje',name,'owner','synthetic-walkthrough','pending',json.dumps({'requested_subscription':{'plan_code':'free'}})))
            manifest.append({'code':code,'name':name,'business_id':business_id,'public_id':public_id,'expected_origin':origin,'expected_plan':plan,'expected_status':status,'associated':associated})
    manifest_path=PRIVATE/'walkthrough-cases.json'
    manifest_path.write_text(json.dumps({'created_at':now.isoformat(),'synthetic_only':True,'cases':manifest},ensure_ascii=False,indent=2)+'\n')
    manifest_path.chmod(0o600)
    print('Preparados',len(manifest),'escenarios sintéticos en la instancia local. Sin reemplazar registros existentes, enviar correos ni cambiar producción.')
    print('Fechas calculadas con America/Bogota. Se conservó respaldo previo privado.')
finally:
    db.close()
