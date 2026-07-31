<?php

declare(strict_types=1);
require_once __DIR__ . '/initiative-common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
$userId = initiativeUserId(); initiativeVerifyCsrf();
$id = (int)($_POST['initiative_id'] ?? 0); $db = initiativeDb();
try {
    $db->beginTransaction();
    $s=$db->prepare("SELECT * FROM initiatives WHERE initiative_id=:id FOR UPDATE"); $s->execute(['id'=>$id]); $i=$s->fetch();
    if(!$i) throw new RuntimeException('Initiative not found.');
    initiativeRequireView($i,$userId);
    if((string)$i['status']!=='APPROVED') throw new RuntimeException('Only an approved request can be converted.');
    if(!initiativeUserHasRole($userId,'Initiative Approver')) throw new RuntimeException('You are not authorized to convert this request.');
    $code=$i['initiative_code'] ?: initiativeGenerateCode($db);
    $u=$db->prepare("UPDATE initiatives SET initiative_code=:code,status='ACTIVE',converted_at=CURRENT_TIMESTAMP,converted_by=:uid,updated_at=CURRENT_TIMESTAMP WHERE initiative_id=:id");
    $u->execute(['code'=>$code,'uid'=>$userId,'id'=>$id]);
    initiativeEvent($id,$userId,'CONVERTED',['initiative_code'=>$code]);
    initiativeNotify((int)$i['created_by'],$id,'CONVERTED','Initiative activated',"Your approved request is now {$code}.");
    $db->commit(); header('Location: initiative-view.php?id='.$id,true,303);
} catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); http_response_code(400); exit(initiativeH($e->getMessage())); }
