<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/advance/AdminLayout.php';
if (isset($_SESSION['admin']) && time()-(int)($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
if (empty($_SESSION['admin'])) { http_response_code(403); exit('Inicia sesión desde el panel de administración.'); }
$_SESSION['last_seen']=time();
$appRoot=dirname((new ReflectionFunction('db'))->getFileName());
require_once $appRoot.'/DatabaseMaintenance.php';
$maintenance=null;$report=null;$error='';$success='';$details=[];
try{$maintenance=new DatabaseMaintenance(db(),$appRoot,(string)($_SERVER['DOCUMENT_ROOT']??''));}
catch(PDOException $e){$error='No se pudo conectar a la base de datos. Revisa el archivo de configuración privado, el servidor y los permisos.';http_response_code(503);}
if($maintenance&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();$action=(string)($_POST['action']??'');$confirmation=is_string($_POST['confirmation']??null)?trim($_POST['confirmation']):'';
        if($action==='install'&&hash_equals('CREAR ESQUEMA',$confirmation)){$maintenance->installBaseSchema();$success='Esquema base instalado. Revisa el estado y aplica las migraciones pendientes.';}
        elseif($action==='migrate'&&hash_equals('APLICAR MIGRACIONES',$confirmation)){$details=$maintenance->applyMigrations();$success='Revisión de migraciones completada.';}
        elseif($action==='import'&&hash_equals('IMPORTAR DESTINOS',$confirmation)){$details=$maintenance->importInitialCatalog();$success='Catálogo inicial importado. Los estados de publicación provienen del catálogo aprobado.';}
        else throw new RuntimeException('Acción o confirmación no válida. No se hicieron cambios.');
    }catch(PDOException $e){$error='La base rechazó la operación. Revisa permisos y el esquema; vuelve a cargar esta página para ver el estado real.';http_response_code(400);}
    catch(Throwable $e){$error=$e->getMessage();http_response_code(400);}
}
if($maintenance){try{$report=$maintenance->inspect();}
catch(PDOException $e){$report=null;$error=$error?:'No se pudo consultar el estado de la base de datos. Revisa la configuración privada y los permisos.';http_response_code(503);}}
function db_state_label(?string $state):string{return match($state){'applied'=>'Aplicada','pending'=>'Pendiente','partial'=>'Incompleta',default=>'No disponible'};}
?><?php adminPageStart('database','Herramientas de base de datos','Comprueba el esquema y aplica las migraciones pendientes sin sustituir el catálogo existente.'); ?>
<?php if($error!==''):?><div class="notice" role="alert"><?=esc($error)?></div><?php endif?><?php if($success!==''):?><div class="notice" role="status"><?=esc($success)?></div><?php endif?><?php if($details):?><ul><?php foreach($details as $key=>$value):?><li><code><?=esc($key)?></code> · <?=esc(is_scalar($value)?(string)$value:'Completado')?></li><?php endforeach?></ul><?php endif?>
<?php if(is_array($report)):?><section class="admin-system"><h2>Estado actual</h2><dl><dt>Conexión</dt><dd><?=esc($report['connected']?'Conectada':'No disponible')?></dd><dt>Motor</dt><dd><?=esc($report['driver'])?></dd><dt>Tablas</dt><dd><?=esc($report['tables'])?></dd><?php foreach($report['counts'] as $table=>$count):?><dt>Registros en <?=esc($table)?></dt><dd><?=$count===null?'Tabla ausente':esc($count)?></dd><?php endforeach?></dl><h3>Migraciones conocidas</h3><ul><?php foreach($report['migrations'] as $id=>$state):?><li><code><?=esc($id)?></code> · <?=esc(db_state_label($state))?></li><?php endforeach?></ul></section>
<section class="admin-system"><h2>Operaciones disponibles</h2><p>Las acciones están limitadas a los archivos incluidos con el portal. Cada operación exige escribir la confirmación que aparece en su botón.</p>
<?php if($report['empty_database']):?><form method="post" class="admin-db-action"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="install"><label>Confirmación<input name="confirmation" required autocomplete="off" placeholder="CREAR ESQUEMA"></label><button class="pill">Instalar esquema base vacío</button><small>Solo se habilita para una base sin tablas.</small></form><?php endif?>
<?php if($report['base_schema']):?><form method="post" class="admin-db-action"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="migrate"><label>Confirmación<input name="confirmation" required autocomplete="off" placeholder="APLICAR MIGRACIONES"></label><button class="pill">Aplicar migraciones pendientes</button><small>Reconoce columnas y tablas existentes para no repetir las ya aplicadas.</small></form><?php endif?>
<?php if($report['catalog_empty']&&$report['driver']==='mysql'):?><form method="post" class="admin-db-action"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import"><label>Confirmación<input name="confirmation" required autocomplete="off" placeholder="IMPORTAR DESTINOS"></label><button class="pill">Importar catálogo inicial</button><small>Solo se ejecuta si destinos, categorías, negocios e imágenes están vacíos. Nunca reemplaza registros existentes.</small></form><?php endif?>
<p class="notice"><strong>Se conservan las solicitudes y el catálogo existentes. La integración retira únicamente la tabla heredada de establecimientos si está vacía; si contiene datos, se detiene. Respalda la base antes de actualizar.</strong> La importación del catálogo semilla solo admite una base de catálogo vacía.</p></section><?php else:?><section class="admin-system"><h2>Sin conexión</h2><p>La página no muestra contraseñas ni detalles privados. Revisa el archivo de configuración privado y vuelve a cargar.</p></section><?php endif?>
<p><a class="text-link" href="index.php">Volver a solicitudes</a></p><?php adminPageEnd(); ?>
