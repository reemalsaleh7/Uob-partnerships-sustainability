<?php

declare(strict_types=1);
require_once __DIR__.'/initiative-common.php';
require_once __DIR__.'/../includes/layout.php';
$uid=initiativeUserId(); $db=initiativeDb();
if(isset($_GET['read'])){$s=$db->prepare("UPDATE initiative_notifications SET is_read=TRUE,read_at=CURRENT_TIMESTAMP WHERE initiative_notification_id=:id AND user_id=:uid");$s->execute(['id'=>(int)$_GET['read'],'uid'=>$uid]);}
$s=$db->prepare("SELECT n.*,i.title AS initiative_title FROM initiative_notifications n LEFT JOIN initiatives i ON i.initiative_id=n.initiative_id WHERE n.user_id=:uid ORDER BY n.created_at DESC LIMIT 100");$s->execute(['uid'=>$uid]);$rows=$s->fetchAll();
workspaceHeader('Initiative notifications','initiatives');?>
<section class="workspace-page-header"><div><p class="eyebrow mb-2">Initiatives</p><h1 class="h2 mb-2">Notifications</h1><p class="text-secondary mb-0">Updates about your requests, reviews, and decisions.</p></div></section>
<section class="workspace-card"><?php if(!$rows):?><div class="form-section text-center py-5 text-secondary">No notifications yet.</div><?php else:?><div class="list-group list-group-flush"><?php foreach($rows as $n):?><a class="list-group-item list-group-item-action px-4 py-3<?= $n['is_read']?'':' fw-semibold'?>" href="initiative-module/notifications.php?read=<?=(int)$n['initiative_notification_id']?>"><div class="d-flex justify-content-between gap-3"><span><?=initiativeH($n['title'])?></span><small class="text-secondary"><?=initiativeH((string)$n['created_at'])?></small></div><small class="text-secondary"><?=initiativeH($n['message'])?></small></a><?php endforeach;?></div><?php endif;?></section><?php workspaceFooter();?>
