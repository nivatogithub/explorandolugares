<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/_portal-shell.php';
function admin_layout_account(array $account):void
{
    $id=$account['id'];$name=$account['display_name'];
    ?><div class="admin-session" aria-label="Sesión administrativa"><span class="admin-session-name"><?=esc($name)?></span><a href="/">← Volver al portal</a><?php if($id):?><a href="/negocios/portal/">Mi cuenta</a><?php endif;?><form method="post" action="<?=esc($account['logout_url'])?>"><input type="hidden" name="csrf" value="<?=esc($account['csrf'])?>"><?php if(!$id):?><input type="hidden" name="action" value="logout"><?php endif;?><button type="submit">Cerrar sesión</button></form></div><?php
}
