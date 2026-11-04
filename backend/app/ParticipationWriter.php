<?php
declare(strict_types=1);

/** Persists an already validated request without deciding HTTP or rate limits. */
final class ParticipationWriter
{
    public function __construct(private PDO $pdo) {}
    public function insert(string $requestKey,array $data,string $createdAt):void
    {
        $columns=['request_key','name','business','municipality','category','email','phone','phone_type','participation_version','has_brand','brand_name','description','interest','wants_contact','consent_version','created_at'];
        $values=[$requestKey];foreach(array_slice($columns,1,-2) as $key)$values[]=$data[$key];
        $values[]='contacto-negocio-v1';$values[]=$createdAt;
        $sql='INSERT INTO leads ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
        $this->pdo->prepare($sql)->execute($values);
    }
}
