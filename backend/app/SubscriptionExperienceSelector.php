<?php
declare(strict_types=1);
/** Public published experiences for subscription quoting. */
final class SubscriptionExperienceSelector
{
    public function __construct(private PDO $pdo) {}
    public function published():array
    {
        return $this->pdo->query('SELECT p.id,p.destination_id,t.name,dt.display_name AS destination_name,d.country_code,d.department FROM points_of_interest p JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale="es" JOIN destinations d ON d.id=p.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es" WHERE p.poi_type="experience" AND p.publication_status="published" AND d.publication_status="published" ORDER BY dt.display_name,t.name')->fetchAll(PDO::FETCH_ASSOC);
    }
}
