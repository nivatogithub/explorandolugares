<?php
declare(strict_types=1);
require_once __DIR__.'/_admin-layout.php';
$activeAdminItem = $adminLayout['active_item'];
?>
<?php require_once __DIR__.'/_asset.php'; ?><link rel="stylesheet" href="<?=esc(admin_asset('admin-navigation.css'))?>">

<aside class="admin-sidebar admin-sidebar-with-system" aria-label="Navegación de administración">
  <a class="admin-sidebar-brand" href="/admin/index.php"><img src="<?=esc(PortalPresentation::brandLogo())?>" width="76" height="76" alt=""><span>Explorando <strong>Lugares</strong><small>Administración</small></span></a>
  <?php admin_layout_account($adminLayout['account']); ?>
  <details class="admin-nav-disclosure" data-admin-navigation open><summary>Menú de administración</summary><nav>
    <section><h2>Operación</h2>
      <a href="/admin/business-support.php" <?= $activeAdminItem === 'businesses' ? 'aria-current="page"' : '' ?>>Negocios locales</a>
      <a href="/admin/index.php?tab=solicitudes" <?= $activeAdminItem === 'requests' || $activeAdminItem === 'public-requests' || $activeAdminItem === 'account-applications' ? 'aria-current="page"' : '' ?>>Solicitudes y mensajes</a>
      <a href="/admin/accounts.php" <?= $activeAdminItem === 'accounts' ? 'aria-current="page"' : '' ?>>Cuentas</a>
      <a href="/admin/subscriptions.php" <?= $activeAdminItem === 'subscriptions' ? 'aria-current="page"' : '' ?>>Suscripciones</a>
      <a href="/admin/email-templates.php" <?= $activeAdminItem === 'email-workspace' ? 'aria-current="page"' : '' ?>>Plantillas de correo</a>
      <a href="/admin/campaigns.php" <?= $activeAdminItem === 'campaigns' ? 'aria-current="page"' : '' ?>>CRM y campañas</a>
    </section>
    <section><h2>Contenido</h2>
      <a href="/admin/places.php" <?= $activeAdminItem === 'places' ? 'aria-current="page"' : '' ?>>Lugares</a>
      <a href="/admin/reports.php" <?= $activeAdminItem === 'reports' ? 'aria-current="page"' : '' ?>>Reportes</a>
    </section>

  </nav>
  <nav id="admin-system-nav" class="admin-system-shortcuts" aria-label="Diagnóstico del sistema">
        <section><h2>Dirección</h2>
      <a href="/admin/investors.php" <?= $activeAdminItem === 'investor-intelligence' ? 'aria-current="page"' : '' ?>>Inteligencia e inversión</a>
      <a href="/admin/costs.php" <?= $activeAdminItem === 'costs' ? 'aria-current="page"' : '' ?>>Costos</a>
      <a href="/admin/cancellations.php" <?= $activeAdminItem === 'cancellations' ? 'aria-current="page"' : '' ?>>Solicitudes de cancelación</a>
    </section>
    <section><h2>Sistema</h2>
      <a href="/admin/payment-settings.php" <?= $activeAdminItem === 'payment-settings' ? 'aria-current="page"' : '' ?>>Configuración de pago</a>
      <a href="/admin/settings.php" <?= $activeAdminItem === 'configuration' ? 'aria-current="page"' : '' ?>>Configuración del portal</a>
      <a href="/admin/campaigns.php?tab=conexion" <?= $activeAdminItem === 'brevo-connection' ? 'aria-current="page"' : '' ?>>Conexión con Brevo</a>
      <a href="/admin/index.php?tab=sistema" <?= $activeAdminItem === 'system' ? 'aria-current="page"' : '' ?>>Estado del sistema</a>
      <a href="/admin/incidents.php" <?= $activeAdminItem === 'incidents' ? 'aria-current="page"' : '' ?>>Errores de ejecución</a>
      <a href="/admin/database.php" <?= $activeAdminItem === 'database' ? 'aria-current="page"' : '' ?>>Base de datos</a>
    </section>
  </nav></details>
</aside>
<script src="<?=esc(admin_asset('admin-navigation.js'))?>" defer></script>
