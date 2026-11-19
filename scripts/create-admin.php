<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/password.php';
require_once dirname(__DIR__) . '/includes/activity.php';

if ($argc !== 4 || stream_isatty(STDIN)) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php EMPLOYEE_ID FIRST_NAME LAST_NAME\n");
    fwrite(STDERR, "Pipe a password of 8 or more characters and at most 72 bytes to standard input.\n");
    exit(1);
}

$identifier = strtoupper(trim($argv[1]));
$firstName = trim($argv[2]);
$lastName = trim($argv[3]);
$password = rtrim((string) fgets(STDIN, 76), "\r\n");
$namePattern = '/\A[^\x00-\x1f\x7f]{1,100}\z/u';
if (!preg_match('/\A[A-Z0-9-]{4,30}\z/', $identifier)
    || !preg_match($namePattern, $firstName)
    || !preg_match($namePattern, $lastName)
    || !validPassword($password)) {
    fwrite(STDERR, "Invalid employee ID, name, or password.\n");
    exit(1);
}

try {
    $db = connectDatabase();
    databaseTransaction($db, function () use ($db, $identifier, $firstName, $lastName, $password): void {
        $statement = $db->prepare(
            "INSERT INTO users
                 (role_id, identifier_number, first_name, last_name, password_hash, account_status, is_activated, activated_at)
             SELECT role_id, :identifier, :first_name, :last_name, :hash, 'Active', 1, NOW()
             FROM roles WHERE role_slug = 'admin'"
        );
        $statement->execute([
            'identifier' => $identifier,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'hash' => hashPassword($password),
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Missing admin role.');
        }
        recordActivity($db, null, 'ADMIN_ACCOUNT_CREATED', $db->lastInsertId(), ['identifier' => $identifier]);
    });
    fwrite(STDOUT, "Administrator account created.\n");
} catch (RuntimeException $exception) {
    fwrite(STDERR, "Could not create the administrator account. Check the employee ID and database setup.\n");
    exit(1);
}
