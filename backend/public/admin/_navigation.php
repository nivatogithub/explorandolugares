<?php
declare(strict_types=1);
$adminPath = basename((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php'));
$adminTab = (string)($_GET['tab'] ?? '');
$activeAdminItem = match (true) {
    $adminPath === 'index.php' && $adminTab === 'sistema' => 'system',
    $adminPath === 'index.php' => 'requests',
    $adminPath === 'negocios.php' => 'businesses',
    $adminPath === 'importar-negocios.php' => 'businesses',
    $adminPath === 'contactos-negocios.php' => 'business-research',
    $adminPath === 'actualizar-negocios.php' => 'business-bulk-edit',
    $adminPath === 'reclamaciones.php' => 'claims',
    $adminPath === 'solicitudes-cuenta.php' => 'account-applications',
    $adminPath === 'usuarios.php' => 'accounts',
    $adminPath === 'suscripciones.php' => 'subscriptions',
    $adminPath === 'destinos.php' || str_starts_with($adminPath, 'paquete-destino') => 'destinations',
    $adminPath === 'base-datos.php' => 'database',
    $adminPath === 'inversionistas.php' => 'investor-intelligence',
    default => '',
};
?>
<aside class="admin-sidebar" aria-label="Navegación de administración">
  <a class="admin-sidebar-brand" href="index.php">Explorando <strong>Lugares</strong><small>Administración</small></a>
  <nav>
    <section><h2>Operación</h2>
      <a href="index.php?tab=solicitudes" <?= $activeAdminItem === 'requests' ? 'aria-current="page"' : '' ?>>Solicitudes de participación</a>
      <a href="negocios.php" <?= $activeAdminItem === 'businesses' ? 'aria-current="page"' : '' ?>>Negocios</a>
      <a href="contactos-negocios.php" <?= $activeAdminItem === 'business-research' ? 'aria-current="page"' : '' ?>>Canales y contactos</a>
      <a href="actualizar-negocios.php" <?= $activeAdminItem === 'business-bulk-edit' ? 'aria-current="page"' : '' ?>>Exportar / actualizar</a>
      <a href="reclamaciones.php" <?= $activeAdminItem === 'claims' ? 'aria-current="page"' : '' ?>>Reclamos de negocio</a>
      <a href="solicitudes-cuenta.php" <?= $activeAdminItem === 'account-applications' ? 'aria-current="page"' : '' ?>>Solicitudes de cuenta</a>
      <a href="usuarios.php" <?= $activeAdminItem === 'accounts' ? 'aria-current="page"' : '' ?>>Cuentas</a>
      <a href="suscripciones.php" <?= $activeAdminItem === 'subscriptions' ? 'aria-current="page"' : '' ?>>Suscripciones</a>
    </section>
    <section><h2>Contenido</h2>
      <a href="destinos.php" <?= $activeAdminItem === 'destinations' ? 'aria-current="page"' : '' ?>>Destinos</a>
      <span class="admin-nav-disabled" aria-disabled="true">Reportes <small>Próximamente</small></span>
    </section>
    <section><h2>Dirección</h2>
      <a href="inversionistas.php" <?= $activeAdminItem === 'investor-intelligence' ? 'aria-current="page"' : '' ?>>Inteligencia e inversión</a>
    </section>
    <section><h2>Sistema</h2>
      <a href="index.php?tab=sistema" <?= $activeAdminItem === 'system' ? 'aria-current="page"' : '' ?>>Estado del sistema</a>
      <a href="base-datos.php" <?= $activeAdminItem === 'database' ? 'aria-current="page"' : '' ?>>Base de datos</a>
    </section>
  </nav>
</aside>
