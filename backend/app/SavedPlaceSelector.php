<?php
declare(strict_types=1);
final class SavedPlaceSelector
{
 public function __construct(private PDO $pdo){}
 public function find(string $slug):?array{
  $q=$this->pdo->prepare("SELECT d.id,d.slug,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' WHERE d.slug=? AND d.publication_status='published'");$q->execute([$slug]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
 }
 public function savedSlugs(int $accountId,array $slugs):array{
  $slugs=array_values(array_unique(array_filter($slugs,'is_string')));if(!$slugs)return [];
  if(count($slugs)>100)throw new InvalidArgumentException('Too many bookmark states.');
  $q=$this->pdo->prepare("SELECT d.slug FROM account_saved_places s JOIN destinations d ON d.id=s.place_id WHERE s.account_id=? AND d.publication_status='published' AND d.slug IN (".implode(',',array_fill(0,count($slugs),'?')).")");$q->execute([$accountId,...$slugs]);return $q->fetchAll(PDO::FETCH_COLUMN);
 }
 public function contains(int $accountId,string $slug):bool{
  $q=$this->pdo->prepare("SELECT 1 FROM account_saved_places s JOIN destinations d ON d.id=s.place_id WHERE s.account_id=? AND d.slug=? AND d.publication_status='published'");$q->execute([$accountId,$slug]);return (bool)$q->fetchColumn();
 }
 public function list(int $accountId):array{
  $q=$this->pdo->prepare("SELECT d.slug,t.display_name FROM account_saved_places s JOIN destinations d ON d.id=s.place_id JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' WHERE s.account_id=? AND d.publication_status='published' ORDER BY s.created_at DESC,d.id");$q->execute([$accountId]);return $q->fetchAll(PDO::FETCH_ASSOC);
 }
}
