<?php

declare(strict_types=1);
require_once __DIR__ . '/initiative-common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
$userId = initiativeUserId();
initiativeVerifyCsrf();
$id = (int) ($_POST['initiative_id'] ?? 0);
$initiative = initiativeLoad($id);
if (!initiativeCanManageParticipants($initiative, $userId)) { http_response_code(403); exit('Not allowed.'); }
$action = (string) ($_POST['action'] ?? '');
$participantId = (int) ($_POST['participant_user_id'] ?? 0);
$role = trim((string) ($_POST['participant_role'] ?? 'MEMBER')) ?: 'MEMBER';
$db = initiativeDb();
try {
    $db->beginTransaction();
    if ($participantId <= 0 || $participantId === $userId) { throw new RuntimeException('Choose a valid participant.'); }
    if ($action === 'add') {
        $s = $db->prepare("INSERT INTO initiative_participants (initiative_id,user_id,participant_role,added_by) VALUES (:id,:uid,:role,:by) ON CONFLICT (initiative_id,user_id) DO UPDATE SET participant_role=EXCLUDED.participant_role, added_by=EXCLUDED.added_by");
        $s->execute(['id'=>$id,'uid'=>$participantId,'role'=>$role,'by'=>$userId]);
        initiativeEvent($id,$userId,'PARTICIPANT_ADDED',['participant_user_id'=>$participantId,'role'=>$role]);
        initiativeNotify($participantId,$id,'PARTICIPANT_ADDED','Added to an initiative',(string)$initiative['title']);
    } elseif ($action === 'remove') {
        $s = $db->prepare("DELETE FROM initiative_participants WHERE initiative_id=:id AND user_id=:uid");
        $s->execute(['id'=>$id,'uid'=>$participantId]);
        initiativeEvent($id,$userId,'PARTICIPANT_REMOVED',['participant_user_id'=>$participantId]);
    } else { throw new RuntimeException('Unknown participant action.'); }
    $db->commit();
    header('Location: initiative-view.php?id='.$id, true, 303);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(400); exit(initiativeH($e->getMessage()));
}
