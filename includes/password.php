<?php
declare(strict_types=1);

function validPassword(string $password): bool
{
    return strlen($password) <= 72 && preg_match('/\A[^\x00]{8,}\z/u', $password) === 1;
}

function hashPassword(string $password): string
{
    return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
}
