<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function registerStudent(array $details, string $password, string $confirmation): ?string
{
    if (!preg_match('/\A[A-Z0-9-]{4,30}\z/', $details['student_number'])) {
        return 'Enter a student number with 4 to 30 letters, numbers, or hyphens.';
    }

    $namePattern = '/\A[^\x00-\x1f\x7f]{1,100}\z/u';
    if (!preg_match($namePattern, $details['first_name'])
        || !preg_match($namePattern, $details['last_name'])
        || ($details['middle_name'] !== '' && !preg_match($namePattern, $details['middle_name']))) {
        return 'Enter your first and last names. Each name can contain up to 100 characters.';
    }

    if (strlen($password) > 72 || !preg_match('/\A[^\x00]{8,}\z/u', $password)) {
        return 'Use a password with at least 8 characters and no more than 72 bytes.';
    }
    if ($password !== $confirmation) {
        return 'Passwords do not match.';
    }

    $statement = connectDatabase()->prepare(
        "INSERT INTO users (role_id, identifier_number, first_name, middle_name, last_name, password_hash)
         SELECT role_id, :identifier, :first_name, :middle_name, :last_name, :password_hash
         FROM roles
         WHERE role_slug = 'student'"
    );

    try {
        $statement->execute([
            ':identifier' => $details['student_number'],
            ':first_name' => $details['first_name'],
            ':middle_name' => $details['middle_name'] !== '' ? $details['middle_name'] : null,
            ':last_name' => $details['last_name'],
            ':password_hash' => password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT),
        ]);
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1062) {
            return 'An account with this student number is already registered.';
        }
        throw $exception;
    }

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The student role is missing.');
    }

    return null;
}
