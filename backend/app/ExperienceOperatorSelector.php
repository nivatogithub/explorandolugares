<?php
declare(strict_types=1);

/** Shared public read of reviewed, active operator placements, selected in one batch. */
final class ExperienceOperatorSelector
{
    public function __construct(private PDO $pdo) {}

    public function activeForExperiences(array $experienceIds,string $locale='es'):array
    {
        $experienceIds=array_values(array_unique(array_map('intval',$experienceIds)));
        if(!$experienceIds)return [];
        try{$this->pdo->query('SELECT subscription_id FROM business_experience_subscriptions LIMIT 0');}
        catch(PDOException $error){if(str_contains($error->getMessage(),'no such table')||($error->errorInfo[1]??0)===1146)return [];throw $error;}
        $query=$this->pdo->prepare('SELECT x.experience_id,b.public_id,b.slug,t.display_name,t.summary,s.plan_code,d.slug AS destination_slug
            FROM business_experience_subscriptions x JOIN business_subscriptions s ON s.id=x.subscription_id
            JOIN businesses b ON b.id=s.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale=?
            JOIN points_of_interest p ON p.id=x.experience_id JOIN destinations d ON d.id=p.destination_id
            WHERE x.experience_id IN ('.implode(',',array_fill(0,count($experienceIds),'?')).')
            AND EXISTS(SELECT 1 FROM service_categories c WHERE c.code="experiencias" AND c.is_active=1
                AND (b.category_code=c.code OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=c.code)))
            AND b.destination_id=p.destination_id AND p.poi_type="experience" AND p.publication_status="published"
            AND d.publication_status="published" AND b.publication_status="published" AND b.operation_status<>"CERRADO_DEFINITIVO"
            AND s.plan_code IN ("featured","page","page_premium") AND s.status="active" AND s.reviewed_at IS NOT NULL
            AND s.starts_at<=CURRENT_TIMESTAMP AND s.ends_at>CURRENT_TIMESTAMP
            AND s.id=(SELECT MAX(s2.id) FROM business_subscriptions s2 WHERE s2.business_id=b.id AND s2.status="active"
                AND s2.starts_at<=CURRENT_TIMESTAMP AND s2.ends_at>CURRENT_TIMESTAMP)
            ORDER BY x.experience_id,t.display_name');
        $query->execute([$locale,...$experienceIds]);$result=[];
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){$id=(int)$row['experience_id'];unset($row['experience_id']);$result[$id][]=$row;}
        return $result;
    }
}
