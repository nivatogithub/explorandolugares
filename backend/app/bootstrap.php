<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/private/config.php';
// Preview may inject a private, ignored local config. Never allow this override on a web host.
if (PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    $localConfig = getenv('EL_LOCAL_CONFIG_PATH');
    if (is_string($localConfig) && $localConfig !== '') $configFile = $localConfig;
}
if (!is_file($configFile)) { http_response_code(503); exit('Configuración del servidor pendiente.'); }
$config = require $configFile;
require_once __DIR__.'/BusinessPortalAccess.php';
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$https = ($_SERVER['HTTPS'] ?? '') === 'on';
if (PHP_SAPI !== 'cli' && !$https && !(($config['local_test'] ?? false) && $local)) {
    http_response_code(403); exit('Este servicio requiere HTTPS.');
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
if (PHP_SAPI !== 'cli') {
    session_name('el_session');
    session_set_cookie_params(['httponly'=>true, 'secure'=>$https, 'samesite'=>'Lax', 'path'=>'/']);
    ini_set('session.use_strict_mode', '1');
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function esc(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function english_request(): bool { return ($_GET['lang'] ?? $_POST['lang'] ?? '') === 'en'; }
function site_path(string $path): string {
    $path='/'.ltrim($path,'/');
    $requestPath=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH);
    $prefix=is_string($requestPath)&&($requestPath==='/v1'||str_starts_with($requestPath,'/v1/'))?'/v1':'';
    return $prefix.$path;
}
function response_text(string $value): string {
    if (!english_request()) return $value;
    static $messages;
    $messages ??= json_decode(file_get_contents(__DIR__.'/messages.en.json'), true);
    return $messages[$value] ?? $value;
}
function db(): PDO {
    global $config;
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO($config['dsn'], $config['db_user'] ?? null, $config['db_password'] ?? null,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    return $pdo;
}
/** Private uploads live beside the deployed app, never inside the public document root. */
function private_storage_path(string $area): string {
    if (!preg_match('/^[a-z0-9-]+$/D', $area)) throw new InvalidArgumentException('Área de almacenamiento inválida.');
    return dirname(__DIR__).'/private/'.$area;
}
function csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit(response_text('La sesión expiró. Recarga la página.'));
    }
}
function throttle(string $kind, int $limit, int $seconds, string $extra = ''): bool {
    global $config;
    $window = intdiv(time(), $seconds);
    $key = hash_hmac('sha256', $kind . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . '|' . $extra . '|' . $window, $config['app_key']);
    $pdo=db();
    $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
      ? 'INSERT INTO rate_limits (bucket_key,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1'
      : 'INSERT INTO rate_limits (bucket_key,hits,expires_at) VALUES (?,1,?) ON CONFLICT(bucket_key) DO UPDATE SET hits=hits+1';
    $pdo->prepare($sql)->execute([$key,($window+1)*$seconds]);
    $q=$pdo->prepare('SELECT hits FROM rate_limits WHERE bucket_key=?');$q->execute([$key]);
    return (int)$q->fetchColumn() <= $limit;
}
function ready(): bool {
    global $config;
    return ($config['registration_enabled'] ?? false) && strlen($config['app_key'] ?? '') >= 32 && !empty($config['privacy']['operator']) && !empty($config['privacy']['contact']) && !empty($config['privacy']['retention']) && (!english_request() || !empty($config['privacy']['retention_en']));
}
function business_portal_available(): bool {
    global $config;
    return BusinessPortalAccess::available($config, $_SESSION);
}
/** Keep account and claim routes private until enabled or unlocked by a configured test key. */
function require_business_portal_access(): void {
    global $config;
    if (business_portal_available()) return;
    $providedKey = is_string($_GET['prueba'] ?? null) ? $_GET['prueba'] : '';
    if (BusinessPortalAccess::unlockForTesting($config, $providedKey, $_SESSION)) {
        session_regenerate_id(true);
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/' || str_contains($path, "\r") || str_contains($path, "\n")) $path = '/';
        $query = [];
        parse_str((string)(parse_url($requestUri, PHP_URL_QUERY) ?? ''), $query);
        unset($query['prueba']);
        $target = $path.($query ? '?'.http_build_query($query) : '');
        header('Location: '.$target, true, 303);
        exit;
    }
    http_response_code(404);
    exit('No encontrado.');
}
function json_response(array $body, int $status=200): never {
    if (isset($body['message'])) $body['message']=response_text($body['message']);
    if (isset($body['errors'])) $body['errors']=array_map('response_text',$body['errors']);
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE); exit;
}
function contact_options(): array {
    static $options=null;
    return $options ??= json_decode(file_get_contents(__DIR__.'/contact-options.json'), true, 512, JSON_THROW_ON_ERROR);
}
function categories(bool $includeLegacy=false): array {
    $result=[];
    foreach (contact_options()['groups'] as $group) foreach ($group['categories'] as $category) $result[$category['value']]=$category['label'];
    if ($includeLegacy) foreach (contact_options()['legacyCategoryAliases']??[] as $old=>$current) $result[$old]=$result[$current];
    return $result;
}
function lead_validation(array $input): array {
    $errors=[];$data=[];
    foreach (['name'=>120,'business'=>160,'municipality'=>120,'email'=>254,'phone'=>40,'brand_name'=>160,'description'=>2000] as $key=>$max) {
        $value=is_string($input[$key]??null)?trim($input[$key]):'';
        if (strlen($value)>$max) $errors[$key]='El texto supera el límite permitido.';
        $data[$key]=$value;
    }
    foreach (['name','business','municipality'] as $key) if ($data[$key]==='') $errors[$key]='Completa este campo.';
    if (!in_array($data['municipality'], contact_options()['municipalities'], true)) $errors['municipality']='Selecciona uno de los municipios disponibles.';
    if ($data['email']!=='' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email']='Revisa el correo electrónico.';
    $data['phone_type']='';
    // El prefijo se calcula aquí: nunca confiar en un prefijo enviado por el navegador.
    if (array_key_exists('phone_number',$input)) {
        $number=is_string($input['phone_number'])?trim($input['phone_number']):'';
        $type=is_string($input['phone_type']??null)?$input['phone_type']:'';
        $data['phone']='';
        if (!in_array($type,['fijo','celular','whatsapp'],true)) $errors['phone']='Revisa el número de contacto.';
        elseif (!is_string($input['phone_number']) || strlen($number)>20) $errors['phone']='Revisa el número de contacto.';
        elseif ($number!=='') {
            if (in_array($type,['celular','whatsapp'],true) && preg_match('/^3[0-9]{9}$/D',$number)) $data['phone']='+57'.$number;
            elseif ($type==='fijo' && preg_match('/^[0-9]{7}$/D',$number)) $data['phone']='+57604'.$number;
            else $errors['phone']=$type==='fijo'?'Escribe los 7 dígitos del teléfono fijo, sin indicativo.':'Escribe los 10 dígitos del celular, empezando por 3.';
        }
    } elseif ($data['phone']!=='' && !preg_match('/^\+57(?:3[0-9]{9}|604[0-9]{7})$/D',$data['phone'])) {
        $errors['phone']='Revisa el número de contacto.';
    }
    if ($data['phone']!=='' && isset($type) && in_array($type,['fijo','celular','whatsapp'],true)) $data['phone_type']=$type;
    if ($data['email']==='' && $data['phone']==='') $errors['email']='Indica al menos un correo o teléfono.';
    $data['category']=is_string($input['category']??null)?$input['category']:'';
    if (!isset(categories()[$data['category']])) $errors['category']='Selecciona un tipo de negocio.';
    // Tipos limitados en servidor: una solicitud especial no se confunde con un negocio normal.
    $requestType=is_string($input['request_type']??null)?$input['request_type']:'negocio';
    if (!in_array($requestType,['negocio','impulsor_local'],true)) $errors['request_type']='Revisa el tipo de solicitud.';
    $data['has_brand']='no';
    $data['brand_name']='';
    $data['interest']=$requestType==='impulsor_local'?'impulsor_local':'contacto';
    $data['wants_contact']=1;
    if (($input['consent']??'')!=='1') $errors['consent']='Necesitamos tu autorización para guardar la solicitud.';
    if (($input['understanding']??'')!=='1') $errors['understanding']='Confirma que has leído y entendido cómo participar y el alcance de esta etapa.';
    $data['participation_version']=$requestType==='impulsor_local'?'impulsor-local-propuesta-v1':'participacion-informativa-v1';
    return [$data,$errors];
}
