<?php
declare(strict_types=1);
$adminPath = basename((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php'));
$adminTab = (string)($_GET['tab'] ?? '');
$activeAdminItem = match (true) {
    $adminPath === 'index.php' && $adminTab === 'sistema' => 'system',
    $adminPath === 'index.php' || ($adminPath === 'solicitud-detalle.php' && ($_GET['tipo']??'')==='participation') => 'requests',
    $adminPath === 'negocios.php' => 'businesses',
    $adminPath === 'trasladar-negocios.php' => 'businesses',
    $adminPath === 'importar-negocios.php' => 'businesses',
    $adminPath === 'contactos-negocios.php' => 'business-research',
    $adminPath === 'actualizar-negocios.php' => 'business-bulk-edit',
    $adminPath === 'campanas.php' && $adminTab === 'conexion' => 'brevo-connection',
    $adminPath === 'campanas.php' => 'campaigns',
    $adminPath === 'reclamaciones.php' => 'account-applications',
    $adminPath === 'solicitudes-cuenta.php' || ($adminPath === 'solicitud-detalle.php' && ($_GET['tipo']??'')==='account') => 'account-applications',
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
    $adminPath === 'incidencias.php' => 'incidents',
    default => '',
};
?>
<link rel="stylesheet" href="../assets/admin-navigation.css?v=20261018-2">
<?php require __DIR__.'/../_account-header.php'; ?>
<aside class="admin-sidebar admin-sidebar-with-system" aria-label="Navegación de administración">
  <a class="admin-sidebar-brand" href="index.php"><img src="/assets/brand/explorando-lugares-logo-v2-transparent.png" width="76" height="76" alt=""><span>Explorando <strong>Lugares</strong><small>Administración</small></span></a>
  <button type="button" class="admin-mobile-nav-toggle pill light compact" aria-expanded="true" aria-controls="admin-operation-nav admin-system-nav" hidden>Secciones del panel</button>
  <nav id="admin-operation-nav">
    <section><h2>Operación</h2>
      <a href="solicitudes-publicas.php" <?= $activeAdminItem === 'public-requests' ? 'aria-current="page"' : '' ?>>Solicitudes de usuarios</a>
      <a href="index.php?tab=solicitudes" <?= $activeAdminItem === 'requests' ? 'aria-current="page"' : '' ?>>Solicitudes de participación</a>
      <a href="negocios.php" <?= $activeAdminItem === 'businesses' ? 'aria-current="page"' : '' ?>>Negocios</a>
      <a href="contactos-negocios.php" <?= $activeAdminItem === 'business-research' ? 'aria-current="page"' : '' ?>>Gestión comercial</a>
      <a href="actualizar-negocios.php" <?= $activeAdminItem === 'business-bulk-edit' ? 'aria-current="page"' : '' ?>>Actualización masiva de datos</a>
      <a href="campanas.php" <?= $activeAdminItem === 'campaigns' ? 'aria-current="page"' : '' ?>>Campañas por correo</a>
      <a href="solicitudes-cuenta.php" <?= $activeAdminItem === 'account-applications' ? 'aria-current="page"' : '' ?>>Revisión de negocios</a>
      <a href="usuarios.php" <?= $activeAdminItem === 'accounts' ? 'aria-current="page"' : '' ?>>Cuentas</a>
      <a href="suscripciones.php" <?= $activeAdminItem === 'subscriptions' ? 'aria-current="page"' : '' ?>>Suscripciones</a>
    </section>
    <section><h2>Contenido</h2>
      <a href="destinos.php" <?= $activeAdminItem === 'destinations' ? 'aria-current="page"' : '' ?>>Lugares</a>
      <a href="reportes.php" <?= $activeAdminItem === 'reports' ? 'aria-current="page"' : '' ?>>Reportes</a>
    </section>

  </nav>
  <nav id="admin-system-nav" class="admin-system-shortcuts" aria-label="Diagnóstico del sistema">
        <section><h2>Dirección</h2>
      <a href="inversionistas.php" <?= $activeAdminItem === 'investor-intelligence' ? 'aria-current="page"' : '' ?>>Inteligencia e inversión</a>
      <a href="costos.php" <?= $activeAdminItem === 'costs' ? 'aria-current="page"' : '' ?>>Costos y cancelaciones</a>
      <a href="costos.php#cancelaciones">Solicitudes de cancelación</a>
    </section>
    <section><h2>Sistema</h2>
      <a href="suscripciones.php#comprobantes">Validación de pagos</a>
      <a href="medios-pago.php" <?= $activeAdminItem === 'payment-settings' ? 'aria-current="page"' : '' ?>>Medios de pago</a>
      <a href="configuracion.php" <?= $activeAdminItem === 'configuration' ? 'aria-current="page"' : '' ?>>Configuración del portal</a>
      <a href="campanas.php?tab=conexion" <?= $activeAdminItem === 'brevo-connection' ? 'aria-current="page"' : '' ?>>Conexión con Brevo</a>
      <a href="index.php?tab=sistema" <?= $activeAdminItem === 'system' ? 'aria-current="page"' : '' ?>>Estado del sistema</a>
      <a href="incidencias.php" <?= $activeAdminItem === 'incidents' ? 'aria-current="page"' : '' ?>>Errores de ejecución</a>
      <a href="base-datos.php" <?= $activeAdminItem === 'database' ? 'aria-current="page"' : '' ?>>Base de datos</a>
    </section>
  </nav>
</aside>

<script src="/assets/admin-navigation.js?v=<?=esc(substr(hash_file('sha256',__DIR__.'/../assets/admin-navigation.js'),0,12))?>" defer></script>
