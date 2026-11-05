<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/PlaceAdminService.php';
require dirname(__DIR__,2).'/app/PlacePackage.php';
if (isset($_SESSION['admin']) && time()-(int)($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
if (empty($_SESSION['admin'])) { http_response_code(403); exit('Inicia sesión desde el panel de administración.'); }
$_SESSION['last_seen']=time();
$service=new PlaceAdminService(db());$error='';$id=max(0,(int)($_GET['id']??$_POST['id']??0));$tab=(string)($_GET['tab']??($id?'crear':'lista'));if(!in_array($tab,['lista','crear'],true))$tab='lista';if($id)$tab='crear';
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();$action=(string)($_POST['action']??'save');
        if($action==='create_starter'){$id=$service->createStarterDraft((string)($_POST['starter_slug']??''),dirname(__DIR__));header('Location: places.php?id='.$id.'&saved=starter',true,303);exit;}
        if ($action==='save') {
            $publicRoot=getenv('EL_ADMIN_MEDIA_ROOT')?:($_SERVER['DOCUMENT_ROOT']??'');
            if (!$publicRoot) throw new RuntimeException('No se encontró la carpeta pública para guardar imágenes.');
            $id=$service->save((isset($_POST['labels_submitted'])?array_replace($_POST,['labels'=>!empty($_POST['heritage_town'])?['heritage_town']:[]]):$_POST),$_FILES['hero_upload']??[],$_FILES['place_uploads']??[],$publicRoot);
            $packageToken=is_string($_POST['package_import_token']??null)?$_POST['package_import_token']:'';
            $staged=$_SESSION['destination_package_staging'][$packageToken]??null;
            if ($packageToken!==''&&is_array($staged)) { PlacePackage::removeDirectory((string)$staged['directory']);unset($_SESSION['destination_package_staging'][$packageToken]); }
            header('Location: places.php?id='.$id.'&saved=1',true,303);exit;
        }
        if ($action==='archive'&&$id>0) {$service->archive($id);header('Location: places.php?saved=archived',true,303);exit;}
        if ($action==='delete'&&$id>0) {$service->delete($id);header('Location: places.php?saved=deleted',true,303);exit;}
        throw new RuntimeException('Acción no válida.');
    }
} catch (Throwable $e) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'destinos'); $error=$e instanceof PDOException?'No se guardó el lugar. Revisa que el identificador no esté repetido y que la base tenga la migración dinámica.':$e->getMessage();http_response_code(400); }
$record=$id>0?$service->find($id):null;
if($id>0&&!$record&&$error===''){$error='No existe un lugar con ese ID.';http_response_code(404);}
$rows=$service->all();$media=$service->mediaOptions();$locales=['es'=>'Español','en'=>'English','pt-BR'=>'Português (Brasil)'];
$placeSearch='';$placeStatus='';$placeDepartment='';$placeDepartments=[];$placeRows=[];$starterOptions=[];
if($tab==='lista'){
    $placeSearch=trim((string)($_GET['q']??''));$placeStatus=(string)($_GET['estado']??'');$placeDepartment=trim((string)($_GET['departamento']??''));$placeDepartments=array_values(array_unique(array_filter(array_column($rows,'department'))));sort($placeDepartments,SORT_NATURAL|SORT_FLAG_CASE);$placeRows=array_values(array_filter($rows,static fn(array $row):bool=>($placeSearch===''||mb_stripos((string)($row['display_name']??''),$placeSearch)!==false)&&($placeDepartment===''||$row['department']===$placeDepartment)&&($placeStatus===''||$row['publication_status']===$placeStatus)));
    $starterOptions=$service->starterOptions();
}
$viewData=compact('record','id','tab','error','media','locales','placeSearch','placeStatus','placeDepartment','placeDepartments','placeRows','starterOptions');
$viewData['adminLayout']=admin_layout_context();
$viewData['defaultDisplayOrder']=count($rows)+1;
$viewData['notice']=$_GET['saved']??'';$viewData['csrfToken']=$_SESSION['csrf'];
// Keep the service, connection and raw request arrays out of the view's local scope.
(static function(array $data,string $adminDirectory):void {
    extract($data,EXTR_SKIP);
    require dirname($adminDirectory,2).'/app/views/admin/places.php';
})($viewData,__DIR__);
