<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function out($data,$status=200){http_response_code($status); echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;}
function sb($method,$url,$anon,$bearer,$body=null){
  $ch=curl_init($url);
  $h=['apikey: '.$anon,'Authorization: Bearer '.$bearer,'Content-Type: application/json','Accept: application/json'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>20]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $raw=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $decoded=json_decode($raw,true);
  return ['http_status'=>$status,'curl_error'=>$err?:null,'body'=>$decoded??$raw,'raw'=>$raw];
}

require_once __DIR__.'/lib/env.php';
loadEnv(__DIR__.'/.env');
$url=trim((string)(getenv('SUPABASE_URL')?:''));
$anon=trim((string)(getenv('SUPABASE_ANON_KEY')?:getenv('SUPABASE_KEY')?:''));
if($url===''||$anon==='') out(['success'=>false,'stage'=>'config','message'=>'SUPABASE_URL atau ANON KEY tidak tersedia.'],500);

$headers=function_exists('getallheaders')?getallheaders():[];
$auth=''; foreach($headers as $k=>$v){if(strtolower($k)==='authorization'){$auth=trim($v);break;}}
if(!preg_match('/^Bearer\s+(.+)$/i',$auth,$m)) out(['success'=>false,'stage'=>'auth_header','message'=>'Authorization Bearer tidak ditemukan. Login Admin Area terlebih dahulu.'],401);
$bearer=trim($m[1]);

$orderId='';
$input=json_decode(file_get_contents('php://input'),true);
if(is_array($input)) $orderId=trim((string)($input['order_id']??''));
if($orderId==='' && isset($_GET['order_id'])) $orderId=trim((string)$_GET['order_id']);
if($orderId==='') out(['success'=>false,'stage'=>'input','message'=>'order_id wajib diisi.'],400);

$result=[];
$result['auth_user']=sb('GET',$url.'/auth/v1/user',$anon,$bearer);
if($result['auth_user']['http_status']<200||$result['auth_user']['http_status']>=300){out(['success'=>false,'stage'=>'auth_user','order_id'=>$orderId,'result'=>$result],401);}
$user=$result['auth_user']['body'];
$userId=is_array($user)?($user['id']??''):'';
if($userId==='') out(['success'=>false,'stage'=>'auth_user','message'=>'Supabase tidak mengembalikan user id.','result'=>$result],401);

$result['profile']=sb('GET',$url.'/rest/v1/profiles?select=id,role,full_name&id=eq.'.rawurlencode($userId).'&limit=1',$anon,$bearer);
$result['order']=sb('GET',$url.'/rest/v1/orders?select=id,order_number,status,client_id,package_id&id=eq.'.rawurlencode($orderId).'&limit=1',$anon,$bearer);

$orderRow=null;
if(is_array($result['order']['body']) && isset($result['order']['body'][0])) $orderRow=$result['order']['body'][0];
if($orderRow){
  $result['client']=sb('GET',$url.'/rest/v1/clients?select=id,client_code,full_name,business_name,email,whatsapp&id=eq.'.rawurlencode($orderRow['client_id']).'&limit=1',$anon,$bearer);
  $result['package']=sb('GET',$url.'/rest/v1/packages?select=id,code,name,active,is_active&id=eq.'.rawurlencode($orderRow['package_id']).'&limit=1',$anon,$bearer);
  $result['brief']=sb('GET',$url.'/rest/v1/website_briefs?select=id,order_id,status,brief_token_hash,brief_token_expires_at,created_at,updated_at&order_id=eq.'.rawurlencode($orderId).'&order=created_at.desc&limit=1',$anon,$bearer);
}

// Also test the same RPC used by the production endpoint, using a deterministic fake token hash.
$tokenHash=hash('sha256','diagnostic-'.microtime(true).'-'.bin2hex(random_bytes(8)));
$expires=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->modify('+14 days')->format(DateTimeInterface::ATOM);
$result['admin_prepare_brief']=sb('POST',$url.'/rest/v1/rpc/admin_prepare_brief',$anon,$bearer,[
  'p_order_id'=>$orderId,
  'p_token_hash'=>$tokenHash,
  'p_token_expires_at'=>$expires
]);

$rpc=$result['admin_prepare_brief'];
$rpcBody=$rpc['body'];
$rpcRow=(is_array($rpcBody)&&isset($rpcBody[0]))?$rpcBody[0]:$rpcBody;
$success=is_array($rpcRow)&&(($rpcRow['success']??false)===true);

out([
  'success'=>$success,
  'stage'=>$success?'admin_prepare_brief_ok':'admin_prepare_brief_result',
  'order_id'=>$orderId,
  'diagnostic_note'=>'Temporary diagnostic. The admin_prepare_brief RPC was called with the current Admin JWT. If success=true, a brief/token may have been created or refreshed; do not reuse this diagnostic token.',
  'result'=>$result
],$success?200:500);
