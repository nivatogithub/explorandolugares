<?php
declare(strict_types=1);

/** Read-only queries for business claims; persistent database names remain unchanged. */
final class BusinessClaimSelector
{
    public function __construct(private PDO $pdo) {}

    public function places(string $locale='es'): array
    {
        $locale=$this->locale($locale);
        if($locale!=='es'){
            $query=$this->pdo->prepare('SELECT DISTINCT d.id,d.slug,COALESCE(NULLIF(tr.display_name,""),t.display_name) AS display_name FROM destinations d
                JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es"
                LEFT JOIN destination_translations tr ON tr.destination_id=d.id AND tr.locale=?
                JOIN businesses b ON b.destination_id=d.id
                WHERE d.publication_status="published" AND b.publication_status="published"
                  AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
                ORDER BY d.display_order,t.display_name');
            $query->execute([$locale]);return $query->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->pdo->query('SELECT DISTINCT d.id,d.slug,t.display_name FROM destinations d
            JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es"
            JOIN businesses b ON b.destination_id=d.id
            WHERE d.publication_status="published" AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
            ORDER BY d.display_order,t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(int $placeId,string $locale='es'): array
    {
        $locale=$this->locale($locale);
        if($locale!=='es'){
            $query=$this->pdo->prepare('SELECT DISTINCT c.code,COALESCE(NULLIF(tr.label,""),t.label) AS label FROM service_categories c
                JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es"
                LEFT JOIN service_category_translations tr ON tr.category_code=c.code AND tr.locale=?
                JOIN businesses b ON b.category_code=c.code
                WHERE c.is_active=1 AND b.destination_id=? AND b.publication_status="published"
                  AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
                ORDER BY c.display_order,t.label');
            $query->execute([$locale,$placeId]);return $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $query=$this->pdo->prepare('SELECT DISTINCT c.code,t.label FROM service_categories c
            JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es"
            JOIN businesses b ON b.category_code=c.code
            WHERE c.is_active=1 AND b.destination_id=? AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
            ORDER BY c.display_order,t.label');
        $query->execute([$placeId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(int $placeId,string $category,string $name='',string $locale='es'): array
    {
        if($placeId<1||$category==='')return [];
        $locale=$this->locale($locale);
        if($locale!=='es'){
            $query=$this->pdo->prepare('SELECT b.id,b.information_status,COALESCE(NULLIF(tr.display_name,""),t.display_name) AS display_name,b.address_text
                FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
                LEFT JOIN business_translations tr ON tr.business_id=b.id AND tr.locale=?
                WHERE b.destination_id=? AND b.category_code=? AND b.publication_status="published"
                  AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
                  AND COALESCE(NULLIF(tr.display_name,""),t.display_name) LIKE ? ORDER BY t.display_name LIMIT 500');
            $query->execute([$locale,$placeId,$category,'%'.str_replace(['%','_'],'',trim($name)).'%']);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $query=$this->pdo->prepare('SELECT b.id,b.information_status,t.display_name,b.address_text
            FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            WHERE b.destination_id=? AND b.category_code=? AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
              AND t.display_name LIKE ? ORDER BY t.display_name LIMIT 500');
        $query->execute([$placeId,$category,'%'.str_replace(['%','_'],'',trim($name)).'%']);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private function locale(string $locale):string
    {
        return match(strtolower($locale)){'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
    }

    public function requests(): array
    {
        return $this->pdo->query('SELECT r.*,b.public_id,b.information_status,b.claim_status,t.display_name,d.slug AS destination_slug,dt.display_name AS destination_name,c.code AS category_code,ct.label AS category_label
            FROM business_claim_requests r JOIN businesses b ON b.id=r.business_id
            JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            JOIN destinations d ON d.id=b.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            JOIN service_categories c ON c.code=b.category_code JOIN service_category_translations ct ON ct.category_code=c.code AND ct.locale="es"
            ORDER BY CASE r.status WHEN "pending" THEN 0 WHEN "in_review" THEN 1 ELSE 2 END,r.created_at')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeAccount(int $accountId):array|false
    {
        $query=$this->pdo->prepare('SELECT id,display_name,email FROM accounts WHERE id=? AND account_type="business" AND status="active"');
        $query->execute([$accountId]);return $query->fetch(PDO::FETCH_ASSOC);
    }

    /** Must be called inside the service transaction. */
    public function lockBusiness(int $businessId):array|false
    {
        $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $query=$this->pdo->prepare('SELECT id,claim_status,publication_status FROM businesses WHERE id=?'.$lock);
        $query->execute([$businessId]);return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function findRequest(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT business_id,status FROM business_claim_requests WHERE id=?');
        $query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
}
