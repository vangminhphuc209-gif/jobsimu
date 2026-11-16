<?php
declare(strict_types=1);
require __DIR__.'/../app/chat-providers.php';
function check(bool $v):void {if(!$v)throw new RuntimeException('Provider test failed');}
$p=[['name'=>'groq','model'=>'test'],['name'=>'cloudflare','model'=>'test']];
$success=['status'=>200,'body'=>['choices'=>[['message'=>['content'=>'Xin chào'],'finish_reason'=>'stop']]]];
foreach ([429,500,0,401] as $status) {
 $calls=0;$r=chat_provider_answer([], $p, function($p,$payload)use(&$calls,$status,$success){$calls++;return $calls===1?['status'=>$status,'body'=>[]]:$success;});
 check($calls===2 && $r['model']==='cloudflare/test' && $r['message']==='Xin chào');
}
foreach ([['status'=>400,'body'=>[]],['status'=>200,'body'=>['choices'=>[['message'=>['refusal'=>'No']]]]]] as $first) {
 $calls=0;chat_provider_answer([],$p,function()use(&$calls,$first){$calls++;return $first;});check($calls===1);
}
$calls=0;$r=chat_provider_answer([],$p,function()use(&$calls,$success){$calls++;return $success;});check($calls===1 && $r['model']==='groq/test');
$r=chat_provider_answer([],$p,fn()=>['status'=>429,'body'=>[]]);check($r['model']==='unavailable');
echo "Provider tests passed: success, quota, timeout, auth, server errors, refusal, bad request and exhausted providers.\n";
