<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessEditorialService.php';
require_once __DIR__.'/BrevoTransactionalClient.php';
/** Single-attempt notification of an exact private version. No marketing or bearer access. */
final class BusinessEditorialMail
{
 public function __construct(private PDO $pdo,private array $settings,private ?Closure $transport=null,private bool $simulation=false){}
 public function send(int $version,string $actor):string{
  if($actor==='')throw new RuntimeException('Notificación reservada al administrador.');$this->pdo->beginTransaction();try{
   $q=$this->pdo->prepare('SELECT business_id FROM business_editorial_versions WHERE id=?');$q->execute([$version]);$business=(int)$q->fetchColumn();$q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$business]);
   $q=$this->pdo->prepare('SELECT v.*,a.email,a.status AS account_status,a.email_verified_at FROM business_editorial_versions v JOIN accounts a ON a.id=v.account_id WHERE v.id=? AND v.id=(SELECT MAX(v2.id) FROM business_editorial_versions v2 WHERE v2.business_id=v.business_id)');$q->execute([$version]);$v=$q->fetch(PDO::FETCH_ASSOC);if(!$v||$v['status']!=='awaiting_owner'||$v['account_status']!=='active'||empty($v['email_verified_at']))throw new RuntimeException('La última versión debe estar preparada y su cuenta activa.');
   (new BusinessEditorialService($this->pdo,sys_get_temp_dir()))->authorize((int)$v['account_id'],$business);
   $q=$this->pdo->prepare('SELECT 1 FROM business_editorial_events WHERE version_id=? AND action="mail_claimed"');$q->execute([$version]);if($q->fetchColumn())throw new RuntimeException('Ya se intentó notificar esta versión. Revisa el resultado antes de reenviar.');
   $origin=rtrim((string)($this->settings['public_origin']??''),'/');if(!preg_match('~^https://[a-z0-9.-]+$~iD',$origin)&&!$this->simulation)throw new RuntimeException('Configura el origen HTTPS del portal en Brevo.');
   if(!$this->simulation&&!(new BrevoTransactionalClient($this->settings,$this->transport))->ready())throw new RuntimeException('Configura primero la conexión transaccional.');
   $this->pdo->prepare('INSERT INTO business_editorial_events(version_id,actor,action) VALUES(?,?,"mail_claimed")')->execute([$version,$actor]);$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  $url=$origin.'/business/editorial.php?business='.$business.'&version='.$version.'#revision';$escape=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $text="Tu negocio está listo para revisión. Abre la vista privada e inicia sesión para aprobar esta versión o pedir correcciones: ".$url."\nTu aprobación no publica automáticamente el negocio. El equipo realizará la validación final.";
  $html='<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:24px;background:#fffaf2;color:#172b42"><img width="100" alt="Explorando Lugares" src="'.$escape($origin).'/assets/brand/explorando-lugares-logo-v2-transparent.png"><h1>Revisa cómo se verá tu negocio</h1><p>Preparamos tu versión privada. Comprueba la información y las imágenes en tu idioma. Abre el enlace y elige «Apruebo esta versión» o «Solicitar correcciones». El portal se encarga de las traducciones.</p><p><a href="'.$escape($url).'" style="display:inline-block;padding:14px 20px;background:#d84e27;color:white;border-radius:22px">Revisar mi prepublicación</a></p><p>Debes iniciar sesión. Tu aprobación no publica automáticamente el negocio: falta la validación final del equipo.</p></div>';
  $outcome='mail_uncertain';try{if($this->simulation)$outcome='mail_simulated';else{$response=(new BrevoTransactionalClient($this->settings,$this->transport))->send(['sender'=>['email'=>$this->settings['sender_email'],'name'=>$this->settings['sender_name']],'to'=>[['email'=>$v['email']]],'subject'=>'Tu negocio está listo para revisión · Explorando Lugares','htmlContent'=>$html,'textContent'=>$text]);if(($response['status']??0)===201&&!empty($response['body']['messageId']))$outcome='mail_accepted';elseif(($response['status']??0)>=400&&($response['status']??0)<500)$outcome='mail_failed';}}catch(Throwable $e){$outcome='mail_uncertain';}
  $this->pdo->prepare('INSERT INTO business_editorial_events(version_id,actor,action) VALUES(?,?,?)')->execute([$version,$actor,$outcome]);return $outcome;
 }
}
