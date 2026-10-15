#!/usr/bin/env python3
"""Despliegue SFTP de Explorando Lugares. Por defecto solo prepara y muestra el plan."""
from pathlib import Path, PurePosixPath
from datetime import datetime, timezone
import argparse
import hashlib
import json
import re
import secrets
import shutil
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[1]
SAFE_PATH=re.compile(r'^/[A-Za-z0-9_./-]+$')
VERIFY_RELATIVE={
    'public/.htaccess','public/index.html','public/assets/style.css','public/destino.php','public/sitemap.xml',
    'public/admin/base-datos.php','public/admin/negocios.php','public/admin/destinos.php','public/admin/suscripciones.php','public/admin/usuarios.php','public/admin/importar-negocios.php','public/admin/reclamaciones.php','public/admin/solicitudes-cuenta.php','public/admin/_navigation.php','public/reclamar-negocio.php','public/business/register.php','public/business/login.php','public/business/dashboard.php','public/business/logout.php',
    'public/assets/admin-business-location.js','public/assets/admin-business-form.js','public/assets/admin-subscriptions.js','public/assets/admin-accounts.js','public/assets/business-claim-filter.js','public/assets/business-registration.js','public/admin/imagen-referencia-negocio.php',
    'app/bootstrap.php','app/BusinessPortalAccess.php','app/DatabaseMaintenance.php','app/BusinessAdminService.php','app/BusinessSubscriptionAdminService.php','app/AccountAdminService.php','app/BusinessPublicImporter.php','app/BusinessClaimService.php','app/BusinessRepository.php',
    'app/bin/importar-destinos.php','app/bin/importar-negocios.php','app/content/destinos-dinamicos.json','app/content/plantilla-importar-negocios.json','app/database/migrations/009-business-origin.mysql.sql','app/database/migrations/010-business-location-subscription-lifecycle.mysql.sql','app/database/migrations/011-premium-business-story.mysql.sql','app/database/migrations/012-business-image-references.mysql.sql','app/database/migrations/013-accounts-location-map.mysql.sql','app/database/migrations/014-tiered-business-description.mysql.sql','app/database/migrations/015-business-media-quotas.mysql.sql','app/database/migrations/016-plan-content-limits.mysql.sql','app/database/migrations/017-business-claims.mysql.sql','app/database/migrations/018-business-portal.mysql.sql','app/database/migrations/019-business-account-applications.mysql.sql','app/BusinessAccountPortalService.php',
}


def load_config(path):
    data=json.loads(path.read_text())
    allowed={'site','host','port','user','remote_base','public_directory','identity_file','known_hosts_file','public_root'}
    if set(data)-allowed: raise ValueError('La configuración contiene campos desconocidos. No guardar contraseñas aquí.')
    if not {'site','host','port','user','remote_base','public_directory','known_hosts_file'}.issubset(data):
        raise ValueError('Configuración incompleta. Usar deploy/sftp.example.json como plantilla.')
    if data.get('site')!='explorandolugares': raise ValueError('La configuración debe pertenecer a Explorando Lugares.')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',data.get('host','')) or data['host']=='SERVIDOR_SFTP': raise ValueError('Completa el servidor SFTP real.')
    if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]*',data.get('user','')) or data['user']=='USUARIO_SFTP': raise ValueError('Completa el usuario SFTP real.')
    if type(data.get('port')) is not int or not 1<=data['port']<=65535: raise ValueError('Puerto inválido.')
    base=data.get('remote_base','')
    parts=PurePosixPath(base).parts
    if not SAFE_PATH.fullmatch(base) or '..' in parts or '.' in base.split('/') or len(parts)<4 or base.endswith('/') or 'USUARIO' in base:
        raise ValueError('Usa una carpeta remota absoluta, exclusiva y sin segmentos relativos.')
    if any(p.lower() in {'public_html','www','htdocs','httpdocs'} for p in parts):
        raise ValueError('remote_base debe estar fuera de las raíces web; app y private serán hermanas de la carpeta pública.')
    directory=data.get('public_directory','')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_-]*',directory) or directory in {'app','private','releases'}:
        raise ValueError('Nombre de carpeta pública inválido.')
    public_root=data.get('public_root')
    if public_root is not None:
        # Solo el dominio de este proyecto puede usar una raíz web separada.
        if not isinstance(public_root,str):raise ValueError('public_root debe ser una ruta absoluta.')
        root_parts=PurePosixPath(public_root).parts
        if (not isinstance(public_root,str) or not SAFE_PATH.fullmatch(public_root)
            or '..' in root_parts or '.' in public_root.split('/') or public_root.endswith('/')
            or root_parts[-2:]!=('public_html','explorandolugares.com')
            or len(root_parts)<5 or root_parts[:3]!=parts[:3]):
            raise ValueError('public_root debe ser la ruta absoluta public_html/explorandolugares.com de la misma cuenta.')
    for key in ['identity_file','known_hosts_file']:
        value=data.get(key,'')
        if not isinstance(value,str) or any(c in value for c in '\r\n\x00'): raise ValueError('Ruta local inválida.')
    return data


def quote(value):
    # Doble interpretación: ruta literal en el lenguaje batch de SFTP, sin shell.
    if any(c in str(value) for c in '\r\n\x00'): raise ValueError('Ruta con caracteres de control.')
    if any(c in str(value) for c in '*?[]'): raise ValueError('No se permiten patrones en las rutas de transferencia.')
    return '"'+str(value).replace('\\','\\\\').replace('"','\\"')+'"'


class SFTP:
    def __init__(self,config):
        self.config=config
        self.command=['sftp','-F','/dev/null','-b','-','-P',str(config['port']),
            '-oBatchMode=yes','-oStrictHostKeyChecking=yes','-oConnectTimeout=20',
            '-oServerAliveInterval=15','-oServerAliveCountMax=3',
            '-oForwardAgent=no','-oClearAllForwardings=yes','-oPermitLocalCommand=no',
            '-oUserKnownHostsFile='+str(Path(config['known_hosts_file']).expanduser().resolve())]
        if config.get('identity_file'):
            key=Path(config['identity_file']).expanduser().resolve()
            if not key.is_file(): raise ValueError('No existe la clave SSH configurada.')
            self.command+=['-oIdentitiesOnly=yes','-i',str(key)]
        self.command+=[config['user']+'@'+config['host']]

    def run(self,commands):
        if not shutil.which('sftp'): raise ValueError('No está disponible OpenSSH sftp.')
        result=subprocess.run(self.command,input='\n'.join(commands)+'\n',text=True,capture_output=True,timeout=1800)
        if result.returncode:
            raise RuntimeError('SFTP no completó la operación. Revisa conexión, huella, permisos y el diario local.\n'+result.stderr[-2000:])
        return result.stdout


def public_path(config):
    return config.get('public_root') or config['remote_base']+'/'+config['public_directory']


def build(output,config=None):
    for folder in ['ensayos/v1','backend/app','backend/public']:
        source=ROOT/folder
        if source.is_symlink() or any(item.is_symlink() for item in source.rglob('*')):
            raise ValueError('La fuente contiene enlaces simbólicos; no se empaqueta.')
    subprocess.run([sys.executable,str(ROOT/'scripts/preparar-hosting.py'),'--output',str(output)],check=True)
    if config and config.get('public_root'):
        # Con app fuera de public_html, las entradas PHP necesitan su ruta privada.
        for relative in ['api/participacion.php','admin/index.php','admin/base-datos.php','admin/destinos.php','admin/negocios.php','admin/suscripciones.php','admin/usuarios.php','admin/importar-negocios.php','admin/reclamaciones.php','admin/solicitudes-cuenta.php','business/register.php','business/login.php','business/dashboard.php','business/logout.php']:
            entry=output/'public'/relative
            content=entry.read_text()
            old="require dirname(__DIR__,2).'/app/bootstrap.php';"
            if old not in content:raise ValueError('Entrada PHP inesperada; no se publicará una ruta privada incorrecta.')
            entry.write_text(content.replace(old,"require '"+config['remote_base']+"/app/bootstrap.php';",1))
        claim_entry=output/'public/reclamar-negocio.php'
        claim_content=claim_entry.read_text()
        old="require __DIR__.'/../app/bootstrap.php';"
        if old not in claim_content:raise ValueError('Entrada pública de reclamación inesperada; no se publicará una ruta privada incorrecta.')
        claim_entry.write_text(claim_content.replace(old,"require '"+config['remote_base']+"/app/bootstrap.php';",1))
    files=[]
    for kind in ['public','app']:
        for source in sorted((output/kind).rglob('*')):
            if source.is_symlink(): raise ValueError('No transferir enlaces simbólicos.')
            if not source.is_file(): continue
            allowed={'.html','.php','.css','.js','.json','.svg','.webp','.png','.txt','.xml','.zip'}
            if source.suffix in {'.sql','.md'} and kind=='app': allowed.add(source.suffix)
            if source.name!='.htaccess' and source.suffix not in allowed: raise ValueError('Archivo no permitido: '+str(source))
            quote(source)
            files.append({'local':str(source),'relative':source.relative_to(output).as_posix(),
                'bytes':source.stat().st_size,'sha256':hashlib.sha256(source.read_bytes()).hexdigest()})
    if not (output/'public/index.html').is_file() or not (output/'app/bootstrap.php').is_file(): raise ValueError('Entrega incompleta.')
    return files


def stage_commands(config,release_id,files):
    base=config['remote_base'];stage=f'{base}/.el-releases/{release_id}'
    commands=[f'cd {quote(base)}',f'-mkdir {quote(base+"/.el-releases")}',f'mkdir {quote(stage)}']
    directories={str(PurePosixPath(item['relative']).parent) for item in files}
    # Directorios auxiliares vacíos del dominio cPanel; conservar su estructura.
    # Los archivos manuales, si aparecen, deben incorporarse tras inspección previa.
    expanded={'public/.well-known','public/.well-known/acme-challenge','public/cgi-bin'}
    for directory in directories:
        path=PurePosixPath(directory)
        expanded.add(str(path));expanded.update(str(p) for p in path.parents if str(p)!='.')
    for directory in sorted(expanded,key=lambda p:(p.count('/'),p)):
        commands.append(f'mkdir {quote(stage+"/"+directory)}')
    for item in files: commands.append(f'put {quote(item["local"])} {quote(stage+"/"+item["relative"])}')
    return commands


def verify_upload(client,config,release_id,files,directory):
    # Cada `put` de SFTP termina con error si el servidor rechaza un archivo.
    # Descargar solo los puntos de control evita volver a leer todas las imágenes.
    checked=[item for item in files if item['relative'] in VERIFY_RELATIVE]
    missing=VERIFY_RELATIVE-{item['relative'] for item in checked}
    if missing:raise RuntimeError('Faltan archivos clave para verificar: '+', '.join(sorted(missing)))
    downloads=directory/'verification';downloads.mkdir(exist_ok=True)
    stage=f'{config["remote_base"]}/.el-releases/{release_id}'
    # La carga usa orden estable: encontrar el último archivo confirma que SFTP
    # alcanzó el final del lote sin enumerar ni descargar de nuevo las imágenes.
    last=files[-1]
    client.run([f'ls -l {quote(stage+"/"+last["relative"])}'])
    commands=[]
    for index,item in enumerate(checked):
        commands.append(f'get {quote(stage+"/"+item["relative"])} {quote(downloads/str(index))}')
    client.run(commands)
    for index,item in enumerate(checked):
        if hashlib.sha256((downloads/str(index)).read_bytes()).hexdigest()!=item['sha256']:
            raise RuntimeError('La verificación de un archivo clave no coincide. No se activó la versión.')


def baseline_files(directory):
    """Carga el último manifiesto activado para un despliegue incremental."""
    candidates=[]
    for manifest in (directory/'.local/deploy').glob('*/manifest.json'):
        try:
            data=json.loads(manifest.read_text())
        except json.JSONDecodeError:
            continue
        confirmed=data.get('status')=='activated' or (
            data.get('status')=='needs-inspection'
            and data.get('activation')
            and all(item.get('status')=='confirmed' for item in data['activation'])
        )
        if confirmed: candidates.append((manifest.stat().st_mtime,data))
    if not candidates: raise ValueError('No existe una publicación local confirmada para comparar. Usa el despliegue completo una vez.')
    return max(candidates,key=lambda candidate:candidate[0])[1]['files']


def changed_files(previous,current):
    old={item['relative']:item['sha256'] for item in previous}
    new={item['relative']:item for item in current}
    removed=sorted(set(old)-set(new))
    if removed:
        raise ValueError('El modo incremental no elimina archivos. Usa el despliegue completo para retirar: '+', '.join(removed[:3]))
    return [item for item in current if old.get(item['relative'])!=item['sha256']]


def incremental_commands(config,files,removed=()):
    """SFTP directo al destino activo: carga cambios y elimina solo rutas conocidas."""
    directories=set()
    commands=[]
    for item in files:
        relative=PurePosixPath(item['relative'])
        root=public_path(config) if relative.parts[0]=='public' else config['remote_base']
        target=PurePosixPath(root).joinpath(*relative.parts[1:])
        directories.add(str(target.parent))
        commands.append((item,str(target)))
    mkdirs=[f'-mkdir {quote(directory)}' for directory in sorted(directories,key=lambda value:(value.count('/'),value))]
    puts=[f'put {quote(item["local"])} {quote(target)}' for item,target in commands]
    removed_targets=[]
    for relative in removed:
        parts=PurePosixPath(relative)
        root=public_path(config) if parts.parts[0]=='public' else config['remote_base']
        removed_targets.append(str(PurePosixPath(root).joinpath(*parts.parts[1:])))
    # Nunca se eliminan directorios que siguen conteniendo archivos de la nueva entrega.
    current_parents=set()
    for item in files:
        parts=PurePosixPath(item['relative'])
        root=public_path(config) if parts.parts[0]=='public' else config['remote_base']
        current_parents.add(str(PurePosixPath(root).joinpath(*parts.parts[1:]).parent))
    removed_dirs=set()
    for target in removed_targets:
        parent=str(PurePosixPath(target).parent)
        while parent not in current_parents and parent not in {public_path(config),config['remote_base'],'/'}:
            removed_dirs.add(parent); parent=str(PurePosixPath(parent).parent)
    deletes=[f'rm {quote(target)}' for target in removed_targets]
    cleanup=[f'rmdir {quote(directory)}' for directory in sorted(removed_dirs,key=lambda value:value.count('/'),reverse=True)]
    return mkdirs+puts+deletes+cleanup


def activate(client,config,release_id,initial,journal,save):
    base=config['remote_base'];stage=f'{base}/.el-releases/{release_id}'
    # Un despliegue existente debe tener ambos directorios para poder preservar la pareja.
    if not initial:client.run([f'cd {quote(public_path(config))}',f'cd {quote(base+"/app")}'])
    operations=[]
    if not initial:
        operations.extend([(public_path(config),f'{stage}/previous-public'),(f'{base}/app',f'{stage}/previous-app')])
    operations.extend([(f'{stage}/app',f'{base}/app'),(f'{stage}/public',public_path(config))])
    journal['activation']=[];save()
    for source,destination in operations:
        operation={'from':source,'to':destination,'status':'pending'}
        journal['activation'].append(operation);save()
        client.run([f'rename {quote(source)} {quote(destination)}'])
        operation['status']='confirmed';save()
    journal['status']='activated';save()


def activate_uploaded(client,config,release_id,journal,save):
    base=config['remote_base']
    client.run([f'cd {quote(base)}',f'mkdir {quote(base+"/.el-deploy-lock")}'])
    journal['status']='activating';save()
    try:
        activate(client,config,release_id,False,journal,save)
        client.run([f'rmdir {quote(base+"/.el-deploy-lock")}'])
    except (RuntimeError,subprocess.TimeoutExpired,KeyboardInterrupt):
        journal['status']='needs-inspection';save()
        raise


def main(argv=None):
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json')
    parser.add_argument('--upload',action='store_true',help='Subir a una carpeta de versión; no cambia el sitio.')
    parser.add_argument('--activate',action='store_true',help='Subir y reemplazar public/app conservando la pareja previa.')
    parser.add_argument('--initial',action='store_true',help='Primera instalación: no deben existir las carpetas public/app de destino.')
    parser.add_argument('--incremental',action='store_true',help='Sube solo archivos modificados desde la última publicación confirmada.')
    parser.add_argument('--remove-removed',action='store_true',help='Con --incremental, retira solo archivos conocidos que ya no existen localmente.')
    parser.add_argument('--verify-release',help='Reanuda la verificación de un staging local interrumpido; no sube ni activa archivos.')
    parser.add_argument('--activate-release',help='Activa un release previamente subido y verificado, sin transferirlo de nuevo.')
    parser.add_argument('--confirm-target',help='Para activar, repetir exactamente remote_base de la configuración.')
    args=parser.parse_args(argv)
    if args.initial and not args.activate: parser.error('--initial requiere --activate')
    if args.activate_release and (args.initial or args.activate or args.incremental or args.upload or args.verify_release):parser.error('--activate-release no se combina con otras acciones.')
    if args.incremental and args.initial: parser.error('--incremental no admite --initial')
    if args.remove_removed and not args.incremental: parser.error('--remove-removed requiere --incremental')
    config=load_config(args.config.expanduser().resolve())
    if (args.activate or args.activate_release) and args.confirm_target!=config['remote_base']:parser.error('Para activar, indica --confirm-target con remote_base exacto.')
    if args.verify_release:
        if not re.fullmatch(r'\d{8}T\d{6}Z-[a-f0-9]{6}',args.verify_release):parser.error('Identificador de release inválido.')
        directory=ROOT/'.local/deploy'/args.verify_release
        manifest=directory/'manifest.json'
        if not manifest.is_file():raise ValueError('No existe el diario local de ese release.')
        journal=json.loads(manifest.read_text())
        if journal.get('site')!='explorandolugares' or journal.get('remote_base')!=config['remote_base'] or journal.get('status')!='needs-inspection':
            raise ValueError('Solo se puede reanudar la verificación de un release propio interrumpido en el mismo destino.')
        files=journal.get('files',[])
        for item in files:
            local=directory/'build'/item['relative']
            if not local.is_file() or hashlib.sha256(local.read_bytes()).hexdigest()!=item['sha256']:
                raise ValueError('La entrega local no coincide con el manifiesto; no se verificó el staging.')
        client=SFTP(config)
        verify_upload(client,config,args.verify_release,files,directory)
        journal['status']='uploaded';journal['verified_after_interruption']=True
        manifest.write_text(json.dumps(journal,indent=2,ensure_ascii=False)+'\n')
        print(f'Release {args.verify_release} verificado y disponible en staging. El sitio activo no cambió.')
        return 0
    if args.activate_release:
        if not re.fullmatch(r'\d{8}T\d{6}Z-[a-f0-9]{6}',args.activate_release):parser.error('Identificador de release inválido.')
        directory=ROOT/'.local/deploy'/args.activate_release
        manifest=directory/'manifest.json'
        if not manifest.is_file():raise ValueError('No existe el diario local de ese release.')
        journal=json.loads(manifest.read_text())
        if journal.get('site')!='explorandolugares' or journal.get('remote_base')!=config['remote_base'] or journal.get('status')!='uploaded':
            raise ValueError('Solo se puede activar un release propio, verificado y subido al mismo destino.')
        files=journal.get('files',[])
        for item in files:
            local=directory/'build'/item['relative']
            if not local.is_file() or hashlib.sha256(local.read_bytes()).hexdigest()!=item['sha256']:
                raise ValueError('La entrega local no coincide con el manifiesto; no se activó.')
        client=SFTP(config)
        verify_upload(client,config,args.activate_release,files,directory)
        activate_uploaded(client,config,args.activate_release,journal,lambda:manifest.write_text(json.dumps(journal,indent=2,ensure_ascii=False)+'\n'))
        print(f'Release {args.activate_release} activado. Verifica el sitio en HTTPS y el formulario.')
        return 0
    release_id=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+secrets.token_hex(3)
    directory=ROOT/'.local/deploy'/release_id;directory.mkdir(parents=True)
    files=build(directory/'build',config)
    previous=baseline_files(ROOT) if args.incremental else []
    removed=sorted({item['relative'] for item in previous}-{item['relative'] for item in files})
    if args.incremental and removed and not args.remove_removed:
        raise ValueError('El modo incremental no elimina archivos. Usa --remove-removed para retirar únicamente rutas conocidas: '+', '.join(removed[:3]))
    selected=changed_files(previous,files) if args.incremental and not removed else ([item for item in files if {entry['relative']:entry['sha256'] for entry in previous}.get(item['relative'])!=item['sha256']] if args.incremental else files)
    journal={'site':'explorandolugares','release':release_id,'host':config['host'],'remote_base':config['remote_base'],
             'public_directory':config['public_directory'],'public_root':public_path(config),'files':files,'changed_files':len(selected),'removed_files':len(removed),'status':'planned'}
    path=directory/'manifest.json'
    def save():path.write_text(json.dumps(journal,indent=2,ensure_ascii=False)+'\n')
    save()
    print(f'Explorando Lugares → {config["host"]}:{public_path(config)}')
    print(f'{len(selected)} archivos para transferir; {sum(x["bytes"] for x in selected)/1024/1024:.1f} MiB. Diario: {path}')
    print('No se transfieren private/, configuración real, base de datos ni credenciales; las migraciones SQL van bajo app/database, fuera de la web.')
    if not (args.upload or args.activate or args.incremental): print('Solo revisión: no se conectó al servidor. Usa --upload, --activate o --incremental para publicar.');return 0
    known_hosts=Path(config['known_hosts_file']).expanduser()
    if not known_hosts.is_file():raise ValueError('Falta el archivo de huellas verificadas. Consulta docs/DESPLIEGUE_SFTP.md.')
    client=SFTP(config)
    # mkdir exclusivo: impide dos transferencias concurrentes contra el mismo destino.
    client.run([f'cd {quote(config["remote_base"])}',f'mkdir {quote(config["remote_base"]+"/.el-deploy-lock")}'])
    journal['status']='uploading';save()
    try:
        if args.incremental:
            client.run(incremental_commands(config,selected,removed))
            journal['status']='activated';save()
        else:
            client.run(stage_commands(config,release_id,files))
            verify_upload(client,config,release_id,files,directory)
            journal['status']='uploaded';save()
            if args.activate: activate(client,config,release_id,args.initial,journal,save)
        client.run([f'rmdir {quote(config["remote_base"]+"/.el-deploy-lock")}'])
    except (RuntimeError,subprocess.TimeoutExpired,KeyboardInterrupt) as error:
        journal['status']='needs-inspection';save()
        print('Se detuvo el despliegue. No se borraron archivos remotos. Si hay un rename pendiente, su resultado es incierto: inspeccionar el servidor antes de reintentar.',file=sys.stderr)
        raise error
    if args.incremental:print('Actualización incremental completada. Se subieron únicamente archivos modificados'+(' y se retiraron rutas conocidas.' if removed else ', sin descargar la entrega completa.'))
    elif args.activate:print('Transferencia y activación completadas. Verifica HTTPS, PHP y formulario; SFTP no comprueba el funcionamiento del sitio.')
    else:print(f'Versión subida a {config["remote_base"]}/.el-releases/{release_id}. El sitio activo no cambió.')
    return 0


if __name__=='__main__':
    try:sys.exit(main())
    except KeyboardInterrupt:
        print('Interrumpido. Si había una transferencia activa, revisa el diario y el bloqueo remoto antes de continuar.',file=sys.stderr);sys.exit(130)
    except (ValueError,OSError,RuntimeError,subprocess.SubprocessError) as error:
        print(str(error),file=sys.stderr);sys.exit(1)
