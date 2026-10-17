<?php
// Injected only into the local package; never copied into a production release.
$localAdminOpen = getenv('EL_PREPROD_LOCAL') === '1'
    && getenv('EL_PREPROD_ADMIN_OPEN') === '1'
    && !empty($config['local_test'])
    && in_array(parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['127.0.0.1','localhost','[::1]'], true)
    && str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/admin/');
if ($localAdminOpen) {
    $_SESSION['admin'] = true;
    $_SESSION['last_seen'] = time();
    $_SESSION['preprod_admin_open'] = true;
} elseif (!empty($_SESSION['preprod_admin_open'])) {
    unset($_SESSION['admin'], $_SESSION['preprod_admin_open']);
}
