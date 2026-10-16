<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessResearchContactImporter.php';

/** Name/destination matching stays compatible with public-directory research packages. */
final class BusinessChannelImporter
{
    public static function import(PDO $pdo,array $package): array
    {
        return BusinessResearchContactImporter::import($pdo,$package);
    }

    public static function saveRecipient(PDO $pdo,int $businessId,int $channelId,mixed $label):void
    {
        if(!BusinessResearchContactImporter::recipientReady($pdo))throw new RuntimeException('Falta habilitar el destinatario de canales en Base de datos (028).');
        if(!is_string($label)||strlen($label)>180||preg_match('/[\x00-\x1F\x7F]/',$label))throw new RuntimeException('El destinatario puede ser un nombre o área de hasta 180 caracteres.');
        $q=$pdo->prepare('SELECT id FROM business_research_contacts WHERE id=? AND business_id=?');$q->execute([$channelId,$businessId]);
        if($q->fetchColumn()===false)throw new RuntimeException('El canal no pertenece al negocio seleccionado.');
        $pdo->prepare('UPDATE business_research_contacts SET recipient_label=? WHERE id=? AND business_id=?')->execute([trim($label),$channelId,$businessId]);
    }
}
