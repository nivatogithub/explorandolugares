<?php
declare(strict_types=1);
interface PaymentEvidenceStorage
{
    /** @return array{storage_name:string,path:string,original_name:string,mime_type:string,file_size:int} */
    public function store(array $upload):array;
    public function discard(array $file):void;
}
