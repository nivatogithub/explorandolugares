<?php
declare(strict_types=1);
require_once __DIR__.'/SavedPlaceSelector.php';
require_once __DIR__.'/SavedPlaceWriter.php';
/** Coordinates private saved guides; authentication and CSRF belong to the controller. */
final class SavedPlaces
{
 private SavedPlaceSelector $selector;
 private SavedPlaceWriter $writer;
 public function __construct(PDO $pdo){$this->selector=new SavedPlaceSelector($pdo);$this->writer=new SavedPlaceWriter($pdo);}
 public function find(string $slug):?array{return $this->selector->find($slug);}
 public function list(int $accountId):array{return $this->selector->list($accountId);}
 public function change(int $accountId,string $slug,bool $save):void{
  if($accountId<1)throw new RuntimeException('Inicia sesión para guardar lugares.');
  $place=$this->selector->find($slug);
  if(!$place)throw new RuntimeException('Este lugar no está disponible para guardar.');
  $this->writer->change($accountId,(int)$place['id'],$save);
 }
}
