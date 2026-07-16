<?php
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthController {
    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error('Email and password are required', 422);
        }

        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            Response::error($result['error'], 401);
        }

        Response::success($result['user']);
    }

    public function logout(): void {
        $this->authService->logout();
        Response::success(['message' => 'Logged out']);
    }

    public function me(): void {
        if (!$this->authService->isAuthenticated()) {
            Response::error('Not authenticated', 401);
        }
        Response::success($this->authService->currentUser());
    }
}