<?php
declare(strict_types=1);
require_once __DIR__.'/HomeAttractions.php';
require_once __DIR__.'/PortalSettings.php';
require_once __DIR__.'/DatabaseClock.php';
require_once __DIR__.'/SubscriptionPricing.php';

/** Loads the data used by public page components, independently of their HTML. */
final class PortalPageData
{
    private static function configuration(string $file):array
    {
        $config=require $file;
        $overrides=PortalSettings::load(dirname($file));
        if(isset($overrides['privacy']))$config['privacy']=array_replace($config['privacy']??[],$overrides['privacy']);
        return $config;
    }
    public static function publishedCounts(string $file): ?array
    {
        if (!is_file($file)) return null;
        try {
            $config = self::configuration($file);
            $db = new PDO($config['dsn'], $config['db_user'] ?? null, $config['db_password'] ?? null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return $db->query("SELECT
        (SELECT COUNT(*) FROM destinations WHERE publication_status='published') AS guides,
        (SELECT COUNT(*) FROM place_labels l JOIN destinations d ON d.id=l.place_id
         WHERE l.code='heritage_town' AND l.enabled=1 AND d.publication_status='published') AS heritage,
        (SELECT COUNT(*) FROM businesses b JOIN destinations d ON d.id=b.destination_id
         WHERE b.publication_status='published' AND d.publication_status='published') AS businesses")->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $error) {
            error_log('Portal published counts unavailable');
            return null;
        }
    }

    public static function privacy(string $file):array
    {
        $privacy=[];
        if(is_file($file))try{$config=self::configuration($file);$privacy=is_array($config['privacy']??null)?$config['privacy']:[];}
        catch(Throwable $error){error_log('portal-public-fragment: unavailable');}
        return $privacy;
    }
    public static function pricing(string $file,string $locale,bool $includeTable):array
    {
        $table='';$operator='';
        if(is_file($file))try{
            $config=self::configuration($file);
            $pdo=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $pricing=new SubscriptionPricing($pdo);
            if($includeTable)$table=$pricing->publicTable($locale);
            $operator=$pricing->operatorText($locale);
        }catch(Throwable $error){error_log('portal-public-fragment: unavailable');}
        return ['table'=>$table,'operator'=>$operator];
    }
    public static function attractions(string $file,string $locale,string $publicRoot):array
    {
        $items=[];
        if(is_file($file))try{
            $config=self::configuration($file);
            $pdo=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            DatabaseClock::configure($pdo);
            $items=HomeAttractions::items($pdo,$locale,$publicRoot);
        }catch(Throwable $error){error_log('portal-public-fragment: unavailable');}
        return $items;
    }
}
