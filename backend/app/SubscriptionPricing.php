<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessSubscriptionChoice.php';
require_once __DIR__.'/ExperienceOperatorAddon.php';

/** Presentation reads the same live catalogue as checkout; no second price list. */
final class SubscriptionPricing
{
    public const MONTHS=['monthly'=>1,'quarterly'=>3,'semiannual'=>6,'annual'=>12];
    public function __construct(private PDO $pdo){}
    public function periods():array
    {
        $rows=$this->pdo->query('SELECT period_code,billable_months FROM subscription_pricing_policy')->fetchAll(PDO::FETCH_KEY_PAIR);
        $result=[];
        foreach(self::MONTHS as $code=>$months){
            $billable=$rows[$code]??null;
            $result[$code]=['months'=>$months,'discount_percentage'=>is_numeric($billable)&&$billable>0&&$billable<=$months?round(100*(1-(float)$billable/$months),2):null];
        }
        return $result;
    }
    public static function money(float|int|string $amount,string $locale='es'):string
    {
        return '$'.number_format((float)$amount,0,',',$locale==='en'?',':'.').' COP';
    }
    public function periodLabel(string $period,string $locale='es'):string
    {
        $labels=match($locale){'en'=>['monthly'=>'Monthly','quarterly'=>'Quarterly','semiannual'=>'Semiannual','annual'=>'Annual'],'pt-BR'=>['monthly'=>'Mensal','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'],default=>BusinessSubscriptionChoice::PERIODS};
        $label=$labels[$period]??$period;$discount=$this->periods()[$period]['discount_percentage']??null;
        return $label.($discount>0?' · '.(string)$discount.' % '.match($locale){'en'=>'off','pt-BR'=>'de desconto',default=>'de descuento'}:'');
    }
    /** Compare actual catalogue amounts, not the policy alone or a historical payment. */
    public function savings(string $plan,string $period,bool $withOperator=false):?array
    {
        $months=self::MONTHS[$period]??0;if($plan==='free'||$months<=1)return null;
        $choice=new BusinessSubscriptionChoice($this->pdo);$monthly=$choice->quote($plan,'monthly');$total=$choice->quote($plan,$period);
        if($monthly['quote_status']!=='quoted'||$total['quote_status']!=='quoted'||$monthly['currency']!=='COP'||$total['currency']!=='COP')return null;
        $monthlyAmount=(float)$monthly['quoted_amount'];$totalAmount=(float)$total['quoted_amount'];
        if($withOperator){$prices=(new ExperienceOperatorAddon($this->pdo))->prices();foreach(['monthly',$period] as $key)if(!isset($prices[$key])||$prices[$key]['currency']!=='COP'||!is_numeric($prices[$key]['amount']))return null;$monthlyAmount+=(float)$prices['monthly']['amount'];$totalAmount+=(float)$prices[$period]['amount'];}
        $reference=(int)round($monthlyAmount*100)*$months;$totalCents=(int)round($totalAmount*100);$saving=$reference-$totalCents;
        if($reference<=0||$saving<=0)return null;
        return ['months'=>$months,'reference_cents'=>$reference,'total_cents'=>$totalCents,'saving_cents'=>$saving,'percentage'=>round(100*$saving/$reference,2)];
    }
    public function publicTable(string $locale='es'):string
    {
        $labels=match($locale){'en'=>['Plan','Free','Featured','Commercial Page','Premium','Experience add-on','Totals per period in COP. Discounts compared with paying month by month. The experience add-on is added to a paid plan.'],'pt-BR'=>['Plano','Grátis','Destaque','Página Comercial','Premium','Complemento de experiência','Totais por período em COP. Descontos comparados ao pagamento mês a mês. O complemento de experiência é somado a um plano pago.'],default=>['Plan','Gratis','Destacado','Página Comercial','Premium','Complemento de experiencia','Totales por período en COP. Descuentos frente a pagar mes a mes. El complemento de experiencia se suma a un plan pago.']};
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<div class="simulator-table-scroll"><table class="simulator-table subscription-current-prices"><caption>'.$escape($labels[6]).'</caption><thead><tr><th scope="col">'.$escape($labels[0]).'</th>';
        foreach(self::MONTHS as $period=>$months)$html.='<th scope="col">'.$escape($this->periodLabel($period,$locale)).'</th>';
        $html.='</tr></thead><tbody>';$service=new BusinessSubscriptionChoice($this->pdo);
        foreach(['free'=>1,'featured'=>2,'page'=>3,'page_premium'=>4,'operator'=>5] as $plan=>$i){
            $html.='<tr><th scope="row">'.$escape($labels[$i]).'</th>';
            foreach(self::MONTHS as $period=>$months){$price=$plan==='operator'?((new ExperienceOperatorAddon($this->pdo))->prices()[$period]??[]):$service->quote($plan,$period);$amount=$price['quoted_amount']??$price['amount']??null;$html.='<td>'.$escape($amount!==null&&($price['currency']??'')==='COP'?self::money($amount,$locale):'—').'</td>';}
            $html.='</tr>';
        }
        return $html.'</tbody></table></div>';
    }
    public function operatorText(string $locale='es'):string
    {
        $prices=(new ExperienceOperatorAddon($this->pdo))->prices();$items=[];
        foreach(self::MONTHS as $period=>$months){$price=$prices[$period]??[];if(!isset($price['amount'])||($price['currency']??'')!=='COP')return ''; $items[]=$this->periodLabel($period,$locale).': '.self::money($price['amount'],$locale);}
        return implode(' · ',$items);
    }
}
