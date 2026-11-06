<?php
declare(strict_types=1);
final class SavedPlaceWriter
{
 public function __construct(private PDO $pdo){}
 public function change(int $accountId,int $placeId,bool $save):void{
  
  if(!$save){$this->pdo->prepare('DELETE FROM account_saved_places WHERE account_id=? AND place_id=?')->execute([$accountId,$placeId]);return;}
  $sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'INSERT INTO account_saved_places(account_id,place_id) VALUES(?,?) ON DUPLICATE KEY UPDATE place_id=VALUES(place_id)':'INSERT INTO account_saved_places(account_id,place_id) VALUES(?,?) ON CONFLICT(account_id,place_id) DO NOTHING';
  $this->pdo->prepare($sql)->execute([$accountId,$placeId]);
 }
}
