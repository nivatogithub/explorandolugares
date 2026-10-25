<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/private/config.php';
require_once __DIR__.'/PortalIncidents.php';
PortalIncidents::install(dirname(__DIR__).'/private/incidents');
// Preview may inject a private, ignored local config. Never allow this override on a web host.
if (PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    $localConfig = getenv('EL_LOCAL_CONFIG_PATH');
    if (is_string($localConfig) && $localConfig !== '') $configFile = $localConfig;
}
if (!is_file($configFile)) { http_response_code(503); exit('Configuración del servidor pendiente.'); }
$config = require $configFile;
require_once __DIR__.'/ProductionRuntimeSettings.php';
try{$config=array_replace_recursive($config,ProductionRuntimeSettings::load(dirname($configFile)));}catch(Throwable $error){PortalIncidents::capture($error,'runtime_configuration');error_log('Explorando Lugares: configuración de ejecución no válida.');http_response_code(503);exit('Revisa la configuración privada del portal.');}
require_once __DIR__.'/PortalSettings.php';
try{$portalOverrides=PortalSettings::load(dirname($configFile));if(isset($portalOverrides['privacy']))$config['privacy']=array_replace($config['privacy']??[],$portalOverrides['privacy']);}catch(Throwable $error){PortalIncidents::capture($error,'runtime_configuration');error_log('Explorando Lugares: configuración privada de privacidad no válida.');http_response_code(503);exit('Revisa la configuración privada del portal.');}
require_once __DIR__.'/BusinessPortalAccess.php';
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || (getenv('EL_PREPROD_LOCAL') === '1' && in_array(parse_url('http://'.($_SERVER['HTTP_HOST']??''), PHP_URL_HOST), ['127.0.0.1','localhost','[::1]'], true));
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
if (PHP_SAPI !== 'cli' && !empty($_SESSION['business_account_id'])) {
    require_once __DIR__.'/AccountSessionSecurity.php';
    try { AccountSessionSecurity::synchronize(db(),$_SESSION); }
    catch (Throwable $error) { PortalIncidents::capture($error,'session_sync');AccountSessionSecurity::clear($_SESSION); }
}
if (PHP_SAPI !== 'cli' && !empty($config['unified_admin_only']) && empty($_SESSION['admin_account_id'])) { unset($_SESSION['admin'],$_SESSION['last_seen']); }
if (PHP_SAPI !== 'cli' && !empty($_SESSION['admin_account_id'])) {
    require_once __DIR__.'/AccountAuthorization.php';
    try { AccountAuthorization::synchronize(db(),$_SESSION); } catch (Throwable $error) { PortalIncidents::capture($error,'admin_session_sync');unset($_SESSION['admin'],$_SESSION['admin_account_id'],$_SESSION['admin_user']); }
}
function esc(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function english_request(): bool { return ($_GET['lang'] ?? $_POST['lang'] ?? '') === 'en'; }
function site_path(string $path): string {
    $path='/'.ltrim($path,'/');
    return $path;
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
        require_once __DIR__.'/DatabaseClock.php';
        DatabaseClock::configure($pdo);
    }
    return $pdo;
}
/** Private uploads live beside the deployed app, never inside the public document root. */
function private_storage_path(string $area): string {
    if (!preg_match('/^[a-z0-9-]+$/D', $area)) throw new InvalidArgumentException('Área de almacenamiento inválida.');
    return dirname(__DIR__).'/private/'.$area;
}
/** Redirect normal form submissions to sign-in when their session is gone.
 * APIs still receive JSON so clients can handle the expired session safely. */
function session_expired(?string $returnTo=null): never {
    $accept=(string)($_SERVER['HTTP_ACCEPT']??'');
    $uri=$returnTo??(string)($_SERVER['REQUEST_URI']??'');
    $parts=parse_url($uri);$path=(string)($parts['path']??'');$query=isset($parts['query'])?'?'.$parts['query']:'';
    if($path!==''&&str_starts_with($path,'/')&&!str_starts_with($path,'//')&&!str_starts_with($path,'/negocios/acceso'))$_SESSION['session_return']=$path.$query;
    if(str_contains($accept,'application/json'))json_response(['error'=>'authentication_required','login_url'=>site_path('/negocios/acceso/')],401);
    header('Location: '.site_path('/negocios/acceso/?sesion=expirada'),true,303);
    exit;
}
/**
 * One boundary for every private account action. Browser pages return to the
 * sign-in screen; fetch/XHR callers receive a predictable response instead
 * of an HTML page or a bare 403.
 */
function require_business_session(bool $expectsJson=false,?string $returnTo=null): void {
    $active=!empty($_SESSION['business_account_id']) && time()-(int)($_SESSION['business_last_seen']??0)<=1800;
    if($active){$_SESSION['business_last_seen']=time();return;}
    require_once __DIR__.'/AccountSessionSecurity.php';
    AccountSessionSecurity::clear($_SESSION);
    $uri=$returnTo??(string)($_SERVER['REQUEST_URI']??'');$parts=parse_url($uri);$path=(string)($parts['path']??'');$query=isset($parts['query'])?'?'.$parts['query']:'';
    if($path!==''&&str_starts_with($path,'/')&&!str_starts_with($path,'//')&&!str_starts_with($path,'/negocios/acceso'))$_SESSION['session_return']=$path.$query;
    $accept=(string)($_SERVER['HTTP_ACCEPT']??'');
    if($expectsJson||str_contains($accept,'application/json'))json_response(['error'=>'authentication_required','login_url'=>site_path('/negocios/acceso/')],401);
    header('Location: '.site_path('/negocios/acceso/?sesion=expirada'),true,303);
    exit;
}
/** Every private administration page uses this guard; it never renders a 403 login message. */
function require_admin_session(): void {
    $active=!empty($_SESSION['admin']) && time()-(int)($_SESSION['last_seen']??0)<=1800;
    if($active){$_SESSION['last_seen']=time();return;}
    unset($_SESSION['admin'],$_SESSION['admin_account_id'],$_SESSION['admin_user'],$_SESSION['last_seen']);
    $uri=(string)($_SERVER['REQUEST_URI']??'');$parts=parse_url($uri);$path=(string)($parts['path']??'');$query=isset($parts['query'])?'?'.$parts['query']:'';
    if($path!==''&&str_starts_with($path,'/')&&!str_starts_with($path,'//'))$_SESSION['session_return']=$path.$query;
    header('Location: '.site_path('/negocios/acceso/'),true,303);
    exit;
}
/** The administrative namespace is protected once, before any page is allowed to render. */
function enforce_administration_session(): void {
    if(PHP_SAPI==='cli')return;
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    if(!str_starts_with($path,'/admin/'))return;
    // index.php owns the sign-in entry point; every other admin endpoint is private.
    if($path==='/admin/'||$path==='/admin/index.php')return;
    require_admin_session();
}
enforce_administration_session();
function csrf(?string $returnTo=null): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        // Anonymous public APIs do not require an account: an expired form token
        // must stay a JSON validation failure, not send a visitor to sign-in.
        $apiPath=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:'');
        if(empty($_SESSION['admin'])&&empty($_SESSION['business_account_id'])&&str_starts_with($apiPath,'/api/')){
            json_response(['error'=>'csrf_invalid','message'=>'Recarga el formulario antes de enviarlo.'],403);
        }
        session_expired($returnTo);
    }
}
function throttle(string $kind, int $limit, int $seconds, string $extra = ''): bool {
    global $config;
    require_once __DIR__.'/LocalRegressionMode.php';
    if (LocalRegressionMode::enabled($config, $_SERVER)) return true;
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
    return ($config['registration_enabled'] ?? false) && strlen($config['app_key'] ?? '') >= 32 && !empty($config['privacy']['operator']) && !empty($config['privacy']['contact']) && !empty($config['privacy']['retention']) && PortalSettings::retention($config['privacy'],(string)($_GET['lang']??$_POST['lang']??'es'))!=='';
}
function business_portal_available(): bool {
    global $config;
    return BusinessPortalAccess::available($config, $_SESSION);
}
/** Account routes are public only when explicitly enabled in private configuration. */
function require_business_portal_access(): void {
    if (business_portal_available()) return;
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
    require_once __DIR__.'/LeadGeography.php';
    $location=LeadGeography::resolve($data['municipality'],is_string($input['contact_department']??null)?$input['contact_department']:'');
    if(!$location)$errors['municipality']='Selecciona un municipio del departamento elegido.';else $data['municipality']=$location['label'];
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
            elseif ($type==='fijo' && preg_match('/^[0-9]{7}$/D',$number)) $data['phone']='+57'.($location['prefix']??'').$number;
            else $errors['phone']=$type==='fijo'?'Escribe los 7 dígitos del teléfono fijo, sin indicativo.':'Escribe los 10 dígitos del celular, empezando por 3.';
        }
    } elseif ($data['phone']!=='' && !preg_match('/^\+57(?:3[0-9]{9}|60[1-8][0-9]{7})$/D',$data['phone'])) {
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
