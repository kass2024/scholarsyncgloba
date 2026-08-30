<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['student_account_id'], $_SESSION['student_application_id'], $_SESSION['student_email'], $_SESSION['student_name']);

require_once __DIR__ . '/../helpers/urls.php';
header('Location: ' . pcvc_url('/student-login.php'));
exit;

