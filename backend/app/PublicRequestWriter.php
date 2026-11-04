<?php
declare(strict_types=1);

/** Stores validated public requests and administrative reviews. */
final class PublicRequestWriter
{
    public function __construct(private PDO $pdo) {}
    public function insert(string $reference,string $type,string $name,string $email,string $message):void
    {
        $q=$this->pdo->prepare('INSERT INTO public_requests(reference,request_type,name,email,message,consent_version) VALUES(?,?,?,?,?,?)');
        $q->execute([$reference,$type,$name,$email,$message,'public-request-v1']);
    }
    public function review(int $id,string $status,string $note):void
    {
        $this->pdo->prepare('UPDATE public_requests SET status=?,response_note=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$note,$id]);
    }
}
