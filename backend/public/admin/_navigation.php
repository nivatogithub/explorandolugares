<?php
declare(strict_types=1);
$adminPath = basename((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php'));
$adminTab = (string)($_GET['tab'] ?? '');
$activeAdminItem = match (true) {
    $adminPath === 'index.php' && $adminTab === 'sistema' => 'system',
    $adminPath === 'index.php' => 'requests',
    $adminPath === 'negocios.php' => 'businesses',
    $adminPath === 'trasladar-negocios.php' => 'businesses',
    $adminPath === 'importar-negocios.php' => 'businesses',
    $adminPath === 'contactos-negocios.php' => 'business-research',
    $adminPath === 'actualizar-negocios.php' => 'business-bulk-edit',
    $adminPath === 'campanas.php' => 'campaigns',
    $adminPath === 'reclamaciones.php' => 'claims',
    $adminPath === 'solicitudes-cuenta.php' => 'account-applications',
    $adminPath === 'usuarios.php' => 'accounts',
    $adminPath === 'suscripciones.php' => 'subscriptions',
    $adminPath === 'destinos.php' || str_starts_with($adminPath, 'paquete-destino') => 'destinations',
    $adminPath === 'reportes.php' => 'reports',
    $adminPath === 'solicitudes-publicas.php' => 'public-requests',
    $adminPath === 'medios-pago.php' => 'payment-settings',
    $adminPath === 'configuracion.php' => 'configuration',
    $adminPath === 'base-datos.php' => 'database',
    $adminPath === 'inversionistas.php' => 'investor-intelligence',
    $adminPath === 'costos.php' => 'costs',
    default => '',
};
?>
<link rel="stylesheet" href="../assets/admin-navigation.css?v=20261017-2">
<?php require __DIR__.'/../_account-header.php'; ?>
<aside class="admin-sidebar admin-sidebar-with-system" aria-label="Navegación de administración">
  <a class="admin-sidebar-brand" href="index.php"><img src="/assets/brand/explorando-lugares-logo-v2-transparent.png" width="76" height="76" alt=""><span>Explorando <strong>Lugares</strong><small>Administración</small></span></a>
  <nav>
    <section><h2>Operación</h2>
      <a href="solicitudes-publicas.php" <?= $activeAdminItem === 'public-requests' ? 'aria-current="page"' : '' ?>>Solicitudes de usuarios</a>
      <a href="index.php?tab=solicitudes" <?= $activeAdminItem === 'requests' ? 'aria-current="page"' : '' ?>>Solicitudes de participación</a>
      <a href="negocios.php" <?= $activeAdminItem === 'businesses' ? 'aria-current="page"' : '' ?>>Negocios</a>
      <a href="contactos-negocios.php" <?= $activeAdminItem === 'business-research' ? 'aria-current="page"' : '' ?>>Canales y responsable</a>
      <a href="actualizar-negocios.php" <?= $activeAdminItem === 'business-bulk-edit' ? 'aria-current="page"' : '' ?>>Exportar / actualizar</a>
      <a href="campanas.php" <?= $activeAdminItem === 'campaigns' ? 'aria-current="page"' : '' ?>>Campañas por correo</a>
      <a href="reclamaciones.php" <?= $activeAdminItem === 'claims' ? 'aria-current="page"' : '' ?>>Reclamos de negocio</a>
      <a href="solicitudes-cuenta.php" <?= $activeAdminItem === 'account-applications' ? 'aria-current="page"' : '' ?>>Solicitudes de cuenta</a>
      <a href="usuarios.php" <?= $activeAdminItem === 'accounts' ? 'aria-current="page"' : '' ?>>Cuentas</a>
      <a href="suscripciones.php" <?= $activeAdminItem === 'subscriptions' ? 'aria-current="page"' : '' ?>>Suscripciones</a>
    </section>
    <section><h2>Contenido</h2>
      <a href="destinos.php" <?= $activeAdminItem === 'destinations' ? 'aria-current="page"' : '' ?>>Destinos</a>
      <a href="reportes.php" <?= $activeAdminItem === 'reports' ? 'aria-current="page"' : '' ?>>Reportes de operación</a>
    </section>

  </nav>
  <nav class="admin-system-shortcuts" aria-label="Diagnóstico del sistema">
        <section><h2>Dirección</h2>
      <a href="inversionistas.php" <?= $activeAdminItem === 'investor-intelligence' ? 'aria-current="page"' : '' ?>>Inteligencia e inversión</a>
      <a href="costos.php" <?= $activeAdminItem === 'costs' ? 'aria-current="page"' : '' ?>>Costos y cancelaciones</a>
    </section>
    <section><h2>Sistema</h2>
      <a href="medios-pago.php" <?= $activeAdminItem === 'payment-settings' ? 'aria-current="page"' : '' ?>>Medios de pago</a>
      <a href="configuracion.php" <?= $activeAdminItem === 'configuration' ? 'aria-current="page"' : '' ?>>Configuración del portal</a>
      <a href="campanas.php?tab=conexion">Conexión con Brevo</a>
      <a href="index.php?tab=sistema" <?= $activeAdminItem === 'system' ? 'aria-current="page"' : '' ?>>Estado del sistema</a>
      <a href="base-datos.php" <?= $activeAdminItem === 'database' ? 'aria-current="page"' : '' ?>>Base de datos</a>
    </section>
  </nav>
</aside>
