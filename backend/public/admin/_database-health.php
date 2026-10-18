<?php
$dbStatus=$databaseHealth['db']??[];$dbVariables=$databaseHealth['variables']??[];
$dbNumber=static fn($v)=>is_numeric($v)?number_format((float)$v,0,',','.'): 'No disponible';
$dbCounters=['Connections'=>'Intentos de conexión','Connection_errors_max_connections'=>'Conexiones rechazadas por límite global','Aborted_connects'=>'Conexiones fallidas','Aborted_clients'=>'Conexiones interrumpidas','Slow_queries'=>'Consultas lentas','Innodb_row_lock_waits'=>'Esperas por bloqueo de filas','Innodb_row_lock_time'=>'Tiempo de bloqueo de filas (ms)','Questions'=>'Consultas recibidas'];
?>
<section id="rendimiento" class="admin-system"><h2>Conexiones y rendimiento</h2><p>Lecturas globales del servidor MariaDB. En un hosting compartido pueden incluir otros sitios: no son visitantes, cuentas ni solicitudes exclusivas de Explorando Lugares.</p>
<?php if(!$dbStatus): ?><p class="notice">El servidor no permite consultar estos indicadores. No se muestran ceros como si fueran mediciones.</p>
<?php else: ?>
<h3>Estado en este momento</h3><dl><dt>Conexiones abiertas ahora</dt><dd><?=$dbNumber($dbStatus['Threads_connected']??null)?></dd><dt>Consultas ejecutándose ahora</dt><dd><?=$dbNumber($dbStatus['Threads_running']??null)?></dd><dt>Límite global de conexiones</dt><dd><?=$dbNumber($dbVariables['max_connections']??null)?></dd></dl>
<?php if(isset($dbStatus['Threads_connected'],$dbVariables['max_connections'])&&(int)$dbVariables['max_connections']>0&&(float)$dbStatus['Threads_connected']/(float)$dbVariables['max_connections']>=.8):?><p class="notice health-critical">Alerta: conexiones globales al 80% o más. Confirmar con GoDaddy qué parte corresponde a esta cuenta.</p><?php endif ?>
<h3>Período de observación</h3><p>Puedes empezar un período desde cero en este panel. Guardamos una lectura de referencia y mostramos lo que aumentó desde entonces; no reiniciamos MariaDB, no borramos estadísticas de visitas ni alteramos otros sitios. La referencia es compartida por los administradores de este ambiente.</p>
<?php if(($databasePeriod['status']??'none')==='active'): ?>
<p role="status">Período iniciado: <?=esc($databasePeriod['started_at'])?> UTC · <?=$dbNumber($databasePeriod['seconds'])?> segundos transcurridos.</p>
<div class="health-table"><table><thead><tr><th>Indicador del servidor</th><th>Aumento desde el inicio del período</th></tr></thead><tbody><?php foreach($dbCounters as $key=>$label):?><tr><td><?=esc($label)?></td><td><?=$dbNumber($databasePeriod['deltas'][$key]??null)?></td></tr><?php endforeach ?></tbody></table></div>
<?php elseif(($databasePeriod['status']??'none')==='reset'): ?><p class="notice" role="status">El servidor parece haber reiniciado o sus contadores cambiaron de base. Inicia otro período para obtener una comparación válida.</p>
<?php else: ?><p class="notice">Todavía no hay un período de observación. Los totales históricos quedan abajo como referencia.</p><?php endif ?>
<form method="post" class="admin-db-action"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="start_health_period"><button class="pill" type="submit">Empezar nuevo período desde cero</button></form><p><a href="base-datos.php#rendimiento">Actualizar lectura →</a></p>
<p>Las lecturas se actualizan al abrir esta página. Las conexiones del propio panel también suman. Para atribuir carga a tu cuenta, consulta las métricas de recursos del hosting.</p>
<details><summary>Ver totales acumulados y límites técnicos del servidor</summary><p>“Intentos de conexión” cuenta intentos exitosos y fallidos. El total puede crecer durante días y abarcar otras cuentas; un número alto aislado no demuestra saturación. Algunos contadores pueden reiniciarse por una operación del proveedor.</p><dl>
<?php foreach($dbCounters as $key=>$label):?><dt><?=esc($label)?></dt><dd><?=$dbNumber($dbStatus[$key]??null)?></dd><?php endforeach ?>
<dt>Máximo de conexiones simultáneas registrado</dt><dd><?=$dbNumber($dbStatus['Max_used_connections']??null)?></dd><dt>Servidor encendido desde hace (segundos)</dt><dd><?=$dbNumber($dbStatus['Uptime']??null)?></dd>
<?php foreach(['max_user_connections'=>'Límite por usuario (0: sin límite global específico)','long_query_time'=>'Umbral de consulta lenta (segundos)','slow_query_log'=>'Registro de consultas lentas'] as $key=>$label):?><dt><?=esc($label)?></dt><dd><?=esc($dbVariables[$key]??'No disponible')?></dd><?php endforeach ?></dl></details>
<?php endif ?></section>
