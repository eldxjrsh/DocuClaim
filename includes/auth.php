<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_ACCOUNT_LIMIT = 5;
const LOGIN_IP_LIMIT = 25;

function signIn(PDO $db, string $identifier, string $password, string $ip): bool
{
    $limits = $db->prepare(
        'SELECT
             (SELECT COUNT(*) FROM login_attempts
              WHERE identifier_number = :identifier AND is_successful = 0
                AND attempted_at >= NOW() - INTERVAL :account_window MINUTE) AS account_failures,
             (SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip AND is_successful = 0
                AND attempted_at >= NOW() - INTERVAL :address_window MINUTE) AS address_failures'
    );
    $limits->execute([
        'identifier' => $identifier,
        'ip' => $ip,
        'account_window' => LOGIN_WINDOW_MINUTES,
        'address_window' => LOGIN_WINDOW_MINUTES,
    ]);
    $attempts = $limits->fetch();
    if ($attempts['account_failures'] >= LOGIN_ACCOUNT_LIMIT || $attempts['address_failures'] >= LOGIN_IP_LIMIT) {
        throw new OverflowException('Too many attempts. Try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.');
    }

    $lookup = $db->prepare(
        'SELECT u.user_id, u.password_hash, u.account_status, u.is_activated, r.role_slug
         FROM users u JOIN roles r ON r.role_id = u.role_id
         WHERE u.identifier_number = :identifier'
    );
    $lookup->execute(['identifier' => $identifier]);
    $user = $lookup->fetch();
    // Use a fixed hash for unknown accounts so password verification still takes place.
    $fallbackHash = '$2y$12$v7Q4FOtB6zfIgXwf5z3bS.kg2ZsTS4fcHXtxRa0xQbSILqT60VtjS';
    $verified = password_verify($password, $user['password_hash'] ?? $fallbackHash);
    $allowed = $verified && $user && $user['password_hash'] !== null && canSignIn($user);
    $record = $db->prepare(
        'INSERT INTO login_attempts (identifier_number, ip_address, is_successful)
         VALUES (:identifier, :ip, :successful)'
    );
    $record->execute(['identifier' => $identifier, 'ip' => $ip, 'successful' => (int) $allowed]);

    if (!$allowed) {
        return false;
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not regenerate the session ID.');
    }
    $_SESSION = ['user_id' => (int) $user['user_id']];
    return true;
}

function currentAccount(PDO $db): ?array
{
    $lookup = $db->prepare(
        'SELECT u.user_id, u.identifier_number, u.first_name, u.middle_name, u.last_name,
                u.account_status, u.is_activated, r.role_slug, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = :user_id'
    );
    $lookup->execute(['user_id' => $_SESSION['user_id']]);
    $user = $lookup->fetch();
    return $user && canSignIn($user) ? $user : null;
}

function canSignIn(array $user): bool
{
    return match ($user['role_slug']) {
        'student' => $user['account_status'] === 'Pending Activation'
            || ($user['account_status'] === 'Active' && (bool) $user['is_activated']),
        'staff', 'admin' => $user['account_status'] === 'Active' && (bool) $user['is_activated'],
        default => false,
    };
}
