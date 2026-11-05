<?php
declare(strict_types=1);

/** Read aggregates used by the audience dashboards. */
final class BasicAudienceSelector
{
    public function __construct(private PDO $pdo) {}
    public function businessExposure(string $first,string $last):array
    {
        $query=$this->pdo->prepare("SELECT metric,SUM(metric_count) AS total FROM el_basic_entity_daily WHERE entity_type='business' AND page_day>=? AND page_day<=? AND metric IN ('card_impression','profile_click') GROUP BY metric");
        $query->execute([$first,$last]);$result=['card_impression'=>0,'profile_click'=>0];
        foreach($query as $row)$result[$row['metric']]=(int)$row['total'];
        return $result;
    }
}
