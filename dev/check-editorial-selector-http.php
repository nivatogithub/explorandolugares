<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-admin-layout-http.php';$previous=ob_get_clean();$result=[];
foreach(['valid','guest','expired'] as $mode){
 layout_http_session($pdo,$account,$mode,function(string $session,string $csrf)use(&$result,$mode,$business):void{
  [$status,$html]=layout_http_request($session,'/business/editorial.php?business='.$business.'&admin=1');
  $html=str_replace($csrf,'synthetic-csrf',$html);$result[$mode]=['status'=>$status,'hash'=>hash('sha256',$html)];
 });
}
echo $previous.json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
