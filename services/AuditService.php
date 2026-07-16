<?php
class AuditService {
    public function logLogin(int $userId, array $context = []): void {
        $this->write('LOGIN', $userId, $context);
    }

    public function logLogout(int $userId, array $context = []): void {
        $this->write('LOGOUT', $userId, $context);
    }

    private function write(string $action, int $userId, array $context = []): void {
        $message = sprintf('[%s] user_id=%d context=%s', $action, $userId, json_encode($context));
        error_log($message);
    }
}
