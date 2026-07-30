<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../helpers/ApiSession.php';
require_once __DIR__ . '/../../../config/database.php';

ApiSession::start();
function initiativeDb(): PDO { return Database::connect(); }
function initiativeUserId(): int {
    foreach (['user_id','auth_user_id','workspace_user_id'] as $k) if (!empty($_SESSION[$k])) return (int)$_SESSION[$k];
    if (!empty($_SESSION['user']['user_id'])) return (int)$_SESSION['user']['user_id'];
    http_response_code(401); exit('Authentication required.');
}
function initiativeH(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function initiativeCsrf(): string { if (empty($_SESSION['initiative_csrf'])) $_SESSION['initiative_csrf']=bin2hex(random_bytes(32)); return $_SESSION['initiative_csrf']; }
function initiativeVerifyCsrf(): void { if (!hash_equals($_SESSION['initiative_csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token.'); } }
function initiativeSnapshot(array $i): string { return json_encode($i, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); }
function initiativeLoad(int $id): array {
 $s=initiativeDb()->prepare("SELECT i.*, u.email AS creator_email FROM initiatives i JOIN users u ON u.user_id=i.created_by WHERE i.initiative_id=:id"); $s->execute(['id'=>$id]); $r=$s->fetch(); if(!$r){http_response_code(404);exit('Initiative not found.');} return $r;
}
function initiativeCanEdit(array $i,int $uid): bool { return (int)$i['created_by']===$uid && in_array($i['status'],['DRAFT','REVISION_REQUIRED'],true); }
function initiativeHeader(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=initiativeH($title)?></title><link rel="stylesheet" href="assets/initiative-style.css"></head><body><header><a href="initiative-dashboard.php">Initiatives</a><nav><a href="initiative-create.php">New initiative</a><a href="../initiative-hub.php">Workspace hub</a></nav></header><main><?php }
function initiativeFooter(): void { ?></main></body></html><?php }
