<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/_portal-shell.php';
function admin_layout_account():void
{
    $id=(int)($_SESSION['admin_account_id']??0);$name='Administrador';
    if($id){$query=db()->prepare('SELECT display_name FROM accounts WHERE id=?');$query->execute([$id]);$name=(string)($query->fetchColumn()?:$name);}
    ?><div class="admin-session" aria-label="Sesión administrativa"><span class="admin-session-name"><?=esc($name)?></span><a href="/">← Volver al portal</a><?php if($id):?><a href="/negocios/portal/">Mi cuenta</a><?php endif;?><form method="post" action="<?=$id?'/negocios/salir/':'/admin/index.php'?>"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf']??'')?>"><?php if(!$id):?><input type="hidden" name="action" value="logout"><?php endif;?><button type="submit">Cerrar sesión</button></form></div><?php
}
