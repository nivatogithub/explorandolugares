<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
require dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
$service=new BusinessAccountPortalService(db());$error='';
try{
 if(!business_portal_available())throw new RuntimeException('El acceso de cuentas empresariales todavía no está habilitado.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf();$account=$service->authenticate((string)($_POST['email']??''),(string)($_POST['password']??''));require_once dirname(__DIR__,2).'/app/AccountSessionSecurity.php';AccountSessionSecurity::bind(db(),$_SESSION,(int)$account['id']);session_regenerate_id(true);$_SESSION['business_account_id']=(int)$account['id'];unset($_SESSION['account_space']);$_SESSION['business_last_seen']=time();unset($_SESSION['admin'],$_SESSION['admin_account_id'],$_SESSION['admin_user']);if(AccountAuthorization::isAdmin(db(),(int)$account['id'])){$_SESSION['admin']=true;$_SESSION['admin_account_id']=(int)$account['id'];$_SESSION['admin_user']=$account['email'];$_SESSION['last_seen']=time();}$_SESSION['csrf']=bin2hex(random_bytes(32));$return=$_SESSION['editorial_return']??null;unset($_SESSION['editorial_return']);
  // A saved paid request remains recoverable: return to its private payment
  // panel until the owner has submitted a payment receipt. Editorial returns
  // always take precedence, and disputed claims never appear in this query.
  $paymentBusiness=0;
  try{$pendingPayment=db()->prepare('SELECT m.business_id FROM business_account_memberships m JOIN business_subscriptions s ON s.business_id=m.business_id LEFT JOIN business_subscription_payment_evidence e ON e.subscription_id=s.id WHERE m.account_id=? AND m.status="active" AND m.role IN ("owner","manager") AND s.plan_code<>"free" AND s.status="pending" GROUP BY m.business_id,s.id HAVING COUNT(e.id)=0 ORDER BY s.id DESC LIMIT 1');$pendingPayment->execute([(int)$account['id']]);$paymentBusiness=(int)$pendingPayment->fetchColumn();}catch(PDOException){$paymentBusiness=0;}
  $businessReturn=$_SESSION['business_registration_return']??null;
  $sessionReturn=$_SESSION['session_return']??null;
  unset($_SESSION['business_registration_return']);
  unset($_SESSION['session_return']);
  $safeSessionReturn=is_string($sessionReturn)&&str_starts_with($sessionReturn,'/')&&!str_starts_with($sessionReturn,'//')&&!str_starts_with($sessionReturn,'/negocios/acceso')?$sessionReturn:null;
  require_once dirname(__DIR__,2).'/app/SavedPlaceIntent.php';
  $savedReturn=null;
  try{$savedReturn=SavedPlaceIntent::complete(db(),$_SESSION,(int)$account['id']);}
  catch(Throwable $error){PortalIncidents::technical($error,'saved_place_after_login');$savedReturn=$safeSessionReturn?$safeSessionReturn.'?guardado=error':null;}
  $target=$savedReturn??(is_array($return)&&(int)($return['business']??0)>0?'/business/editorial.php?business='.(int)$return['business'].'&version='.(int)($return['version']??0):($safeSessionReturn??($businessReturn==='/negocios/registro/?tipo=business'?$businessReturn:($paymentBusiness?'/negocios/portal/?tab=business&business='.$paymentBusiness.'&solicitud=guardada#pagos':'/negocios/portal/'))));header('Location: '.site_path($target),true,303);exit;
 }
}catch(Throwable $e){PortalIncidents::technical($e,'account_login');$error=$e->getMessage();http_response_code(400);}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
$viewData=['csrf'=>(string)$_SESSION['csrf'],'email'=>(string)($_POST['email']??''),'closed'=>($_GET['cuenta']??'')==='cerrada','error'=>$error,'savedPlacesBenefit'=>!empty($_SESSION['saved_place_intent'])];
AccountAuthPresentation::render('login',account_layout_context(),$viewData,dirname(__DIR__));
