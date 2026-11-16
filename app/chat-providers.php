<?php
declare(strict_types=1);

function chat_provider_config(): array {
    $providers=[];
    if ($key=trim(getenv('GROQ_API_KEY') ?: '')) $providers[]=['name'=>'groq','key'=>$key,'url'=>'https://api.groq.com/openai/v1/chat/completions','model'=>'openai/gpt-oss-120b'];
    $key=trim(getenv('CLOUDFLARE_API_TOKEN') ?: '');
    $account=trim(getenv('CLOUDFLARE_ACCOUNT_ID') ?: '');
    if ($key && preg_match('/^[a-f0-9]{32}$/i',$account)) $providers[]=['name'=>'cloudflare','key'=>$key,'url'=>'https://api.cloudflare.com/client/v4/accounts/'.$account.'/ai/v1/chat/completions','model'=>'@cf/meta/llama-3.1-8b-instruct-fp8-fast'];
    return $providers;
}

/** No redirects, no raw provider errors or credentials in logs. */
function chat_provider_transport(array $provider, array $payload): array {
    if (!function_exists('curl_init')) return ['status'=>0,'body'=>[]];
    $ch=curl_init($provider['url']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$provider['key'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return ['status'=>$status,'body'=>is_string($raw)?(json_decode($raw,true) ?: []):[]];
}

/** One attempt per configured provider; never reroute a safety refusal. */
function chat_provider_answer(array $messages, ?array $providers=null, ?callable $transport=null): array {
    $start=microtime(true);$transport=$transport ?? 'chat_provider_transport';
    foreach ($providers ?? chat_provider_config() as $p) {
        $payload=['model'=>$p['model'],'messages'=>$messages,'temperature'=>0.4,'stream'=>false];
        if ($p['name']==='groq') { $payload['max_completion_tokens']=2048;$payload['reasoning_effort']='low'; }
        else $payload['max_tokens']=600;
        $r=$transport($p,$payload);$status=(int)$r['status'];
        $body=is_array($r['body'] ?? null)?$r['body']:[];
        $choice=$body['choices'][0] ?? [];$message=$choice['message'] ?? [];
        if (!empty($message['refusal']) || ($choice['finish_reason'] ?? '')==='content_filter') {
            return ['message'=>'Tôi chưa thể hỗ trợ yêu cầu này. Bạn có thể đặt lại câu hỏi theo hướng tìm hiểu nghề hoặc cách xử lý an toàn.','model'=>$p['name'].'/'.$p['model'],'latency_ms'=>(int)((microtime(true)-$start)*1000)];
        }
        if ($status>=200 && $status<300) {
            $text=$message['content'] ?? null;
            if (is_string($text) && trim($text)!=='') return ['message'=>mb_substr(trim($text),0,8000),'model'=>$p['name'].'/'.$p['model'],'latency_ms'=>(int)((microtime(true)-$start)*1000)];
            break; // Invalid success response needs diagnosis, not blind routing.
        }
        if (!in_array($status,[0,401,402,408,429],true) && $status<500) break;
    }
    return ['message'=>'AI hiện chưa khả dụng hoặc đã hết hạn mức. Câu hỏi đã được lưu; bạn có thể thử lại sau.','model'=>'unavailable','latency_ms'=>(int)((microtime(true)-$start)*1000)];
}
