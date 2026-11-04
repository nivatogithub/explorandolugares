<?php
declare(strict_types=1);
require_once __DIR__.'/ParticipationSelector.php';
require_once __DIR__.'/ParticipationWriter.php';

/** Keeps duplicate submission handling together with persistence. */
final class ParticipationService
{
    public function __construct(private ParticipationSelector $selector,private ParticipationWriter $writer) {}
    public function record(string $requestKey,array $data,string $createdAt):void
    {
        try{$this->writer->insert($requestKey,$data,$createdAt);}
        catch(PDOException $error){
            if(!in_array($error->getCode(),['23000','23505'],true)||!$this->selector->hasRequest($requestKey))throw $error;
        }
    }
}
