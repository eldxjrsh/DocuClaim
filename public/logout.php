<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/activity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Sign out from your account page.');
}
if (!validCsrfToken()) {
    http_response_code(403);
    exit('The form expired. Return to your account page and try again.');
}

if (isset($_SESSION['user_id'])) {
    try {
        recordActivity(connectDatabase(), (int) $_SESSION['user_id'], 'LOGOUT', (int) $_SESSION['user_id']);
    } catch (RuntimeException $exception) {
        error_log('DocuClaim logout activity could not be recorded (code ' . $exception->getCode() . ').');
    }
}

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
]);
session_destroy();
redirectTo('login.php');
