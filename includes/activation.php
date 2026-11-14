<?php
declare(strict_types=1);

function issueActivationSlip(PDO $db, int $staffId, string $identifier): ?array
{
    $db->beginTransaction();
    try {
        $lookup = $db->prepare(
            "SELECT u.user_id, u.first_name, u.last_name
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.identifier_number = :identifier AND r.role_slug = 'student'
               AND u.account_status = 'Pending Activation' AND u.is_activated = 0
             FOR UPDATE"
        );
        $lookup->execute(['identifier' => $identifier]);
        $student = $lookup->fetch();
        if (!$student) {
            $db->rollBack();
            return null;
        }

        $invalidate = $db->prepare(
            "UPDATE security_tokens SET invalidated_at = NOW()
             WHERE user_id = :student AND token_type = 'ACTIVATION_SLIP'
               AND used_at IS NULL AND invalidated_at IS NULL"
        );
        $invalidate->execute(['student' => $student['user_id']]);

        $code = 'ACT-' . implode('-', str_split(strtoupper(bin2hex(random_bytes(10))), 5));
        $insert = $db->prepare(
            "INSERT INTO security_tokens (user_id, issued_by_user_id, token_type, token_hash, expires_at)
             VALUES (:student, :staff, 'ACTIVATION_SLIP', :hash, NOW() + INTERVAL 7 DAY)"
        );
        $insert->execute([
            'student' => $student['user_id'],
            'staff' => $staffId,
            'hash' => hash('sha256', $code),
        ]);

        $slip = [
            'code' => $code,
            'identifier' => $identifier,
            'name' => $student['first_name'] . ' ' . $student['last_name'],
        ];
        $db->commit();
        return $slip;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function activateStudent(PDO $db, int $studentId, string $code): bool
{
    $db->beginTransaction();
    try {
        // Lock the account first in both activation paths to serialize issuance and redemption.
        $activate = $db->prepare(
            "UPDATE users u JOIN roles r ON r.role_id = u.role_id
             SET u.is_activated = 1, u.activated_at = NOW(), u.account_status = 'Active'
             WHERE u.user_id = :student AND r.role_slug = 'student'
               AND u.account_status = 'Pending Activation' AND u.is_activated = 0"
        );
        $activate->execute(['student' => $studentId]);
        if ($activate->rowCount() !== 1) {
            $db->rollBack();
            return false;
        }

        $use = $db->prepare(
            "UPDATE security_tokens SET used_at = NOW()
             WHERE user_id = :student AND token_type = 'ACTIVATION_SLIP' AND token_hash = :hash
               AND used_at IS NULL AND invalidated_at IS NULL AND expires_at > NOW()"
        );
        $use->execute(['student' => $studentId, 'hash' => hash('sha256', $code)]);
        if ($use->rowCount() !== 1) {
            $db->rollBack();
            return false;
        }

        $db->commit();
        return true;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
