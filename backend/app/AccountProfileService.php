<?php
declare(strict_types=1);

/** The account is the responsible person's profile; no separate person registry. */
final class AccountProfileService
{
    public function __construct(private PDO $pdo) {}

    public function ready(): bool
    {
        try {$this->pdo->query('SELECT contact_phone FROM accounts LIMIT 0');return true;}
        catch (PDOException $error) {
            if (in_array($error->errorInfo[0]??'',['42S02','42S22'],true)||str_contains($error->getMessage(),'no such table:')||str_contains($error->getMessage(),'no such column:')) return false;
            throw $error;
        }
    }

    public function forBusiness(int $businessId): array
    {
        $phone=$this->ready()?'a.contact_phone':'""';
        $q=$this->pdo->prepare('SELECT a.id AS account_id,a.display_name,a.email,a.status AS account_status,'.$phone.' AS contact_phone
            FROM business_account_memberships m JOIN accounts a ON a.id=m.account_id
            WHERE m.business_id=? AND m.status="active"');
        $q->execute([$businessId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>1) throw new RuntimeException('Este negocio tiene más de una cuenta activa. Revisa su asociación en Cuentas.');
        return $rows;
    }

    public function initializePhone(int $accountId,string $phone): void
    {
        $phone=self::phone($phone);
        if ($phone==='') return;
        $this->pdo->prepare('UPDATE accounts SET contact_phone=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND contact_phone=""')->execute([$phone,$accountId]);
    }

    public function syncApprovedApplication(int $applicationId): void
    {
        $q=$this->pdo->prepare('SELECT r.account_id,r.business_id,r.contact_phone FROM business_account_applications r WHERE r.id=? AND r.status="approved"');
        $q->execute([$applicationId]);$app=$q->fetch(PDO::FETCH_ASSOC);
        if (!$app||!(int)$app['business_id']) throw new RuntimeException('El responsable debe proceder de una solicitud aprobada.');
        $responsible=$this->forBusiness((int)$app['business_id']);
        if (!$responsible||(int)$responsible[0]['account_id']!==(int)$app['account_id']) throw new RuntimeException('La solicitud no corresponde a la cuenta responsable de este negocio.');
        $this->initializePhone((int)$app['account_id'],(string)$app['contact_phone']);
    }

    public function savePhoneForBusiness(int $businessId,mixed $phone): void
    {
        if (!$this->ready()) throw new RuntimeException('Falta habilitar el teléfono de la cuenta en Base de datos (028).');
        if (!is_string($phone)) throw new RuntimeException('Revisa el teléfono del responsable.');
        $phone=self::phone($phone);$this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$q->execute([$businessId]);
            if ($q->fetchColumn()===false) throw new RuntimeException('Selecciona un negocio existente.');
            $responsible=$this->forBusiness($businessId);
            if (!$responsible) throw new RuntimeException('El negocio todavía no tiene una cuenta responsable asociada.');
            $this->pdo->prepare('UPDATE accounts SET contact_phone=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$phone,$responsible[0]['account_id']]);
            $this->pdo->commit();
        } catch (Throwable $error) {if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $error;}
    }

    private static function phone(string $phone): string
    {
        $phone=trim($phone);
        if ($phone!==''&&(strlen($phone)>40||!preg_match('/^\+?[0-9() .-]{7,39}$/D',$phone)||strlen(preg_replace('/\D/','',$phone)??'')<7)) throw new RuntimeException('Revisa el teléfono del responsable.');
        return $phone;
    }
}
