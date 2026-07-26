<?php
// services/WorkflowService.php
declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

class WorkflowService {
    private $db;
    private $notificationService;
    private $lang;
    
    // Initiative Approval Hierarchy
    private $initiativeHierarchy = [
        1 => ['name_ar' => 'دكتور', 'name_en' => 'Doctor', 'level' => 'DOCTOR'],
        2 => ['name_ar' => 'رئيس القسم', 'name_en' => 'Head of Department', 'level' => 'HOD'],
        3 => ['name_ar' => 'عميد الكلية', 'name_en' => 'Dean', 'level' => 'DEAN'],
        4 => ['name_ar' => 'مكتب نائب الرئيس', 'name_en' => 'VP Office', 'level' => 'VP'],
        5 => ['name_ar' => 'مكتب الرئيس', 'name_en' => 'President Office', 'level' => 'PRESIDENT']
    ];
    
    // Partnership/Agreement Approval Hierarchy
    private $partnershipHierarchy = [
        1 => ['name_ar' => 'عميد الكلية', 'name_en' => 'Dean', 'level' => 'DEAN'],
        2 => ['name_ar' => 'مكتب نائب الرئيس', 'name_en' => 'VP Office', 'level' => 'VP'],
        3 => ['name_ar' => 'اللجنة القانونية', 'name_en' => 'Law Committee', 'level' => 'LAW'],
        4 => ['name_ar' => 'اللجنة المالية', 'name_en' => 'Finance Committee', 'level' => 'FINANCE'],
        5 => ['name_ar' => 'مكتب نائب الرئيس', 'name_en' => 'VP Office', 'level' => 'VP'],
        6 => ['name_ar' => 'مكتب الرئيس', 'name_en' => 'President Office', 'level' => 'PRESIDENT']
    ];
    
    // Role to level mapping for finding next approver
    private $roleLevelMap = [
        'DOCTOR' => 1,
        'HOD' => 2,
        'DEAN' => 3,
        'VP' => 4,
        'PRESIDENT' => 5,
        'LAW' => 3,
        'FINANCE' => 4
    ];
    
    public function __construct($dbConnection = null) {
        global $db;
        $this->db = $dbConnection ?? $db;
        $this->lang = $_SESSION['lang'] ?? 'ar';
        $this->notificationService = new NotificationService($this->db);
        
        if (!$this->db) {
            $this->db = $this->getDbConnection();
        }
    }
    
    private function getDbConnection() {
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
    
    /**
     * Submit an initiative for approval
     * Hierarchy: Doctor → Head of Dept → Dean → VP → President
     */
    public function submitInitiative(int $initiativeId, int $userId): bool {
        if (!$this->db) return false;
        try {
            error_log("=== submitInitiative START ===");
            error_log("Initiative ID: " . $initiativeId);
            error_log("User ID: " . $userId);
        
            $this->db->beginTransaction();
        
            // Get initiative details
            $initiative = $this->getInitiative($initiativeId);
            if (!$initiative) {
                error_log("ERROR: Initiative not found: " . $initiativeId);
                throw new Exception("Initiative not found");
            }
            error_log("Initiative found: " . $initiative['title']);
        
            // Get the user's current level (role)
            $userLevel = $this->getUserLevel($userId);
            error_log("User level: " . $userLevel);
        
            $currentStep = $this->roleLevelMap[$userLevel] ?? 1;
            error_log("Current step: " . $currentStep);
        
            // Get the next approver based on hierarchy
            $nextStep = $currentStep + 1;
            error_log("Next step: " . $nextStep);
        
            $nextLevelName = $this->initiativeHierarchy[$nextStep]['name_ar'] ?? '';
            $nextLevelCode = $this->initiativeHierarchy[$nextStep]['level'] ?? '';
            error_log("Next level: " . $nextLevelCode . " - " . $nextLevelName);
        
            // Get approvers for the next level
            $nextApprovers = $this->getApproversByLevel($nextLevelCode, 'initiative');
            error_log("Next approvers: " . count($nextApprovers));
            error_log("Next approvers: " . print_r($nextApprovers, true));
        
            // Create workflow instance if not exists
            error_log("Creating workflow instance...");
            $workflowInstanceId = $this->createWorkflowInstance(
                'initiative', 
                $initiativeId, 
                $userId,
                $currentStep
            );
            error_log("Workflow instance ID: " . $workflowInstanceId);
        
            // Create workflow step for this submission
            error_log("Creating workflow step...");
            $stepId = $this->createWorkflowStep(
                $workflowInstanceId,
                $currentStep,
                $userId,
                'SUBMITTED'
            );
            error_log("Workflow step ID: " . $stepId);
        
            // Record history
            $this->addHistory(
                $workflowInstanceId,
                $stepId,
                'SUBMITTED',
                $userId,
                "تم تقديم المبادرة للموافقة"
            );
        
            // If at President level, final approval
            if ($nextStep > 5) {
                error_log("Final step - completing workflow...");
                $this->completeWorkflow($workflowInstanceId, $userId);
                $this->notifyWorkflowComplete($workflowInstanceId, 'initiative', $initiative);
            } else {
                // Notify next approvers
                error_log("Notifying next approvers...");
                $this->notifyApprovers(
                    $workflowInstanceId,
                    $stepId,
                    $nextApprovers,
                    $this->initiativeHierarchy[$nextStep],
                    $initiative,
                    'initiative',
                    $userId
                );
                
                // Notify the submitter
                $this->notifySubmitter(
                    $workflowInstanceId,
                    $userId,
                    $initiative,
                    'initiative',
                    $this->initiativeHierarchy[$nextStep]['name_ar']
                );
            }
            
            // Update initiative status
            $this->updateInitiativeStatus($initiativeId, 'UNDER_REVIEW');
            
            $this->db->commit();
            error_log("=== submitInitiative SUCCESS ===");
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("=== submitInitiative ERROR: " . $e->getMessage() . " ===");
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
        }
    
    /**
     * Submit a partnership/agreement for approval
     * Hierarchy: Dean → VP → Law → Finance → VP → President
     */
    public function submitPartnership(int $agreementId, int $userId): bool {
        if (!$this->db) return false;
        
        try {
            $this->db->beginTransaction();
            
            // Get agreement details
            $agreement = $this->getAgreement($agreementId);
            if (!$agreement) {
                throw new Exception("Agreement not found");
            }
            
            // Partnership always starts at Dean level (step 1)
            $currentStep = 1;
            $nextStep = 2;
            
            // Get next approvers (VP)
            $nextApprovers = $this->getApproversByLevel('VP', 'partnership');
            
            // Create workflow instance
            $workflowInstanceId = $this->createWorkflowInstance(
                'partnership', 
                $agreementId, 
                $userId,
                $currentStep
            );
            
            // Create workflow step
            $stepId = $this->createWorkflowStep(
                $workflowInstanceId,
                $currentStep,
                $userId,
                'SUBMITTED'
            );
            
            // Record history
            $this->addHistory(
                $workflowInstanceId,
                $stepId,
                'SUBMITTED',
                $userId,
                "تم تقديم الاتفاقية للموافقة"
            );
            
            // Notify next approvers (VP)
            $this->notifyApprovers(
                $workflowInstanceId,
                $stepId,
                $nextApprovers,
                $this->partnershipHierarchy[$nextStep],
                $agreement,
                'partnership',
                $userId
            );
            
            // Notify the submitter
            $this->notifySubmitter(
                $workflowInstanceId,
                $userId,
                $agreement,
                'partnership',
                $this->partnershipHierarchy[$nextStep]['name_ar']
            );
            
            // Update agreement status
            $this->updateAgreementStatus($agreementId, 'UNDER_REVIEW');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error submitting partnership: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Approve a workflow step (for both initiative and partnership)
     */
    public function approveStep(int $workflowInstanceId, int $stepId, int $userId, string $comments = null): bool {
        if (!$this->db) return false;
        
        try {
            $this->db->beginTransaction();
            
            // Get step info
            $step = $this->getStepInfo($stepId);
            if (!$step) {
                throw new Exception("Step not found");
            }
            
            // Check if user is authorized
            if (!$this->isUserAuthorizedForStep($stepId, $userId)) {
                throw new Exception("User not authorized to approve this step");
            }
            
            // Get workflow info
            $workflow = $this->getWorkflowInfo($workflowInstanceId);
            $entityType = $workflow['entity_type'];
            $entityId = $workflow['entity_id'];
            
            // Get entity details
            $entity = $entityType === 'initiative' 
                ? $this->getInitiative($entityId) 
                : $this->getAgreement($entityId);
            
            // Determine hierarchy
            $hierarchy = $entityType === 'initiative' 
                ? $this->initiativeHierarchy 
                : $this->partnershipHierarchy;
            
            $currentStepOrder = $step['step_order'];
            $totalSteps = count($hierarchy);
            
            // Update step status
            $this->updateStepStatus($stepId, 'APPROVED', $userId, $comments);
            
            // Add history
            $this->addHistory(
                $workflowInstanceId,
                $stepId,
                'APPROVED',
                $userId,
                $comments ?: "تمت الموافقة"
            );
            
            // Check if this was the last step
            if ($currentStepOrder >= $totalSteps) {
                // Complete workflow
                $this->completeWorkflow($workflowInstanceId, $userId);
                $this->notifyWorkflowComplete($workflowInstanceId, $entityType, $entity);
                
                // Update entity status
                if ($entityType === 'initiative') {
                    $this->updateInitiativeStatus($entityId, 'APPROVED');
                } else {
                    $this->updateAgreementStatus($entityId, 'APPROVED');
                }
            } else {
                // Move to next step
                $nextStepOrder = $currentStepOrder + 1;
                $nextLevel = $hierarchy[$nextStepOrder];
                
                // Get next approvers
                $nextApprovers = $this->getApproversByLevel($nextLevel['level'], $entityType);
                
                // Update workflow current step
                $this->updateWorkflowCurrentStep($workflowInstanceId, $nextStepOrder);
                
                // Create next step
                $nextStepId = $this->createWorkflowStep(
                    $workflowInstanceId,
                    $nextStepOrder,
                    null,
                    'PENDING'
                );
                
                // Notify next approvers
                $this->notifyApprovers(
                    $workflowInstanceId,
                    $nextStepId,
                    $nextApprovers,
                    $nextLevel,
                    $entity,
                    $entityType,
                    $userId
                );
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error approving step: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject a workflow step
     */
    public function rejectStep(int $workflowInstanceId, int $stepId, int $userId, string $comments = null): bool {
        if (!$this->db) return false;
        
        try {
            $this->db->beginTransaction();
            
            // Check if user is authorized
            if (!$this->isUserAuthorizedForStep($stepId, $userId)) {
                throw new Exception("User not authorized to reject this step");
            }
            
            // Update step status
            $this->updateStepStatus($stepId, 'REJECTED', $userId, $comments);
            
            // Get workflow info
            $workflow = $this->getWorkflowInfo($workflowInstanceId);
            
            // Add history
            $this->addHistory(
                $workflowInstanceId,
                $stepId,
                'REJECTED',
                $userId,
                $comments ?: "تم الرفض"
            );
            
            // Reject workflow
            $this->rejectWorkflow($workflowInstanceId, $userId);
            
            // Get entity
            $entity = $workflow['entity_type'] === 'initiative' 
                ? $this->getInitiative($workflow['entity_id']) 
                : $this->getAgreement($workflow['entity_id']);
            
            // Notify submitter and all approvers
            $this->notifyWorkflowRejected($workflowInstanceId, $userId, $entity, $comments);
            
            // Update entity status
            if ($workflow['entity_type'] === 'initiative') {
                $this->updateInitiativeStatus($workflow['entity_id'], 'REJECTED');
            } else {
                $this->updateAgreementStatus($workflow['entity_id'], 'REJECTED');
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error rejecting step: " . $e->getMessage());
            return false;
        }
    }
    
    // ============================================
    // NOTIFICATION METHODS
    // ============================================
    
    /**
     * Notify approvers about a new request
     */
    private function notifyApprovers($workflowInstanceId, $stepId, $approvers, $level, $entity, $entityType, $submitterId) {
        if (empty($approvers)) return;
        
        $levelName = $this->lang === 'ar' ? $level['name_ar'] : $level['name_en'];
        $entityName = $entity['title'] ?? ($entity['name'] ?? '');
        $entityCode = $entity['code'] ?? $entity['id'] ?? '';
        
        $titleAr = "طلب موافقة: {$levelName}";
        $titleEn = "Approval Request: {$levelName}";
        
        $messageAr = "تم إرسال طلب موافقة على {$entityType} '{$entityName}' إلى {$levelName}.";
        $messageEn = "An approval request for {$entityType} '{$entityName}' has been sent to {$levelName}.";
        
        $typeText = $entityType === 'initiative' ? 'مبادرة' : 'اتفاقية';
        
        $actionUrl = $entityType === 'initiative' 
            ? "/initiative-details.php?id=" . urlencode($entityCode)
            : "/agreement-details.php?code=" . urlencode($entityCode);
        
        foreach ($approvers as $approver) {
            $this->notificationService->createNotification([
                'user_id' => $approver['user_id'],
                'notification_type' => 'WORKFLOW_SUBMIT',
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'message_ar' => $messageAr,
                'message_en' => $messageEn,
                'workflow_instance_id' => $workflowInstanceId,
                'workflow_step_id' => $stepId,
                'entity_type' => $entityType,
                'entity_id' => $entity['id'] ?? $entity['agreement_id'] ?? null,
                'entity_code' => $entityCode,
                'priority' => 'HIGH',
                'action_required' => true,
                'action_url' => $actionUrl,
                'send_email' => true
            ]);
        }
    }
    
    /**
     * Notify submitter about request status
     */
    private function notifySubmitter($workflowInstanceId, $userId, $entity, $entityType, $nextLevelName) {
        $entityName = $entity['title'] ?? ($entity['name'] ?? '');
        
        $titleAr = "تم تقديم طلب {$entityType}";
        $titleEn = "{$entityType} Request Submitted";
        
        $messageAr = "تم تقديم {$entityType} '{$entityName}' بنجاح. وهو الآن في مرحلة موافقة {$nextLevelName}.";
        $messageEn = "Your {$entityType} '{$entityName}' has been submitted successfully. It is now at {$nextLevelName} approval stage.";
        
        $this->notificationService->createNotification([
            'user_id' => $userId,
            'notification_type' => 'WORKFLOW_SUBMIT',
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'message_ar' => $messageAr,
            'message_en' => $messageEn,
            'workflow_instance_id' => $workflowInstanceId,
            'entity_type' => $entityType,
            'entity_id' => $entity['id'] ?? $entity['agreement_id'] ?? null,
            'entity_code' => $entity['code'] ?? '',
            'priority' => 'MEDIUM',
            'action_required' => false,
            'action_url' => "/notifications.php",
            'send_email' => true
        ]);
    }
    
    /**
     * Notify workflow complete
     */
    private function notifyWorkflowComplete($workflowInstanceId, $entityType, $entity) {
        $entityName = $entity['title'] ?? ($entity['name'] ?? '');
        
        // Get all involved users
        $involvedUsers = $this->getInvolvedUsers($workflowInstanceId);
        
        $titleAr = "✅ تم اكتمال الموافقة على {$entityType}";
        $titleEn = "✅ {$entityType} Approval Completed";
        
        $messageAr = "تم اكتمال جميع مراحل الموافقة على {$entityType} '{$entityName}' بنجاح.";
        $messageEn = "All approval stages for {$entityType} '{$entityName}' have been completed successfully.";
        
        foreach ($involvedUsers as $user) {
            $this->notificationService->createNotification([
                'user_id' => $user['user_id'],
                'notification_type' => 'WORKFLOW_COMPLETE',
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'message_ar' => $messageAr,
                'message_en' => $messageEn,
                'workflow_instance_id' => $workflowInstanceId,
                'entity_type' => $entityType,
                'entity_id' => $entity['id'] ?? $entity['agreement_id'] ?? null,
                'entity_code' => $entity['code'] ?? '',
                'priority' => 'HIGH',
                'action_required' => false,
                'action_url' => "/notifications.php",
                'send_email' => true
            ]);
        }
    }
    
    /**
     * Notify workflow rejected
     */
    private function notifyWorkflowRejected($workflowInstanceId, $rejecterId, $entity, $comments = null) {
        $rejecter = $this->getUserInfo($rejecterId);
        $entityName = $entity['title'] ?? ($entity['name'] ?? '');
        $entityType = $entity['type'] ?? 'request';
        
        // Get submitter
        $submitter = $this->getWorkflowSubmitter($workflowInstanceId);
        
        $titleAr = "❌ تم رفض {$entityType}";
        $titleEn = "❌ {$entityType} Rejected";
        
        $messageAr = "تم رفض {$entityType} '{$entityName}' من قبل " . ($rejecter['first_name'] ?? '') . ". " . ($comments ? "التعليق: {$comments}" : "");
        $messageEn = "The {$entityType} '{$entityName}' was rejected by " . ($rejecter['first_name'] ?? '') . ". " . ($comments ? "Comment: {$comments}" : "");
        
        // Notify submitter
        if ($submitter) {
            $this->notificationService->createNotification([
                'user_id' => $submitter['user_id'],
                'notification_type' => 'WORKFLOW_REJECT',
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'message_ar' => $messageAr,
                'message_en' => $messageEn,
                'workflow_instance_id' => $workflowInstanceId,
                'entity_type' => $entityType,
                'entity_id' => $entity['id'] ?? $entity['agreement_id'] ?? null,
                'entity_code' => $entity['code'] ?? '',
                'priority' => 'HIGH',
                'action_required' => true,
                'action_url' => "/notifications.php",
                'send_email' => true
            ]);
        }
        
        // Notify all approvers
        $approvers = $this->getAllApprovers($workflowInstanceId);
        foreach ($approvers as $approver) {
            if ($approver['user_id'] != $rejecterId) {
                $this->notificationService->createNotification([
                    'user_id' => $approver['user_id'],
                    'notification_type' => 'WORKFLOW_REJECT',
                    'title_ar' => "تم رفض {$entityType}",
                    'title_en' => "{$entityType} Rejected",
                    'message_ar' => "تم رفض {$entityType} '{$entityName}' من قبل " . ($rejecter['first_name'] ?? ''),
                    'message_en' => "The {$entityType} '{$entityName}' was rejected by " . ($rejecter['first_name'] ?? ''),
                    'workflow_instance_id' => $workflowInstanceId,
                    'entity_type' => $entityType,
                    'entity_id' => $entity['id'] ?? $entity['agreement_id'] ?? null,
                    'entity_code' => $entity['code'] ?? '',
                    'priority' => 'MEDIUM',
                    'action_required' => false,
                    'action_url' => "/notifications.php",
                    'send_email' => true
                ]);
            }
        }
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    private function getUserLevel($userId) {
    // Get user's hierarchy role (Doctor, HOD, Dean, VP, President)
        $sql = "SELECT r.role_name 
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                JOIN roles r ON ur.role_id = r.role_id
                WHERE u.user_id = :user_id
                AND r.role_name IN ('Doctor', 'Head of Department', 'Dean', 'Vice President', 'President')
                AND u.is_active = TRUE
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $roleName = $result['role_name'] ?? 'Doctor';
        error_log("User $userId has hierarchy role: " . $roleName);
        
        // Map role to level
        $roleLevelMap = [
            'Doctor' => 'DOCTOR',
            'Head of Department' => 'HOD',
            'Dean' => 'DEAN',
            'Vice President' => 'VP',
            'President' => 'PRESIDENT'
        ];
        
        return $roleLevelMap[$roleName] ?? 'DOCTOR';
    }
    
   private function getApproversByLevel($level, $entityType) {
        // Map level to role name
        $roleMap = [
            'DOCTOR' => 'Doctor',
            'HOD' => 'Head of Department',
            'DEAN' => 'Dean',
            'VP' => 'Vice President',
            'PRESIDENT' => 'President',
            'LAW' => 'Legal Officer',
            'FINANCE' => 'Finance Officer'
        ];
        
        $roleName = $roleMap[$level] ?? $level;
        
        // Log what we're looking for
        error_log("Looking for approvers with role: " . $roleName);
        
        $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                JOIN roles r ON ur.role_id = r.role_id
                WHERE r.role_name = :role_name
                AND u.is_active = TRUE
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_name' => $roleName]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($results) . " approver(s) for role: " . $roleName);
        
        // If no approvers found, return admin as fallback
        if (empty($results)) {
            error_log("No approvers found for role: " . $roleName . ". Using admin as fallback.");
            return $this->getAdminUser();
        }
        
        return $results;
    }

    private function getAdminUser() {
        $sql = "SELECT user_id, first_name, last_name, email FROM users WHERE email = 'admin@uob.edu.bh' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getInitiative($initiativeId) {
        $sql = "SELECT * FROM initiatives WHERE initiative_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $initiativeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getAgreement($agreementId) {
        $sql = "SELECT * FROM agreements WHERE agreement_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $agreementId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserInfo($userId) {
        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getStepInfo($stepId) {
        $sql = "SELECT * FROM workflow_instance_steps WHERE instance_step_id = :step_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':step_id' => $stepId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getWorkflowInfo($workflowInstanceId) {
        $sql = "SELECT * FROM workflow_instances WHERE workflow_instance_id = :instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function createWorkflowInstance($entityType, $entityId, $userId, $currentStep) {
        $sql = "INSERT INTO workflow_instances (
                    workflow_template_id, entity_type, entity_id, 
                    current_step, status, started_by
                ) VALUES (
                    1, :entity_type, :entity_id, 
                    :current_step, 'IN_PROGRESS', :started_by
                ) RETURNING workflow_instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':current_step' => $currentStep,
            ':started_by' => $userId
        ]);
        return $stmt->fetchColumn();
    }
    
    private function createWorkflowStep($workflowInstanceId, $stepOrder, $userId, $status) {
        $sql = "INSERT INTO workflow_instance_steps (
                    workflow_instance_id, step_order, status, started_at
                ) VALUES (
                    :instance_id, :step_order, :status, CURRENT_TIMESTAMP
                ) RETURNING instance_step_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':instance_id' => $workflowInstanceId,
            ':step_order' => $stepOrder,
            ':status' => $status
        ]);
        return $stmt->fetchColumn();
    }
    
    private function updateStepStatus($stepId, $status, $userId, $comments = null) {
        $sql = "UPDATE workflow_instance_steps 
                SET status = :status, 
                    approved_by = :user_id, 
                    approved_at = CURRENT_TIMESTAMP,
                    completed_at = CURRENT_TIMESTAMP,
                    comments = :comments
                WHERE instance_step_id = :step_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':step_id' => $stepId,
            ':status' => $status,
            ':user_id' => $userId,
            ':comments' => $comments
        ]);
    }
    
    private function updateWorkflowCurrentStep($workflowInstanceId, $stepOrder) {
        $sql = "UPDATE workflow_instances 
                SET current_step = :step_order
                WHERE workflow_instance_id = :instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':instance_id' => $workflowInstanceId,
            ':step_order' => $stepOrder
        ]);
    }
    
    private function completeWorkflow($workflowInstanceId, $userId) {
        $sql = "UPDATE workflow_instances 
                SET status = 'COMPLETED', 
                    completed_at = CURRENT_TIMESTAMP
                WHERE workflow_instance_id = :instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
    }
    
    private function rejectWorkflow($workflowInstanceId, $userId) {
        $sql = "UPDATE workflow_instances 
                SET status = 'REJECTED', 
                    completed_at = CURRENT_TIMESTAMP
                WHERE workflow_instance_id = :instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
    }
    
    private function addHistory($workflowInstanceId, $stepId, $action, $userId, $comments = null) {
        $sql = "INSERT INTO workflow_history (
                    workflow_instance_id, workflow_step_id, 
                    action, performed_by, comments
                ) VALUES (
                    :instance_id, :step_id,
                    :action, :user_id, :comments
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':instance_id' => $workflowInstanceId,
            ':step_id' => $stepId,
            ':action' => $action,
            ':user_id' => $userId,
            ':comments' => $comments
        ]);
    }
    
    private function isUserAuthorizedForStep($stepId, $userId) {
        // Check if user is assigned to this step
        $sql = "SELECT COUNT(*) FROM workflow_step_assignments 
                WHERE workflow_instance_step_id = :step_id 
                AND user_id = :user_id 
                AND is_active = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':step_id' => $stepId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function getInvolvedUsers($workflowInstanceId) {
        $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email
                FROM users u
                WHERE u.user_id IN (
                    SELECT started_by FROM workflow_instances 
                    WHERE workflow_instance_id = :instance_id
                    UNION
                    SELECT performed_by FROM workflow_history 
                    WHERE workflow_instance_id = :instance_id
                    UNION
                    SELECT user_id FROM workflow_step_assignments 
                    WHERE workflow_instance_step_id IN (
                        SELECT instance_step_id FROM workflow_instance_steps 
                        WHERE workflow_instance_id = :instance_id
                    )
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getWorkflowSubmitter($workflowInstanceId) {
        $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email
                FROM users u
                JOIN workflow_instances wi ON u.user_id = wi.started_by
                WHERE wi.workflow_instance_id = :instance_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getAllApprovers($workflowInstanceId) {
        $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email
                FROM users u
                JOIN workflow_step_assignments wsa ON u.user_id = wsa.user_id
                WHERE wsa.workflow_instance_step_id IN (
                    SELECT instance_step_id FROM workflow_instance_steps 
                    WHERE workflow_instance_id = :instance_id
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instance_id' => $workflowInstanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function updateInitiativeStatus($initiativeId, $status) {
        $sql = "UPDATE initiatives SET status = :status WHERE initiative_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $initiativeId]);
    }
    
    private function updateAgreementStatus($agreementId, $status) {
        $sql = "UPDATE agreements SET status = :status WHERE agreement_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $agreementId]);
    }
}
?>