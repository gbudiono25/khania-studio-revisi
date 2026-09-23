<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/../lib/SupabaseClient.php';
use KhaniaStudio\SupabaseClient;
$token=trim((string)($_GET['brief_token']??''));
if($token===''||strlen($token)<40){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Token Client Brief tidak valid.']);exit;}
$s=new SupabaseClient();
if(!$s->isConfigured()){http_response_code(500);echo json_encode(['success'=>false,'message'=>'Konfigurasi database belum tersedia.']);exit;}
$hash=hash('sha256',$token);
$r=$s->rpcPostDetailed('get_brief_context',['p_token_hash'=>$hash]);
$row=(is_array($r['body'])&&isset($r['body'][0]))?$r['body'][0]:$r['body'];
if($r['status']<200||$r['status']>=300||!is_array($row)||($row['success']??false)!==true){
  http_response_code(410);
  echo json_encode(['success'=>false,'message'=>is_array($row)?($row['message']??'Link Client Brief tidak tersedia.'): 'Link Client Brief tidak tersedia.'],JSON_UNESCAPED_UNICODE);
  exit;
}
echo json_encode(['success'=>true,'data'=>$row],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
