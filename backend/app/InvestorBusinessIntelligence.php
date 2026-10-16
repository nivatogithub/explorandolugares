<?php
declare(strict_types=1);

/** Read-only, source-backed indicators for the private investor/admin workspace. */
final class InvestorBusinessIntelligence
{
    public function __construct(private PDO $pdo) {}

    public function report(int $days): array
    {
        if (!in_array($days, [30, 90, 365], true)) $days = 90;
        $zone = new DateTimeZone('UTC');
        $end = new DateTimeImmutable('tomorrow', $zone);
        $start = $end->modify('-'.$days.' days');
        $previousStart = $start->modify('-'.$days.' days');
        $from = $start->format('Y-m-d H:i:s');
        $to = $end->format('Y-m-d H:i:s');
        $previousFrom = $previousStart->format('Y-m-d H:i:s');
        $previousTo = $from;

        return [
            'days' => $days,
            'period' => ['from' => $start->format('Y-m-d'), 'to' => $end->modify('-1 day')->format('Y-m-d')],
            'previous_period' => ['from' => $previousStart->format('Y-m-d'), 'to' => $start->modify('-1 day')->format('Y-m-d')],
            'snapshot' => $this->snapshot(),
            'current' => $this->periodMetrics($from, $to),
            'previous' => $this->periodMetrics($previousFrom, $previousTo),
            'monthly' => $this->monthlyTrend(),
        ];
    }

    private function snapshot(): array
    {
        return [
            'destinations_total' => $this->scalar("SELECT COUNT(*) FROM destinations"),
            'destinations_published' => $this->scalar("SELECT COUNT(*) FROM destinations WHERE publication_status='published'"),
            'destinations_draft' => $this->scalar("SELECT COUNT(*) FROM destinations WHERE publication_status='draft'"),
            'businesses_total' => $this->scalar('SELECT COUNT(*) FROM businesses'),
            'businesses_published' => $this->scalar("SELECT COUNT(*) FROM businesses WHERE publication_status='published'"),
            'businesses_verified' => $this->scalar("SELECT COUNT(*) FROM businesses WHERE information_status IN ('VERIFICADO','ACTIVO')"),
            'businesses_claimed_active' => $this->scalar("SELECT COUNT(*) FROM businesses WHERE information_status='ACTIVO' AND claim_status='RECLAMADO'"),
            'businesses_paid_active' => $this->scalar("SELECT COUNT(DISTINCT b.id) FROM businesses b JOIN business_subscriptions s ON s.business_id=b.id WHERE b.publication_status='published' AND s.plan_code<>'free' AND s.status='active' AND (s.starts_at IS NULL OR s.starts_at<=CURRENT_TIMESTAMP) AND (s.ends_at IS NULL OR s.ends_at>CURRENT_TIMESTAMP)"),
            'accounts_total' => $this->scalar('SELECT COUNT(*) FROM accounts'),
            'business_accounts_active' => $this->scalar("SELECT COUNT(*) FROM accounts WHERE account_type='business' AND status='active'"),
        ];
    }

    private function periodMetrics(string $from, string $to): array
    {
        $metrics = [
            'destinations_created' => $this->countBetween('destinations', 'created_at', $from, $to),
            'businesses_created' => $this->countBetween('businesses', 'created_at', $from, $to),
            'leads_received' => $this->countBetween('leads', 'created_at', $from, $to),
            'business_applications_received' => $this->countBetween('business_account_applications', 'created_at', $from, $to),
            'claim_requests_received' => $this->countBetween('business_claim_requests', 'created_at', $from, $to),
            'business_accounts_created' => $this->countBetween('accounts', 'created_at', $from, $to, "account_type='business'"),
        ];
        $q = $this->pdo->prepare("SELECT COUNT(*), COALESCE(SUM(quoted_amount),0) FROM business_subscriptions WHERE plan_code<>'free' AND reviewed_at IS NOT NULL AND reviewed_at>=? AND reviewed_at<?");
        $q->execute([$from, $to]);
        $payment = $q->fetch(PDO::FETCH_NUM) ?: [0, 0];
        $metrics['payments_marked_reviewed'] = (int)$payment[0];
        $metrics['reviewed_subscription_value_cop'] = (float)$payment[1];
        $renewals = $this->pdo->prepare("SELECT COUNT(DISTINCT due.business_id) AS due_businesses,
                COUNT(DISTINCT CASE WHEN EXISTS (
                    SELECT 1 FROM business_subscriptions successor
                    WHERE successor.renewal_of_subscription_id=due.id
                      AND successor.starts_at<=due.ends_at
                      AND successor.plan_code<>'free'
                ) THEN due.business_id END) AS renewed_businesses
            FROM business_subscriptions due
            WHERE due.plan_code<>'free' AND due.status IN ('active','expired') AND due.starts_at IS NOT NULL
              AND due.ends_at>=? AND due.ends_at<?");
        $renewals->execute([$from,$to]);$renewalRow=$renewals->fetch(PDO::FETCH_ASSOC)?:['due_businesses'=>0,'renewed_businesses'=>0];
        $metrics['renewal_due_businesses']=(int)$renewalRow['due_businesses'];
        $metrics['renewed_businesses']=(int)$renewalRow['renewed_businesses'];
        $metrics['retention_rate']=$metrics['renewal_due_businesses']>0?($metrics['renewed_businesses']/$metrics['renewal_due_businesses'])*100:null;
        $late=$this->pdo->prepare("SELECT COUNT(DISTINCT current.business_id) FROM business_subscriptions current
            JOIN business_subscriptions previous ON previous.id=current.renewal_of_subscription_id
            WHERE current.plan_code<>'free' AND current.starts_at>=? AND current.starts_at<? AND current.starts_at>previous.ends_at");
        $late->execute([$from,$to]);$metrics['late_reactivated_businesses']=(int)$late->fetchColumn();
        return $metrics;
    }

    private function countBetween(string $table, string $dateColumn, string $from, string $to, string $condition = ''): int
    {
        // Identifiers and condition snippets are defined only in this class, never from request input.
        $sql = 'SELECT COUNT(*) FROM '.$table.' WHERE '.$dateColumn.'>=? AND '.$dateColumn.'<?';
        if ($condition !== '') $sql .= ' AND '.$condition;
        $q = $this->pdo->prepare($sql);
        $q->execute([$from, $to]);
        return (int)$q->fetchColumn();
    }

    private function monthlyTrend(): array
    {
        $zone = new DateTimeZone('UTC');
        $first = new DateTimeImmutable('first day of this month', $zone)->setTime(0, 0)->modify('-11 months');
        $end = new DateTimeImmutable('first day of next month', $zone)->setTime(0, 0);
        $from = $first->format('Y-m-d H:i:s');
        $to = $end->format('Y-m-d H:i:s');
        $months = [];
        for ($date = $first; $date < $end; $date = $date->modify('+1 month')) {
            $key = $date->format('Y-m');
            $months[$key] = ['key' => $key, 'label' => $date->format('m/Y'), 'destinations' => 0, 'businesses' => 0, 'leads' => 0, 'reviewed_value_cop' => 0.0];
        }
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $monthExpression = $driver === 'sqlite'
            ? static fn(string $column): string => "strftime('%Y-%m', {$column})"
            : static fn(string $column): string => "DATE_FORMAT({$column}, '%Y-%m')";
        foreach ([['destinations', 'created_at', 'destinations'], ['businesses', 'created_at', 'businesses'], ['leads', 'created_at', 'leads']] as [$table, $column, $key]) {
            $sql = 'SELECT '.$monthExpression($column).' AS month_key, COUNT(*) AS total FROM '.$table.' WHERE '.$column.'>=? AND '.$column.'<? GROUP BY month_key';
            $q = $this->pdo->prepare($sql); $q->execute([$from, $to]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) if (isset($months[$row['month_key']])) $months[$row['month_key']][$key] = (int)$row['total'];
        }
        $sql = 'SELECT '.$monthExpression('reviewed_at').' AS month_key, COALESCE(SUM(quoted_amount),0) AS total FROM business_subscriptions WHERE plan_code<>\'free\' AND reviewed_at IS NOT NULL AND reviewed_at>=? AND reviewed_at<? GROUP BY month_key';
        $q = $this->pdo->prepare($sql); $q->execute([$from, $to]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) if (isset($months[$row['month_key']])) $months[$row['month_key']]['reviewed_value_cop'] = (float)$row['total'];
        return array_values($months);
    }

    private function scalar(string $sql): int
    {
        return (int)$this->pdo->query($sql)->fetchColumn();
    }
}
