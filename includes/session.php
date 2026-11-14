<?php
declare(strict_types=1);

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_name('DOCUCLAIM_SESSID');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
if (!session_start()) {
    http_response_code(503);
    exit('The session is temporarily unavailable.');
}

function postValue(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function validCsrfToken(): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], postValue('csrf_token'));
}

function redirectTo(string $page): never
{
    header('Location: ' . $page, true, 303);
    exit;
}
