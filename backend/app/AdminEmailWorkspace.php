<?php
declare(strict_types=1);
/** Private template/message files; no second contact registry or business schema. */
final class AdminEmailWorkspace
{
    public function __construct(private string $directory){
        if(is_link($directory))throw new RuntimeException('Directorio privado no válido.');
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('No se pudo crear el archivo privado de correos.');
    }
    private function path(string $kind,string $id):string{
        if(!in_array($kind,['template','message'],true)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Referencia de correo no válida.');
        $path=$this->directory.'/'.$kind.'-'.$id.'.json';if(is_link($path))throw new RuntimeException('Referencia privada no válida.');return $path;
    }
    public function get(string $kind,string $id):array{
        $path=$this->path($kind,$id);if(!is_file($path))throw new RuntimeException('No se encontró el registro.');return json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    }
    private function write(string $kind,string $id,array $data):void{
        $path=$this->path($kind,$id);$tmp=tempnam($this->directory,'draft-');
        if($tmp===false)throw new RuntimeException('No se pudo guardar el correo.');
        try{chmod($tmp,0600);if(file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($tmp,$path))throw new RuntimeException('No se pudo guardar el correo.');}finally{if(is_file($tmp))unlink($tmp);}
    }
    public function all(string $kind):array{
        if(!in_array($kind,['template','message'],true))throw new RuntimeException('Tipo no válido.');$rows=[];
        foreach(glob($this->directory.'/'.$kind.'-*.json')?:[] as $path){$id=substr(basename($path),strlen($kind)+1,32);$rows[]=$this->get($kind,$id);}
        usort($rows,static fn($a,$b)=>strcmp($b['updated_at'],$a['updated_at']));return $rows;
    }
    public static function cleanHtml(string $html):string{
        if(strlen($html)>60000)throw new RuntimeException('El HTML supera 60 KB.');
        if(!class_exists('DOMDocument'))throw new RuntimeException('El servidor requiere la extensión DOM para validar plantillas.');
        $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
        try{$dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);}
        finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        $allowed=['p','br','h1','h2','h3','strong','b','em','i','ul','ol','li','a','blockquote','hr','table','thead','tbody','tr','th','td','div','span'];
        $walk=function(DOMNode $node)use(&$walk,$allowed):void{
            foreach(iterator_to_array($node->childNodes) as $child){
                if($child instanceof DOMElement){
                    if(!in_array(strtolower($child->tagName),$allowed,true)){$node->removeChild($child);continue;}
                    foreach(iterator_to_array($child->attributes) as $attr){
                        $ok=$child->tagName==='a'&&$attr->name==='href'&&(preg_match('~^https://[^\s<>]+$~iD',$attr->value)||preg_match('/^mailto:[^\s<>]+@[^\s<>]+$/iD',$attr->value));
                        if(!$ok)$child->removeAttribute($attr->name);
                    }
                    $walk($child);
                }elseif(!($child instanceof DOMText))$node->removeChild($child);
            }
        };
        $body=$dom->getElementsByTagName('body')->item(0);$walk($body);$out='';foreach($body->childNodes as $child)$out.=$dom->saveHTML($child);return $out;
    }
    public function saveTemplate(array $input,string $actor):string{
        $id=(string)($input['template_id']??'');$old=$id!==''?$this->get('template',$id):null;$id=$id?:bin2hex(random_bytes(16));
        $name=trim((string)($input['name']??''));$subject=trim((string)($input['subject']??''));$html=self::cleanHtml((string)($input['html']??''));
        if($name===''||strlen($name)>180||$subject===''||strlen($subject)>250||strpbrk($subject,"\r\n")!==false||trim(strip_tags($html))==='')throw new RuntimeException('Completa nombre, asunto y contenido válidos.');
        self::render($subject,$html,[]);
        $this->write('template',$id,['id'=>$id,'name'=>$name,'subject'=>$subject,'html'=>$html,'revision'=>($old['revision']??0)+1,'actor'=>$actor,'updated_at'=>gmdate('c')]);return $id;
    }
    public static function render(string $subject,string $html,array $values):array{
        $keys=['business_name','recipient_name','recipient_email','destination_name','subscription_name','expiration_date'];
        foreach([$subject,$html] as $text){preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/',$text,$matches);foreach($matches[1] as $key)if(!in_array($key,$keys,true))throw new RuntimeException('Variable no admitida: '.$key);}
        $replace=static function(string $text,bool $escape)use($values):string{return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',static fn($m)=>$escape?htmlspecialchars((string)($values[$m[1]]??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):(string)($values[$m[1]]??''),$text);};
        $rendered=$replace($subject,false);if(strpbrk($rendered,"\r\n")!==false)throw new RuntimeException('Asunto no válido.');
        $content=self::cleanHtml($replace($html,true));return ['subject'=>$rendered,'html'=>$content,'text'=>html_entity_decode(strip_tags(str_replace(['</p>','<br>','<br/>','<br />','</li>'],"\n",$content)),ENT_QUOTES|ENT_HTML5,'UTF-8')];
    }
    public function prepare(string $templateId,string $email,array $values,array $sender,string $actor,?int $businessId=null,array $overrides=[]):string{
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Selecciona un correo válido.');
        $template=$this->get('template',$templateId);$values['recipient_email']=$email;$subject=trim((string)($overrides['subject']??''))?:$template['subject'];$html=trim((string)($overrides['html']??''))?:$template['html'];if(strlen($subject)>250||strlen($html)>60000)throw new RuntimeException('El contenido supera el tamaño permitido.');$content=self::render($subject,$html,$values);$id=bin2hex(random_bytes(16));
        $this->write('message',$id,['id'=>$id,'business_id'=>$businessId,'email'=>$email,'values'=>$values,'template_id'=>$templateId,'template_revision'=>$template['revision'],'content'=>$content,'sender'=>$sender,'actor'=>$actor,'purpose'=>'requested_reply_or_service','status'=>'draft','updated_at'=>gmdate('c')]);return $id;
    }
    public function send(string $id,array $settings,string $actor,?Closure $transport=null):array{
        $lock=fopen($this->directory.'/send.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo reservar el envío.');
        try{
            $m=$this->get('message',$id);if($m['status']!=='draft')throw new RuntimeException('Este correo ya fue procesado. No se reenvía automáticamente.');
            if(empty($settings['enabled'])||empty($settings['api_key'])||!filter_var($settings['sender_email']??'',FILTER_VALIDATE_EMAIL)||empty($settings['sender_name']))throw new RuntimeException('Configura y habilita Brevo desde Sistema antes de enviar.');
            $sender=['sender_email'=>$settings['sender_email'],'sender_name'=>$settings['sender_name']];
            if($m['sender']!==$sender)throw new RuntimeException('El remitente cambió. Prepara y revisa otra vista previa.');
            if(!$transport&&!function_exists('curl_exec'))throw new RuntimeException('El envío no está disponible en esta instancia.');
            $m['status']='sending';$m['sent_by']=$actor;$m['updated_at']=gmdate('c');$this->write('message',$id,$m);
            try{
                $payload=['sender'=>['email'=>$sender['sender_email'],'name'=>$sender['sender_name']],'to'=>[['email'=>$m['email']]],'subject'=>$m['content']['subject'],'htmlContent'=>$m['content']['html'],'textContent'=>$m['content']['text']];
                if($transport)$response=$transport($payload);
                else{$curl=curl_init('https://api.brevo.com/v3/smtp/email');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['api-key: '.$settings['api_key'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);$body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);$response=['status'=>$code,'body'=>is_string($body)?json_decode($body,true):null];}
                $code=(int)($response['status']??0);$m['provider_http_status']=$code;$messageId=$response['body']['messageId']??null;
                $m['status']=$code===201&&is_string($messageId)&&$messageId!==''?'accepted':($code>=400&&$code<500&&$code!==408?'rejected':'uncertain');
                if($m['status']==='accepted')$m['provider_message_id']=$messageId;
            }catch(Throwable){$m['status']='uncertain';}
            $m['updated_at']=gmdate('c');$this->write('message',$id,$m);return $m;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
