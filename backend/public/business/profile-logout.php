<?php
// Shared profile action. Only rendered from an authenticated account page.
if(!isset($account)||empty($account['id']))return;
?><form class="account-profile-logout" method="post" action="/negocios/salir/"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><button type="submit">Cerrar sesión</button></form>
