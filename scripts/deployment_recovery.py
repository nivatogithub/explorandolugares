"""Recoverable, per-file deployment. No full-site copies or parallel releases."""
from pathlib import Path
import hashlib, json, secrets, os


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def save(path, data):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True);os.chmod(path.parent,0o700)
    temp=path.with_suffix('.writing');temp.write_text(json.dumps(data,indent=2)+'\n');os.chmod(temp,0o600);temp.replace(path)


def protected_deploy(api, client, config, selected, previous, manifest_path, recovery_root, local_manifest):
    if not previous:raise ValueError('Se requiere un manifiesto previo confirmado para recuperación incremental.')
    recovery_root=Path(recovery_root)
    for pending in recovery_root.glob('*/journal.json'):
        if json.loads(pending.read_text()).get('state') not in {'finalized'}:
            raise ValueError('Hay una entrega interrumpida pendiente de recuperar: '+str(pending))
    run_id=secrets.token_hex(12);folder=recovery_root/run_id;folder.mkdir(parents=True,mode=0o700)
    old={x['relative']:x for x in previous['files']};q=api.quote
    remote_manifest=config['remote_base']+'/'+api.MANIFEST_NAME
    stage=config['remote_base']+'/private/.incremental-'+run_id
    journal={'state':'preparing','site':config['site'],'host':config['host'],'remote_base':config['remote_base'],'public_root':api.public_path(config),'stage':stage,'entries':[]}
    commands=[]
    # Download only files about to be changed. No config, media library or whole-site copy.
    for i,item in enumerate(selected):
        rel=item['relative'];backup=folder/('before-'+str(i));target=api.remote_path(config,rel)
        entry={'relative':rel,'target':target,'stage':stage+'/'+str(i),'after':item['sha256'],'before':old.get(rel,{}).get('sha256'),'backup':str(backup)}
        journal['entries'].append(entry)
        if entry['before']:commands.append(f'get {q(target)} {q(backup)}')
        else:
            probe=folder/('unexpected-'+str(i));commands.append(f'-get {q(target)} {q(probe)}')
    before_manifest=folder/'manifest-before.json'
    client.run(commands+[f'get {q(remote_manifest)} {q(before_manifest)}'])
    if json.loads(before_manifest.read_text())!=previous:raise ValueError('El manifiesto cambió durante la preparación.')
    for i,entry in enumerate(journal['entries']):
        if entry['before'] and digest(entry['backup'])!=entry['before']:raise ValueError('El archivo remoto cambió fuera del manifiesto: '+entry['relative'])
        if not entry['before'] and (folder/('unexpected-'+str(i))).exists():raise ValueError('Existe un archivo remoto no inventariado: '+entry['relative'])
        if entry['before']:os.chmod(entry['backup'],0o600)
    journal['before_manifest']=str(before_manifest);journal['new_manifest']=str(folder/'manifest-after.json')
    Path(journal['new_manifest']).write_bytes(Path(manifest_path).read_bytes())
    journal['state']='prepared';save(folder/'journal.json',journal)
    # Private temporary bytes only; live URLs still serve the old complete file.
    commands=[f'-mkdir {q(stage)}',f'chmod 700 {q(stage)}']
    directories=set()
    for entry,item in zip(journal['entries'],selected):
        parent=Path(entry['target']).parent;root=Path(api.public_path(config) if entry['relative'].startswith('public/') else config['remote_base'])
        while parent!=root and str(parent)!='/':directories.add(str(parent));parent=parent.parent
        commands += [f'put {q(item["local"])} {q(entry["stage"])}',f'chmod 644 {q(entry["stage"])}']
    commands += [f'-mkdir {q(p)}' for p in sorted(directories,key=lambda x:(x.count('/'),x))]
    commands += [f'put {q(journal["new_manifest"])} {q(stage+"/manifest")}']
    client.run(commands)
    verification=folder/'verified';verification.mkdir(mode=0o700)
    client.run([f'get {q(e["stage"])} {q(verification/str(i))}' for i,e in enumerate(journal['entries'])]+[f'get {q(stage+"/manifest")} {q(verification/"manifest")}'])
    for i,e in enumerate(journal['entries']):
        if digest(verification/str(i))!=e['after']:raise ValueError('Transferencia incompleta; no se sustituyó el sitio.')
    if digest(verification/'manifest')!=digest(journal['new_manifest']):raise ValueError('Manifiesto temporal incompleto.')
    # No dependency is published until every modified file has arrived intact.
    order=lambda e:(0 if e['before'] is None else 1,0 if e['relative'].startswith('app/') else (1 if e['relative'].startswith('public/assets/') else 2),e['relative'])
    journal['entries'].sort(key=order);journal['state']='publishing';save(folder/'journal.json',journal)
    commands=[f'rename {q(e["stage"])} {q(e["target"])}' for e in journal['entries']]
    commands += [f'rename {q(stage+"/manifest")} {q(remote_manifest)}']
    try:client.run(commands)
    except BaseException:
        journal['state']='interrupted';save(folder/'journal.json',journal)
        raise RuntimeError('Entrega interrumpida. No se restauró la base de datos. Recuperación: '+str(folder/'journal.json')) from None
    journal['state']='complete';save(folder/'journal.json',journal)
    Path(local_manifest).write_bytes(Path(journal['new_manifest']).read_bytes())
    try:client.run([f'-rmdir {q(stage)}'])
    except Exception:pass  # A failed cleanup cannot undo an otherwise completed deployment.
    return folder/'journal.json'


def recover(api,client,config,journal_path,local_manifest):
    journal_path=Path(journal_path);j=json.loads(journal_path.read_text());q=api.quote
    if j.get('state') in {'finalized','validated'}:raise ValueError('Contingencia ya cerrada: recuperar versiones anteriores desde Git.')
    expected=(config['site'],config['host'],config['remote_base'],api.public_path(config))
    if tuple(j.get(k) for k in ['site','host','remote_base','public_root'])!=expected:raise ValueError('El registro pertenece a otro sitio.')
    # Validate every local backup and remote target before restoring even the first byte.
    folder=journal_path.parent;probe=folder/'recovery-probe';probe.mkdir(exist_ok=True,mode=0o700)
    commands=[]
    for i,e in enumerate(j['entries']):
        if e['target']!=api.remote_path(config,e['relative']):raise ValueError('Ruta de recuperación inválida.')
        if e['before'] and digest(e['backup'])!=e['before']:raise ValueError('Respaldo local alterado.')
        path=probe/str(i);path.unlink(missing_ok=True);commands.append(f'-get {q(e["target"])} {q(path)}')
    client.run(commands)
    for i,e in enumerate(j['entries']):
        path=probe/str(i);current=digest(path) if path.exists() else None
        if current not in {e['before'],e['after']}:raise ValueError('Cambio ajeno detectado; no se restaura: '+e['relative'])
    old_manifest=Path(j['before_manifest']);new_manifest=Path(j['new_manifest'])
    remote_manifest=config['remote_base']+'/'+api.MANIFEST_NAME
    manifest_probe=probe/'manifest';client.run([f'get {q(remote_manifest)} {q(manifest_probe)}'])
    if digest(manifest_probe) not in {digest(old_manifest),digest(new_manifest)}:raise ValueError('El manifiesto pertenece a otra entrega; no se restaura.')
    if not j['stage'].startswith(config['remote_base']+'/private/.incremental-'):raise ValueError('Directorio temporal inválido.')
    client.run([f'-mkdir {q(j["stage"])}',f'chmod 700 {q(j["stage"])}'])
    j['state']='recovering';save(journal_path,j)
    # Reverse publication order. Files introduced by this attempt are removed only when intact.
    for e in reversed(j['entries']):
        if e['before']:
            temporary=e['stage']+'-recover'
            client.run([f'put {q(e["backup"])} {q(temporary)}',f'chmod 644 {q(temporary)}',f'rename {q(temporary)} {q(e["target"])}'])
        else:client.run([f'-rm {q(e["target"])}'])
    tmp=j['stage']+'/manifest-recover'
    client.run([f'put {q(old_manifest)} {q(tmp)}',f'rename {q(tmp)} {q(remote_manifest)}'])
    Path(local_manifest).write_bytes(old_manifest.read_bytes());j['state']='recovered';save(journal_path,j)
    return j


def finalize(api,client,config,journal_path,report_path):
    """Purge only this delivery's temporary bytes after recorded functional validation."""
    import shutil
    from datetime import datetime,timezone
    journal_path=Path(journal_path).resolve();folder=journal_path.parent
    j=json.loads(journal_path.read_text());report=json.loads(Path(report_path).read_text());q=api.quote
    if j.get('state') not in {'complete','recovered','validated'}:raise ValueError('No se limpia una entrega interrumpida o sin recuperar.')
    if tuple(j.get(k) for k in ['site','host','remote_base','public_root'])!=(config['site'],config['host'],config['remote_base'],api.public_path(config)):raise ValueError('Registro de otro sitio.')
    source_state=j.get('validated_from',j['state'])
    if j['state']=='validated':expected=j['validated_manifest']
    else:
        expected_path=Path(j['new_manifest'] if source_state=='complete' else j['before_manifest'])
        if expected_path.resolve().parent!=folder:raise ValueError('Manifiesto fuera del registro.')
        expected=json.loads(expected_path.read_text())
    required={'public_pages','accounts','statistics','assets'}
    checks=report.get('checks',{})
    if report.get('schema_version')!=1 or not expected.get('commit') or report.get('commit')!=expected['commit'] or not isinstance(checks,dict) or not required.issubset(checks) or not all(value is True for value in checks.values()):raise ValueError('Falta validación funcional aprobada para esta versión exacta.')
    probe=folder/'final-validation';probe.mkdir(exist_ok=True,mode=0o700)
    remote_manifest=config['remote_base']+'/'+api.MANIFEST_NAME
    client.run([f'get {q(remote_manifest)} {q(probe/"manifest")}'])
    if json.loads((probe/'manifest').read_text())!=expected:raise ValueError('Cambió la versión publicada; se conservan los respaldos.')
    # Verify live bytes too; functional evidence alone cannot authorize deleting a
    # contingency for files that no longer match the tested delivery.
    commands=[]
    for i,e in enumerate(j['entries']):
        if e['target']!=api.remote_path(config,e['relative']):raise ValueError('Ruta fuera del paquete.')
        (probe/str(i)).unlink(missing_ok=True)
        commands.append(f'-get {q(e["target"])} {q(probe/str(i))}')
    client.run(commands)
    for i,e in enumerate(j['entries']):
        wanted=e['after'] if source_state=='complete' else e['before'];p=probe/str(i)
        if (digest(p) if p.exists() else None)!=wanted:raise ValueError('Contenido distinto al validado; no se eliminan respaldos.')
    if not j['stage'].startswith(config['remote_base']+'/private/.incremental-'):raise ValueError('Temporal remoto inválido.')
    commands=[]
    for e in j['entries']:commands += [f'-rm {q(e["stage"])}',f'-rm {q(e["stage"]+"-recover")}']
    commands += [f'-rm {q(j["stage"]+"/manifest")}',f'-rm {q(j["stage"]+"/manifest-recover")}',f'-rmdir {q(j["stage"])}']
    client.run(commands)
    receipt={'state':'finalized','site':j['site'],'host':j['host'],'remote_base':j['remote_base'],'public_root':j['public_root'],'commit':expected['commit'],'validated_at':datetime.now(timezone.utc).isoformat(timespec='seconds'),'files':len(j['entries']),'checks':checks,'temporary_backups_removed':True}
    j.update(state='validated',validated_from=source_state,validated_manifest=expected)
    save(journal_path,j)  # Retryable even if local cleanup is interrupted.
    # Limit deletion to this tool's own local delivery folder. No paths from the
    # journal are used as deletion targets; DB backups live elsewhere.
    for child in folder.iterdir():
        if child==journal_path:continue
        if child.is_symlink() or child.is_file():child.unlink()
        elif child.is_dir():shutil.rmtree(child)
    save(journal_path,receipt)
    return receipt
