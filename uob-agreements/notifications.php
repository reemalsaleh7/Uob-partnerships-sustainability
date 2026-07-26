<?php
// notifications.php - Database Version
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/NotificationService.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
    header("Location: login.php?to=notifications.php");
    exit;
}

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$pageTitle = $isArabic ? "الإشعارات" : "Notifications";
$hidePageHeader = true;
$mainContainer = false;

$notificationService = new NotificationService();
$email = $_SESSION['user_email'];
$userId = $notificationService->getUserIdByEmail($email);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notificationId = $_POST['notification_id'] ?? 0;
    
    if ($action === 'mark_read' && $notificationId) {
        $notificationService->markAsRead($notificationId, $userId);
        header("Location: notifications.php?lang=" . urlencode($lang));
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $notificationService->markAllAsRead($userId);
        header("Location: notifications.php?lang=" . urlencode($lang));
        exit;
    }
}

// Get notifications
$unreadOnly = isset($_GET['filter']) && $_GET['filter'] === 'unread';
$notifications = $notificationService->getUserNotifications($userId, $unreadOnly, 100);
$unreadCount = $notificationService->getUnreadCount($userId);

require_once __DIR__ . '/header.php';
?>

<style>
.notification-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}
.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}
.notification-header h1 {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}
.notification-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn-secondary {
    background: #e8e8e8;
    color: #333;
}
.btn-secondary:hover {
    background: #d5d5d5;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-success {
    background: #27ae60;
    color: white;
}
.btn-success:hover {
    background: #229954;
}
.btn-sm {
    padding: 4px 12px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.notification-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.notification-item {
    background: white;
    padding: 16px 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
    border-left: 4px solid transparent;
    transition: all 0.3s;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}
.notification-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.notification-item.unread {
    background: #f8f9ff;
    border-left-color: #3498db;
}
.notification-item.read {
    opacity: 0.75;
}
.notification-content {
    flex: 1;
    min-width: 0;
}
.notification-title {
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 3px;
    color: #1a1a1a;
}
.notification-message {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
    line-height: 1.5;
}
.notification-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: #999;
}
.notification-meta .priority {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}
.priority-high {
    background: #fce4ec;
    color: #c62828;
}
.priority-medium {
    background: #fff3e0;
    color: #ef6c00;
}
.notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.empty-state i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 15px;
}
.empty-state h3 {
    color: #555;
    margin-bottom: 5px;
}
.badge {
    background: #e74c3c;
    color: white;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 12px;
}
.badge-primary {
    background: #3498db;
}
@media (max-width: 768px) {
    .notification-header {
        flex-direction: column;
        align-items: stretch;
    }
    .notification-actions {
        flex-wrap: wrap;
    }
    .notification-item {
        flex-direction: column;
    }
}
</style>

<div class="notification-page">
    <div class="notification-header">
        <h1>
            🔔 <?= $isArabic ? 'الإشعارات' : 'Notifications' ?>
            <span class="badge badge-primary" style="font-size:14px; margin-left:8px;">
                <?= $unreadCount ?> <?= $isArabic ? 'غير مقروء' : 'unread' ?>
            </span>
        </h1>
        <div class="notification-actions">
            <a href="?filter=<?= $unreadOnly ? 'all' : 'unread' ?>&lang=<?= urlencode($lang) ?>" class="btn btn-secondary">
                <?= $unreadOnly ? ($isArabic ? '📋 عرض الكل' : '📋 Show All') : ($isArabic ? '📌 غير المقروءة' : '📌 Unread Only') ?>
            </a>
            <?php if ($unreadCount > 0): ?>
                <form method="post" style="display:inline;">
                    <button type="submit" name="action" value="mark_all_read" class="btn btn-primary">
                        ✅ <?= $isArabic ? 'تحديد الكل كمقروء' : 'Mark All Read' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3><?= $isArabic ? 'لا توجد إشعارات' : 'No notifications' ?></h3>
                <p><?= $isArabic ? 'ستظهر الإشعارات هنا عند توفرها' : 'Notifications will appear here when available' ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <?php $isUnread = !$notif['is_read']; ?>
                <div class="notification-item <?= $isUnread ? 'unread' : 'read' ?>">
                    <div class="notification-content">
                        <div class="notification-title">
                            <?= h($isArabic ? ($notif['title_ar'] ?? $notif['title']) : ($notif['title_en'] ?? $notif['title'])) ?>
                            <?php if ($isUnread): ?>
                                <span class="badge" style="font-size:10px;"><?= $isArabic ? 'جديد' : 'New' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-message">
                            <?= nl2br(h($isArabic ? ($notif['message_ar'] ?? $notif['message']) : ($notif['message_en'] ?? $notif['message']))) ?>
                        </div>
                        <div class="notification-meta">
                            <span><?= $notif['created_at'] ?></span>
                        </div>
                        <?php if ($isUnread): ?>
                            <div class="notification-actions">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                    <button type="submit" name="action" value="mark_read" class="btn btn-success btn-sm">
                                        ✅ <?= $isArabic ? 'تحديد كمقروءة' : 'Mark as Read' ?>
                                    </button>
                                </form>
                                <?php if ($notif['action_url']): ?>
                                    <a href="<?= h($notif['action_url']) ?>" class="btn btn-primary btn-sm">
                                        👉 <?= $isArabic ? 'عرض التفاصيل' : 'View Details' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>