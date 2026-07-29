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
            $dbname = 'UOB_Partnership_and_Initiative';
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
    
    /**
     * Send real email using PHPMailer or mail() function
     */
    private function sendEmail($to, $subject, $message, $isHtml = true) {
        // ============================================
        // OPTION 1: Using PHPMailer (Recommended)
        // ============================================
        // First, check if PHPMailer is available
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // Server settings for UOB email (using SMTP)
                $mail->isSMTP();
                $mail->Host       = 'smtp.office365.com'; // UOB uses Office 365
                $mail->SMTPAuth   = true;
                $mail->Username   = '202208354@stu.uob.edu.bh'; // Your UOB email
                $mail->Password   = 'YOUR_UOB_EMAIL_PASSWORD'; // ⚠️ Replace with your actual password
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('202208354@stu.uob.edu.bh', 'UOB Partnerships & Sustainable Impact');
                $mail->addAddress($to);
                
                // Content
                $mail->isHTML($isHtml);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                $mail->AltBody = strip_tags($message);
                
                $mail->send();
                error_log("Email sent to: $to");
                return true;
                
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
                return false;
            }
        }
        
        // ============================================
        // OPTION 2: Using PHP mail() function (Fallback)
        // ============================================
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: 202208354@stu.uob.edu.bh\r\n";
        $headers .= "Reply-To: 202208354@stu.uob.edu.bh\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $result = mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log("Email sent via mail() to: $to");
        } else {
            error_log("Email via mail() failed for: $to");
        }
        
        return $result;
    }
    
    /**
     * Create email content with proper styling
     */
    private function buildEmailContent($title, $message, $actionUrl = null, $lang = 'ar') {
        $isArabic = ($lang === 'ar');
        $appName = $isArabic ? 'الشراكات والأثر المستدام – جامعة البحرين' : 'UOB Partnerships & Sustainable Impact';
        $buttonText = $isArabic ? 'عرض التفاصيل' : 'View Details';
        $footerText = $isArabic ? '© جامعة البحرين - جميع الحقوق محفوظة' : '© University of Bahrain - All rights reserved';
        
        $html = '
        <!DOCTYPE html>
        <html dir="' . ($isArabic ? 'rtl' : 'ltr') . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body {
                    font-family: "Cairo", Arial, sans-serif;
                    background-color: #f4f7fb;
                    margin: 0;
                    padding: 20px;
                    color: #0f172a;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
                    overflow: hidden;
                }
                .email-header {
                    background: #0b1f3a;
                    padding: 20px 30px;
                    text-align: center;
                }
                .email-header h1 {
                    color: #ffffff;
                    font-size: 20px;
                    font-weight: 700;
                    margin: 0;
                }
                .email-header .subtitle {
                    color: #c9a227;
                    font-size: 14px;
                    margin-top: 4px;
                }
                .email-body {
                    padding: 30px;
                }
                .email-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #0b1f3a;
                    margin: 0 0 12px 0;
                }
                .email-message {
                    color: #334155;
                    line-height: 1.8;
                    margin: 12px 0 20px 0;
                }
                .email-button {
                    display: inline-block;
                    background: #0b1f3a;
                    color: #ffffff !important;
                    padding: 12px 28px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                }
                .email-button:hover {
                    background: #102a4c;
                }
                .email-footer {
                    background: #f8fafc;
                    padding: 16px 30px;
                    text-align: center;
                    color: #64748b;
                    font-size: 12px;
                    border-top: 1px solid #e6ebf2;
                }
                .email-footer a {
                    color: #0b1f3a;
                    text-decoration: none;
                }
                .text-center {
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>🔔 ' . $appName . '</h1>
                    <div class="subtitle">' . ($isArabic ? 'نظام الإشعارات' : 'Notification System') . '</div>
                </div>
                <div class="email-body">
                    <div class="email-title">' . htmlspecialchars($title) . '</div>
                    <div class="email-message">' . nl2br(htmlspecialchars($message)) . '</div>';
        
        if ($actionUrl) {
            $html .= '
                    <div class="text-center">
                        <a href="' . htmlspecialchars($actionUrl) . '" class="email-button">' . $buttonText . '</a>
                    </div>';
        }
        
        $html .= '
                </div>
                <div class="email-footer">
                    ' . $footerText . '<br>
                    <a href="' . ($actionUrl ?: 'https://uob.edu.bh') . '">' . ($isArabic ? 'زيارة المنصة' : 'Visit Platform') . '</a>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    public function createNotification(array $data): ?int {
        if (!$this->db) return null;
        
        try {
            $userId = $data['user_id'] ?? null;
            if (!$userId && !empty($data['email'])) {
                $userId = $this->getUserIdByEmail($data['email']);
            }
            if (!$userId) return null;
            
            // Get user email for sending notifications
            $userEmail = null;
            if ($userId) {
                $stmt = $this->db->prepare("SELECT email FROM users WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $userEmail = $user['email'] ?? null;
            }
            
            // Set priority based on entity type
            $priority = $data['priority'] ?? 'NORMAL';
            $entityType = $data['entity_type'] ?? '';
            
            if (empty($data['priority'])) {
                if ($entityType === 'agreement' || $entityType === 'partnership') {
                    $priority = 'HIGH';
                } elseif ($entityType === 'initiative') {
                    $priority = 'LOW';
                } else {
                    $priority = 'NORMAL';
                }
            }
            
            $allowedPriorities = ['HIGH', 'MEDIUM', 'NORMAL', 'LOW'];
            if (!in_array($priority, $allowedPriorities)) {
                $priority = 'NORMAL';
            }
            
            $actionRequired = isset($data['action_required']) ? (bool)$data['action_required'] : true;
            
            $sql = "INSERT INTO notifications (
                        user_id, title_ar, title_en, 
                        message_ar, message_en,
                        entity_type, entity_id, entity_code,
                        priority, reminder_count, reminder_sent,
                        action_required, action_url,
                        is_read, is_archived, is_deleted
                    ) VALUES (
                        :user_id, :title_ar, :title_en, 
                        :message_ar, :message_en,
                        :entity_type, :entity_id, :entity_code,
                        :priority, 0, FALSE,
                        :action_required, :action_url,
                        FALSE, FALSE, FALSE
                    ) RETURNING notification_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':title_ar' => $data['title_ar'] ?? $data['title'] ?? '',
                ':title_en' => $data['title_en'] ?? $data['title'] ?? '',
                ':message_ar' => $data['message_ar'] ?? $data['message'] ?? '',
                ':message_en' => $data['message_en'] ?? $data['message'] ?? '',
                ':entity_type' => $entityType,
                ':entity_id' => $data['entity_id'] ?? null,
                ':entity_code' => $data['entity_code'] ?? null,
                ':priority' => $priority,
                ':action_required' => $actionRequired,
                ':action_url' => $data['action_url'] ?? null
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $notificationId = $result ? (int)$result['notification_id'] : null;
            
            // ✅ Send email if user email exists and send_email is true
            if ($notificationId && $userEmail && isset($data['send_email']) && $data['send_email'] === true) {
                $lang = $_SESSION['lang'] ?? 'ar';
                $isArabic = ($lang === 'ar');
                
                $title = $isArabic ? ($data['title_ar'] ?? $data['title'] ?? '') : ($data['title_en'] ?? $data['title'] ?? '');
                $message = $isArabic ? ($data['message_ar'] ?? $data['message'] ?? '') : ($data['message_en'] ?? $data['message'] ?? '');
                $actionUrl = $data['action_url'] ?? null;
                
                $emailContent = $this->buildEmailContent($title, $message, $actionUrl, $lang);
                $this->sendEmail($userEmail, $title, $emailContent);
            }
            
            return $notificationId;
            
        } catch (PDOException $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return null;
        }
    }
}  ?>