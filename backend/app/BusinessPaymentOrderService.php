<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessPaymentOrderSelector.php';
require_once __DIR__.'/BusinessPaymentOrderWriter.php';
require_once __DIR__.'/PaymentEvidenceStorage.php';
require_once __DIR__.'/SubscriptionChangeService.php';
require_once __DIR__.'/BusinessPromotions.php';
require_once __DIR__.'/BusinessPaymentEvidenceService.php';
/** Account-owned immutable orders. One manual receipt covers all item allocations atomically. */
final class BusinessPaymentOrderService
{
    private BusinessPaymentOrderSelector $read;
    private BusinessPaymentOrderWriter $write;
    public function __construct(private PDO $pdo,private ?PaymentEvidenceStorage $storage=null)
    {$this->read=new BusinessPaymentOrderSelector($pdo);$this->write=new BusinessPaymentOrderWriter($pdo);}
    private function transaction(callable $work):mixed
    {
        if($this->pdo->inTransaction())throw new LogicException('Payment order owns its transaction.');
        $this->pdo->beginTransaction();try{$result=$work();$this->pdo->commit();return $result;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function authorize(int $account,int $business,bool $lock=false):array
    {
        $row=$this->read->business($account,$business,$lock);if(!$row)throw new RuntimeException('Uno de los negocios no está asociado a tu cuenta o ya no puedes administrarlo.');return $row;
    }
    private function normalize(array $choices):array
    {
        if(!$choices||count($choices)>50)throw new RuntimeException('Selecciona entre uno y 50 negocios.');$normalized=[];
        foreach($choices as $choice){
            if(!is_array($choice)||!ctype_digit((string)($choice['business_id']??''))||(int)$choice['business_id']<1)throw new RuntimeException('Selecciona negocios válidos.');
            $id=(int)$choice['business_id'];if(isset($normalized[$id]))throw new RuntimeException('Un negocio está repetido en la selección.');
            $plan=$choice['plan_code']??null;$period=$choice['billing_frequency']??null;
            if(!is_string($plan)||!is_string($period))throw new RuntimeException('Revisa el plan y período de cada negocio.');
            if(!isset(BusinessPromotions::PLANS[$plan])||!isset(BusinessSubscriptionChoice::PERIODS[$period]))throw new RuntimeException('Revisa el plan y período de cada negocio.');
            $experience=filter_var($choice['experience_id']??0,FILTER_VALIDATE_INT);if($experience===false||$experience<0)throw new RuntimeException('Revisa el complemento de experiencia.');
            $normalized[$id]=['business_id'=>$id,'plan_code'=>$plan,'billing_frequency'=>$period,'experience_id'=>$experience];
        }ksort($normalized,SORT_NUMERIC);return array_values($normalized);
    }
    private function quote(int $account,array $choice):array
    {
        $id=$choice['business_id'];if(!$this->read->activePlan($choice['plan_code']))throw new RuntimeException('Selecciona una suscripción disponible.');$business=$this->authorize($account,$id,true);$sub=$this->read->latestSubscription($id,true);
        if(!$sub||!in_array($sub['status'],['pending','pending_publication','active','expired'],true))throw new RuntimeException('Este negocio no tiene una suscripción disponible para contratar o renovar.');
        if($this->read->pendingEvidence($id))throw new RuntimeException('Ya hay un comprobante pendiente para uno de los negocios.');
        if($this->read->openForBusiness($id))throw new RuntimeException('Uno de los negocios ya está en otra orden. Continúa esa orden o cambia su selección.');
        $addons=new ExperienceOperatorAddon($this->pdo);$addon=null;
        if($choice['experience_id']){$addons->requireEligible($id);$addon=$addons->quote($choice['plan_code'],$choice['billing_frequency'],(int)$business['destination_id'],$choice['experience_id']);}
        $changes=new SubscriptionChangeService($this->pdo);$quote=$changes->quote($id,$choice['plan_code'],$choice['billing_frequency'],$addon);
        $promotions=new BusinessPromotions($this->pdo);$offer=$promotions->forSubscription((int)$sub['id']);
        if($offer&&($sub['plan_code']!==$choice['plan_code']||$sub['billing_frequency']!==$choice['billing_frequency']))throw new RuntimeException('Conserva el plan y período del cupón ya aplicado.');
        if($offer)$quote=$promotions->checkout($sub,$quote);
        if($quote['currency']!=='COP'||$quote['due_cents']<=0)throw new RuntimeException('Solo se agrupan saldos positivos en COP; los negocios cubiertos por cupón no requieren comprobante.');
        $quote['operation']=$quote['kind'];$quote['source_snapshot']=array_intersect_key($sub,array_flip(['plan_code','billing_frequency','status','starts_at','ends_at','reviewed_at','quoted_amount','currency']));
        if($sub['status']==='expired'&&$sub['plan_code']!=='free'){$quote['operation']='renewal';$quote['renewal_of_subscription_id']=(int)$sub['id'];}
        return ['business_id'=>$id,'source_request_id'=>(int)$sub['id'],'business_name'=>$business['display_name'],'place_name'=>$business['place_name'],'quote'=>$quote,'addon'=>$addon];
    }
    public function create(int $account,array $choices,string $requestKey):array
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',$requestKey))throw new RuntimeException('La solicitud venció; vuelve a seleccionar los negocios.');
        $choices=$this->normalize($choices);$hash=hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($account,$choices,$requestKey,$hash):array{
            if(!$this->read->account($account,true))throw new RuntimeException('Tu cuenta ya no está activa.');
            $existing=$this->read->request($account,$requestKey);if($existing){if(!hash_equals($existing['request_hash'],$hash))throw new RuntimeException('Esta solicitud ya se usó para otra selección.');return $existing;}
            $items=[];$total=0;
            foreach($choices as $choice){$item=$this->quote($account,$choice);$total+=$item['quote']['due_cents'];if($total>99999999999999)throw new RuntimeException('El total supera el importe permitido por operación.');$items[]=$item;}
            $order=$this->write->create($account,$requestKey,$hash,$total,'COP');foreach($items as $item)$this->write->addItem($order,$item);
            return $this->read->byId($order);
        });
    }
    public function get(int $account,string $publicId):array
    {
        $order=$this->read->order($publicId,$account);if(!$order)throw new RuntimeException('La orden no está disponible para tu cuenta.');
        $order['items']=$this->read->items((int)$order['id']);$order['evidence']=$this->read->evidence((int)$order['id']);return $order;
    }
    public function cancel(int $account,string $publicId):void
    {
        $this->transaction(function()use($account,$publicId):void{
            $order=$this->read->order($publicId,$account,true);if(!$order)throw new RuntimeException('Orden no disponible.');
            if($order['status']==='cancelled')return;
            if(!in_array($order['status'],['awaiting_evidence','rejected','clarification_requested'],true))throw new RuntimeException('La orden ya fue enviada o confirmada y no se puede cambiar.');
            foreach($this->read->items((int)$order['id']) as $item)$this->authorize($account,(int)$item['business_id'],true);
            $this->write->status((int)$order['id'],'cancelled');$this->write->release((int)$order['id']);
        });
    }
    public function submit(int $account,string $publicId,string $reference,array $upload,array $details):array
    {
        $file=null;
        try{return $this->transaction(function()use($account,$publicId,$reference,$upload,$details,&$file):array{
            $order=$this->read->order($publicId,$account,true);if(!$order)throw new RuntimeException('Orden no disponible.');
            if(!$this->read->account($account,true))throw new RuntimeException('Tu cuenta ya no está activa.');
            $items=$this->read->items((int)$order['id']);$this->validateTotal($order,$items);foreach($items as $item)$this->authorize($account,(int)$item['business_id'],true);
            if(in_array($order['status'],['under_review','approved'],true))return $order;
            if(!in_array($order['status'],['awaiting_evidence','rejected','clarification_requested'],true))throw new RuntimeException('Esta orden ya no admite comprobantes.');
            $reference=trim($reference);if(strlen($reference)>160)throw new RuntimeException('La referencia de pago es demasiado larga.');
            $details=BusinessPaymentEvidenceService::validateTransaction($reference,[...$details,'checkout_reference'=>$order['checkout_reference']]);
            if(SubscriptionChangeService::cents($details['paid_amount'])<(int)$order['total_cents'])throw new RuntimeException('El comprobante debe cubrir el total de los negocios seleccionados.');
            foreach($items as $item){
                $latest=$this->read->latestSubscription((int)$item['business_id'],true);
                $expected=(int)($item['subscription_id']?:$item['source_request_id']);
                if(!$latest||(int)$latest['id']!==$expected||!in_array($latest['status'],['pending','pending_publication','active','expired'],true))throw new RuntimeException('Una suscripción cambió. Contacta al equipo con la referencia de la orden antes de repetir el pago.');
                if($item['addon'])(new ExperienceOperatorAddon($this->pdo))->requireEligible((int)$item['business_id']);
                if(!$item['subscription_id']){
                    if(array_intersect_key($latest,$item['quote']['source_snapshot'])!=$item['quote']['source_snapshot'])throw new RuntimeException('La suscripción original cambió; solicita una revisión de esta orden.');
                    $offer=(new BusinessPromotions($this->pdo))->forSubscription((int)$item['source_request_id']);
                    if($offer)$subscription=(int)$item['source_request_id'];
                    else{$subscription=$this->write->createSubscription($item);(new SubscriptionChangeService($this->pdo))->capture($subscription,$item['quote']);(new ExperienceOperatorAddon($this->pdo))->attach($subscription,$item['addon']);}
                    $this->write->target((int)$item['id'],$subscription);
                }else $subscription=(int)$item['subscription_id'];
                $targets[]=$subscription;
            }
            if(!$this->storage)throw new LogicException('Manual payment requires evidence storage.');
            $file=$this->storage->store($upload);$this->write->attachEvidence($order,$targets[0],$file,$reference,$details);
            $this->write->status((int)$order['id'],'under_review');return $this->read->byId((int)$order['id']);
        });}catch(Throwable $e){if($file!==null)$this->storage->discard($file);throw $e;}
    }
    private function validateTotal(array $order,array $items):void
    {
        $sum=0;if(!$items)throw new RuntimeException('La orden no tiene negocios.');
        foreach($items as $item){if((int)$item['due_cents']<=0||(int)$item['due_cents']!==$item['quote']['due_cents']||$item['quote']['currency']!==$order['currency'])throw new RuntimeException('El desglose de la orden requiere revisión.');$sum+=(int)$item['due_cents'];}
        if($sum!==(int)$order['total_cents'])throw new RuntimeException('El total de la orden no coincide con su desglose.');
    }
    public function review(int $evidence,string $decision,string $note,string $actor,string $message=''):void
    {
        if(!in_array($decision,['approved','rejected','clarification_requested'],true))throw new RuntimeException('Selecciona una decisión válida.');
        $note=trim($note);$message=trim($message);if(strlen($note)>1000||strlen($message)>1000||trim($actor)===''||strlen($actor)>120)throw new RuntimeException('Revisa los datos de la revisión.');
        if($decision!=='approved'&&(mb_strlen($note)<8||mb_strlen($message)<8))throw new RuntimeException('Indica el motivo interno y el mensaje para la persona responsable.');
        $this->transaction(function()use($evidence,$decision,$note,$actor,$message):void{
            $found=$this->read->byEvidence($evidence);if(!$found)throw new RuntimeException('Comprobante de orden no disponible.');$order=$this->read->byId((int)$found['id'],true);
            $receipt=$this->read->evidence((int)$order['id']);if(!$receipt||(int)$receipt['id']!==$evidence)throw new RuntimeException('Hay un comprobante más reciente para esta orden.');
            if($order['status']===$decision&&$receipt['status']===$decision)return;
            if($order['status']!=='under_review'||$receipt['status']!=='pending')throw new RuntimeException('La orden ya fue revisada.');
            if(SubscriptionChangeService::cents($receipt['paid_amount'])<(int)$order['total_cents'])throw new RuntimeException('El pago no cubre el total de la orden.');
            $items=$this->read->items((int)$order['id']);$this->validateTotal($order,$items);
            foreach($items as $item){
                $sub=$this->read->subscription((int)$item['subscription_id'],true);
                if(!$sub||$sub['status']!=='pending'||$sub['plan_code']!==$item['quote']['plan_code']||$sub['billing_frequency']!==$item['quote']['billing_frequency']||(int)$sub['business_id']!==(int)$item['business_id']||$sub['currency']!==$order['currency']||SubscriptionChangeService::cents($sub['quoted_amount'])!==(int)$item['due_cents'])throw new RuntimeException('Una suscripción cambió; revisa la orden completa.');
                if($decision==='approved'){
                    if($item['addon'])(new ExperienceOperatorAddon($this->pdo))->requireEligible((int)$item['business_id']);
                    $this->write->confirm((int)$sub['id']);
                }
            }
            $this->write->review($evidence,$decision,$note,$actor,$message);$this->write->status((int)$order['id'],$decision);
            if($decision==='approved')$this->write->release((int)$order['id']);
        });
    }
}
