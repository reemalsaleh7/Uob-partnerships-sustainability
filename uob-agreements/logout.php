<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

unset($_SESSION['user_email']);
header("Location: index.php");
exit;