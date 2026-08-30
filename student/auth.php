<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['student_account_id'])) {
    require_once __DIR__ . '/../helpers/urls.php';
    header('Location: ' . pcvc_url('/student-login.php'));
    exit;
}

