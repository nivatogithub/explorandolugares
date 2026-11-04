<?php
declare(strict_types=1);
require_once __DIR__.'/PlaceSelector.php';
require_once __DIR__.'/BusinessDirectorySelector.php';
require_once __DIR__.'/ExperienceOperatorSelector.php';
require_once __DIR__.'/BusinessContactVisibility.php';
require_once __DIR__.'/DatabaseClock.php';

/** Assemble a place's public data. SQL belongs to selectors; HTML belongs to views. */
final class PlaceRepository
{
    public function __construct(
        private PlaceReader $places,
        private BusinessDirectorySelector $directory,
        private ExperienceOperatorSelector $operators
    ) {}

    /** Composition boundary for existing PDO-based entry points. */
    public static function fromDatabase(PDO $pdo):self
    {
        DatabaseClock::configure($pdo);
        return new self(new PlaceSelector($pdo),new BusinessDirectorySelector($pdo),new ExperienceOperatorSelector($pdo));
    }

    public function findPublished(string $slug,string $locale,bool $preview=false):?array
    {
        $locale=$this->locale($locale);
        $place=$this->places->findPublished($slug,$locale,$preview);
        if(!$place)return null;
        $id=(int)$place['id'];
        $place['available_locales']=$this->places->availableLocales($id);
        $place['history']=$this->places->history($id,$locale);
        $place['reasons']=$this->places->reasons($id,$locale);
        $place['hero']=$this->media($this->places->media($id,$locale,'hero'));
        $place['history_media']=$this->media($this->places->media($id,$locale,'history'));
        $place['sources']=$this->places->sources($id,$locale);
        $attractions=[];
        foreach($this->places->attractions($id,$locale,$preview) as $row){
            $key=(int)$row['id'];
            if(!isset($attractions[$key]))$attractions[$key]=[
                'id'=>$key,'operators'=>[],'slug'=>$row['slug'],'type'=>$row['poi_type'],
                'name'=>$row['name'],'description'=>$row['description'],'map_url'=>$row['map_url'],
                'external_url'=>$row['external_url'],'external_label'=>$row['external_label'],'media'=>[],
            ];
            if($row['asset_path']!==null)$attractions[$key]['media'][]=$this->media($row);
        }
        $experiences=array_keys(array_filter($attractions,static fn($item)=>$item['type']==='experience'));
        $operators=$this->operators->activeForExperiences($experiences,$locale);
        foreach($attractions as $key=>&$attraction)if($attraction['type']==='experience')$attraction['operators']=$operators[$key]??[];unset($attraction);
        $place['places']=array_values($attractions);
        $place['categories']=$this->directory->categoriesForPlace($id,$locale,$preview);
        $place['businesses']=$this->directory->businessesForPlace($id,$locale,$preview);
        $place['public_contacts']=[];
        $ids=array_column($place['businesses'],'id');
        if($ids){
            foreach($this->directory->publicContacts($ids) as $contact)$place['public_contacts'][(int)$contact['business_id']][]=$contact;
            foreach($place['businesses'] as $business){
                $businessId=(int)$business['id'];
                $place['public_contacts'][$businessId]=BusinessContactVisibility::filter((string)$business['plan_code'],$place['public_contacts'][$businessId]??[]);
            }
            $plans=array_values(array_unique(array_column($place['businesses'],'plan_code')));$features=[];
            foreach($this->directory->planFeatures($plans) as $feature)$features[$feature['plan_code']][$feature['feature_code']]=true;
            foreach($place['businesses'] as &$business)$business['features']=$features[$business['plan_code']]??[];unset($business);
            $place['business_features']=$features;
        }
        return $place;
    }

    public function listPublished(string $locale):array
    {
        $rows=[];
        foreach($this->places->listPublished($this->locale($locale)) as $row)if(!isset($rows[$row['slug']]))$rows[$row['slug']]=$row;
        return array_values($rows);
    }

    private function locale(string $locale):string
    {
        return in_array($locale,['es','en','pt-BR'],true)?$locale:'es';
    }

    /** Public allowlist: selectors do not fetch private research or media provenance. */
    private function media(?array $row):?array
    {
        if(!$row)return null;
        return ['path'=>$row['asset_path'],'origin'=>$row['origin']??'external','alt'=>$row['alt_text'],
            'caption'=>$row['caption'],'creator'=>$row['creator'],'source_url'=>$row['source_url'],
            'license_name'=>$row['license_name'],'license_url'=>$row['license_url'],'width'=>$row['width'],
            'height'=>$row['height'],'srcset'=>$row['responsive_srcset']??'','sizes'=>$row['responsive_sizes']??''];
    }
}
