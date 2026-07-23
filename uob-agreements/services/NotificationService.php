<?php
// services/NotificationService.php
declare(strict_types=1);

class NotificationService {
    private $db;
    private $lang;
    
    public function __construct($dbConnection = null) {
        $this->db = $dbConnection;
        $this->lang = $_SESSION['lang'] ?? 'ar';
        
        if (!$this->db) {
            $this->db = $this->getDbConnection();
        }
    }
    
    public function getDbConnection() {
        try {
            $host = 'localhost';
            $port = '5432';
            $dbname = 'postgres';
            $user = 'postgres';
            $password = 'fatema_fruit_20&04';
            
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    public function getUserIdByEmail(string $email): ?int {
        if (!$this->db) return null;
        
        try {
            $sql = "SELECT user_id FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['user_id'] : null;
        } catch (PDOException $e) {
            error_log("Error getting user ID: " . $e->getMessage());
            return null;
        }
    }
    
    public function createNotification(array $data): ?int {
        if (!$this->db) return null;
        
        try {
            $userId = $data['user_id'] ?? null;
            if (!$userId && !empty($data['email'])) {
                $userId = $this->getUserIdByEmail($data['email']);
            }
            if (!$userId) return null;
            
            // Convert boolean values properly
            $actionRequired = isset($data['action_required']) ? (bool)$data['action_required'] : true;
            
            $sql = "INSERT INTO notifications (
                        user_id, notification_type,
                        title_ar, title_en, message_ar, message_en,
                        workflow_instance_id, workflow_step_id, workflow_history_id,
                        entity_type, entity_id, entity_code,
                        priority, action_required, action_url,
                        is_read, is_archived, is_deleted
                    ) VALUES (
                        :user_id, :notification_type,
                        :title_ar, :title_en, :message_ar, :message_en,
                        :workflow_instance_id, :workflow_step_id, :workflow_history_id,
                        :entity_type, :entity_id, :entity_code,
                        :priority, :action_required, :action_url,
                        false, false, false
                    ) RETURNING notification_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':notification_type' => $data['notification_type'] ?? 'SYSTEM',
                ':title_ar' => $data['title_ar'] ?? $data['title'] ?? '',
                ':title_en' => $data['title_en'] ?? $data['title'] ?? '',
                ':message_ar' => $data['message_ar'] ?? $data['message'] ?? '',
                ':message_en' => $data['message_en'] ?? $data['message'] ?? '',
                ':workflow_instance_id' => $data['workflow_instance_id'] ?? null,
                ':workflow_step_id' => $data['workflow_step_id'] ?? null,
                ':workflow_history_id' => $data['workflow_history_id'] ?? null,
                ':entity_type' => $data['entity_type'] ?? null,
                ':entity_id' => $data['entity_id'] ?? null,
                ':entity_code' => $data['entity_code'] ?? null,
                ':priority' => $data['priority'] ?? 'NORMAL',
                ':action_required' => $actionRequired,
                ':action_url' => $data['action_url'] ?? null
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['notification_id'] : null;
            
        } catch (PDOException $e) {
            error_log("Notification creation error: " . $e->getMessage());
            echo "❌ Database error: " . $e->getMessage() . "<br>";
            return null;
        }
    }
    
    public function createBulkNotifications(array $users, array $data): array {
        $notificationIds = [];
        foreach ($users as $user) {
            if (is_array($user)) {
                $data['user_id'] = $user['user_id'] ?? null;
                $data['email'] = $user['email'] ?? '';
            } else {
                $data['user_id'] = $user;
            }
            $id = $this->createNotification($data);
            if ($id) $notificationIds[] = $id;
        }
        return $notificationIds;
    }
    
    public function getUserNotifications(int $userId, bool $unreadOnly = true, int $limit = 50): array {
        if (!$this->db) return [];
        
        try {
            $sql = "SELECT 
                        n.*,
                        u.first_name || ' ' || u.last_name as sender_name
                    FROM notifications n
                    LEFT JOIN users u ON n.user_id = u.user_id
                    WHERE n.user_id = :user_id
                    AND n.is_deleted = FALSE";
            
            if ($unreadOnly) {
                $sql .= " AND n.is_read = FALSE";
            }
            
            $sql .= " ORDER BY n.priority DESC, n.created_at DESC LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            return [];
        }
    }
    
    public function markAsRead(int $notificationId, int $userId): bool {
        if (!$this->db) return false;
        
        try {
            $sql = "UPDATE notifications 
                    SET is_read = TRUE, read_at = CURRENT_TIMESTAMP
                    WHERE notification_id = :notification_id 
                    AND user_id = :user_id
                    AND is_read = FALSE
                    RETURNING notification_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    public function markAllAsRead(int $userId): int {
        if (!$this->db) return 0;
        
        try {
            $sql = "UPDATE notifications 
                    SET is_read = TRUE, read_at = CURRENT_TIMESTAMP
                    WHERE user_id = :user_id AND is_read = FALSE
                    RETURNING notification_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error marking all as read: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getUnreadCount(int $userId): int {
        if (!$this->db) return 0;
        
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE user_id = :user_id AND is_read = FALSE AND is_deleted = FALSE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
}
?>