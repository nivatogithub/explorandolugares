<?php
declare(strict_types=1);
require_once __DIR__.'/_admin-layout.php';
$adminPath = basename((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php'));
$adminTab = (string)($_GET['tab'] ?? '');
$activeAdminItem = match (true) {
    $adminPath === 'index.php' && $adminTab === 'sistema' => 'system',
    $adminPath === 'index.php' => 'requests',
    $adminPath === 'correos.php' => 'email-workspace',
    $adminPath === 'responder-solicitud.php' => 'requests',
    $adminPath === 'solicitud-detalle.php' => ($_GET['type']??$_GET['tipo']??'')==='account'?'account-applications':'requests',
    $adminPath === 'atencion-comercios.php' || $adminPath === 'editorial-negocios.php' || $adminPath === 'editorial.php' || $adminPath === 'paquete-editorial.php' => 'businesses',
    $adminPath === 'negocios.php' => 'businesses',
    $adminPath === 'trasladar-negocios.php' => 'businesses',
    $adminPath === 'importar-negocios.php' => 'businesses',
    $adminPath === 'contactos-negocios.php' => 'campaigns',
    $adminPath === 'actualizar-negocios.php' => 'businesses',
    $adminPath === 'campanas.php' && $adminTab === 'conexion' => 'brevo-connection',
    $adminPath === 'campanas.php' => 'campaigns',
    $adminPath === 'reclamaciones.php' => 'account-applications',
    $adminPath === 'solicitudes-cuenta.php' || ($adminPath === 'solicitud-detalle.php' && ($_GET['tipo']??'')==='account') => 'account-applications',
    $adminPath === 'usuarios.php' => 'accounts',
    $adminPath === 'suscripciones.php' || $adminPath === 'cupones.php' => 'subscriptions',
    $adminPath === 'destinos.php' || str_starts_with($adminPath, 'paquete-destino') => 'destinations',
    $adminPath === 'reportes.php' => 'reports',
    $adminPath === 'solicitudes-publicas.php' => 'public-requests',
    $adminPath === 'medios-pago.php' => 'payment-settings',
    $adminPath === 'configuracion.php' => 'configuration',
    $adminPath === 'base-datos.php' => 'database',
    $adminPath === 'inversionistas.php' => 'investor-intelligence',
    $adminPath === 'costos.php' => 'costs',
    $adminPath === 'cancelaciones.php' => 'cancellations',
    $adminPath === 'incidencias.php' => 'incidents',
    default => '',
};
?>
<?php require_once __DIR__.'/_asset.php'; ?><link rel="stylesheet" href="<?=esc(admin_asset('admin-navigation.css'))?>">

<aside class="admin-sidebar admin-sidebar-with-system" aria-label="Navegación de administración">
  <a class="admin-sidebar-brand" href="index.php"><img src="<?=esc(PortalPresentation::brandLogo())?>" width="76" height="76" alt=""><span>Explorando <strong>Lugares</strong><small>Administración</small></span></a>
  <?php admin_layout_account(); ?>
  <details class="admin-nav-disclosure" data-admin-navigation open><summary>Menú de administración</summary><nav>
    <section><h2>Operación</h2>
      <a href="atencion-comercios.php" <?= $activeAdminItem === 'businesses' ? 'aria-current="page"' : '' ?>>Negocios locales</a>
      <a href="index.php?tab=solicitudes" <?= $activeAdminItem === 'requests' || $activeAdminItem === 'public-requests' || $activeAdminItem === 'account-applications' ? 'aria-current="page"' : '' ?>>Solicitudes y mensajes</a>
      <a href="usuarios.php" <?= $activeAdminItem === 'accounts' ? 'aria-current="page"' : '' ?>>Cuentas</a>
      <a href="suscripciones.php" <?= $activeAdminItem === 'subscriptions' ? 'aria-current="page"' : '' ?>>Suscripciones</a>
      <a href="correos.php" <?= $activeAdminItem === 'email-workspace' ? 'aria-current="page"' : '' ?>>Plantillas de correo</a>
      <a href="campanas.php" <?= $activeAdminItem === 'campaigns' ? 'aria-current="page"' : '' ?>>CRM y campañas</a>
    </section>
    <section><h2>Contenido</h2>
      <a href="destinos.php" <?= $activeAdminItem === 'destinations' ? 'aria-current="page"' : '' ?>>Lugares</a>
      <a href="reportes.php" <?= $activeAdminItem === 'reports' ? 'aria-current="page"' : '' ?>>Reportes</a>
    </section>

  </nav>
  <nav id="admin-system-nav" class="admin-system-shortcuts" aria-label="Diagnóstico del sistema">
        <section><h2>Dirección</h2>
      <a href="inversionistas.php" <?= $activeAdminItem === 'investor-intelligence' ? 'aria-current="page"' : '' ?>>Inteligencia e inversión</a>
      <a href="costos.php" <?= $activeAdminItem === 'costs' ? 'aria-current="page"' : '' ?>>Costos</a>
      <a href="cancelaciones.php" <?= $activeAdminItem === 'cancellations' ? 'aria-current="page"' : '' ?>>Solicitudes de cancelación</a>
    </section>
    <section><h2>Sistema</h2>
      <a href="medios-pago.php" <?= $activeAdminItem === 'payment-settings' ? 'aria-current="page"' : '' ?>>Configuración de pago</a>
      <a href="configuracion.php" <?= $activeAdminItem === 'configuration' ? 'aria-current="page"' : '' ?>>Configuración del portal</a>
      <a href="campanas.php?tab=conexion" <?= $activeAdminItem === 'brevo-connection' ? 'aria-current="page"' : '' ?>>Conexión con Brevo</a>
      <a href="index.php?tab=sistema" <?= $activeAdminItem === 'system' ? 'aria-current="page"' : '' ?>>Estado del sistema</a>
      <a href="incidencias.php" <?= $activeAdminItem === 'incidents' ? 'aria-current="page"' : '' ?>>Errores de ejecución</a>
      <a href="base-datos.php" <?= $activeAdminItem === 'database' ? 'aria-current="page"' : '' ?>>Base de datos</a>
    </section>
  </nav></details>
</aside>
<script src="<?=esc(admin_asset('admin-navigation.js'))?>" defer></script>
