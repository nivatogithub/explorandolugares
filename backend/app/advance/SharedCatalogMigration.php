<?php
declare(strict_types=1);
final class SharedCatalogMigration {
    public static function apply(PDO $db): void {
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(!in_array($driver,['mysql','sqlite'],true))throw new RuntimeException('Motor no compatible.');
        // Comprobar todo antes de DDL, que MySQL no puede revertir de forma atómica.
        foreach(['destinations','destination_translations','service_categories','service_category_translations','businesses','business_translations','business_contacts','business_sources','advance_contacts','advance_audit'] as $table)$db->query('SELECT 1 FROM '.$table.' LIMIT 0');
        $hasTable=$driver==='mysql'
            ?(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='advance_establishments'")->fetchColumn()
            :(bool)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='advance_establishments'")->fetchColumn();
        if($hasTable&&(int)$db->query('SELECT COUNT(*) FROM advance_establishments')->fetchColumn()>0){
            throw new RuntimeException('Hay establecimientos heredados. No se modificó el esquema. Reconciliar cada registro con un businesses.id existente o un nuevo borrador, conservando fuentes y vínculo CRM, antes de retirar la tabla. No se crean coincidencias por nombre automáticamente.');
        }
        $columns=$driver==='mysql'
            ?$db->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='advance_contacts'")->fetchAll(PDO::FETCH_COLUMN)
            :array_column($db->query('PRAGMA table_info(advance_contacts)')->fetchAll(PDO::FETCH_ASSOC),'name');
        $hasDestination=in_array('destination_id',$columns,true);
        $hasLink=$driver==='mysql'
            ?(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='advance_business_crm'")->fetchColumn()
            :(bool)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='advance_business_crm'")->fetchColumn();
        if($hasDestination&&$hasLink&&!$hasTable)return;
        if($hasDestination||$hasLink)throw new RuntimeException('Migración parcial: revisar esquema y respaldo antes de continuar.');
        // Source checkout stores database/ beside app/; hosting packages it inside app/.
        $filename='008-advance-shared-catalog.'.$driver.'.sql';
        $migration=dirname(__DIR__).'/database/migrations/'.$filename;
        if(!is_file($migration))$migration=dirname(__DIR__,2).'/database/migrations/'.$filename;
        if(!is_file($migration))throw new RuntimeException('Falta la migración del catálogo compartido en la aplicación privada.');
        $sql=file_get_contents($migration);
        if(!$hasTable)$sql=str_replace('DROP TABLE advance_establishments;','',$sql);
        if($driver==='sqlite')$db->beginTransaction();
        try{
            foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$db->exec($statement);
            if($driver==='sqlite')$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
