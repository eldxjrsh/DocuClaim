<?php
declare(strict_types=1);

require_once __DIR__ . '/pickups.php';
require_once __DIR__ . '/activity.php';

const ACCOUNT_STATUSES = ['Pending Activation', 'Active', 'Suspended', 'Deactivated'];

function updateManagedAccount(PDO $db, int $adminId, string $identifier, string $role, string $status, string $expectedRole, string $expectedStatus): void
{
    if (!in_array($role, ['student', 'staff', 'admin'], true) || !in_array($status, ACCOUNT_STATUSES, true)) {
        throw new DomainException('Choose a valid role and account status.');
    }
    databaseTransaction($db, function () use ($db, $adminId, $identifier, $role, $status, $expectedRole, $expectedStatus): void {
        // Lock administrator accounts in the same order before changing access.
        $db->query(
            "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE r.role_slug = 'admin' ORDER BY u.user_id FOR UPDATE"
        )->fetchAll();
        requireActivePickupActor($db, $adminId, 'admin');
        $lookup = $db->prepare(
            'SELECT u.user_id, u.account_status, u.is_activated, r.role_slug
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.identifier_number = :identifier FOR UPDATE'
        );
        $lookup->execute(['identifier' => $identifier]);
        $account = $lookup->fetch();
        if (!$account || $account['role_slug'] !== $expectedRole || $account['account_status'] !== $expectedStatus) {
            throw new DomainException('The account has changed or is unavailable. Refresh the list and try again.');
        }
        if ((int) $account['user_id'] === $adminId && ($role !== 'admin' || $status !== 'Active')) {
            throw new DomainException('Your administrator account must keep its active administrator access.');
        }
        if ($role === $expectedRole && $status === $expectedStatus) {
            return;
        }
        $activated = match ($status) {
            'Active' => 1,
            'Pending Activation' => 0,
            default => (int) $account['is_activated'],
        };
        $update = $db->prepare(
            'UPDATE users SET role_id = (SELECT role_id FROM roles WHERE role_slug = :role),
             account_status = :status, is_activated = :activated,
             activated_at = CASE WHEN :active = 1 THEN COALESCE(activated_at, NOW()) ELSE activated_at END
             WHERE user_id = :user'
        );
        $update->execute(['role' => $role, 'status' => $status, 'activated' => $activated, 'active' => $activated, 'user' => $account['user_id']]);
        recordActivity($db, $adminId, 'USER_ROLE_OR_STATUS_UPDATED', $account['user_id'], [
            'identifier' => $identifier, 'previous_role' => $expectedRole, 'role' => $role,
            'previous_status' => $expectedStatus, 'status' => $status,
        ]);
    });
}

function saveManagedDocument(PDO $db, int $adminId, array $details): void
{
    $id = $details['doc_type_id'] === '' ? null : filter_var($details['doc_type_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || !preg_match('/\A[A-Z0-9_-]{1,20}\z/', $details['type_code'])
        || !preg_match('/\A.{1,100}\z/u', $details['type_name'])
        || !preg_match('/\A.{0,255}\z/us', $details['description'])
        || !in_array($details['group_code'], ['A', 'B'], true)) {
        throw new DomainException('Use a document code of up to 20 letters, numbers, hyphens or underscores, a name of up to 100 characters, and a description of up to 255 characters.');
    }

    databaseTransaction($db, function () use ($db, $adminId, $details, $id): void {
        requireActivePickupActor($db, $adminId, 'admin');
        if ($id !== null) {
            $lookup = $db->prepare('SELECT doc_type_id FROM document_types WHERE doc_type_id = :id FOR UPDATE');
            $lookup->execute(['id' => $id]);
            if ($lookup->fetchColumn() === false) {
                throw new DomainException('The document type is unavailable.');
            }
        }
        $params = [
            'group' => $details['group_code'], 'code' => $details['type_code'], 'name' => $details['type_name'],
            'description' => $details['description'] === '' ? null : $details['description'],
            'active' => (int) $details['is_active'],
        ];
        if ($id === null) {
            $statement = $db->prepare(
                'INSERT INTO document_types (group_code, type_code, type_name, description, is_active)
                 VALUES (:group, :code, :name, :description, :active)'
            );
        } else {
            $statement = $db->prepare(
                'UPDATE document_types SET group_code = :group, type_code = :code, type_name = :name,
                 description = :description, is_active = :active WHERE doc_type_id = :id'
            );
            $params['id'] = $id;
        }
        try {
            $statement->execute($params);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('A document type with that code or name already exists.');
            }
            throw $exception;
        }
        recordActivity($db, $adminId, $id === null ? 'CATALOG_DOCUMENT_CREATED' : 'CATALOG_DOCUMENT_UPDATED', $id ?? $db->lastInsertId(), [
            'code' => $details['type_code'], 'name' => $details['type_name'],
            'group' => $details['group_code'], 'active' => (bool) $details['is_active'],
        ]);
    });
}

function listManagedAccounts(PDO $db, string $search, string $status, int $page): array
{
    $sql = 'SELECT u.identifier_number, u.first_name, u.last_name, u.account_status, r.role_slug, r.role_name
            FROM users u JOIN roles r ON r.role_id = u.role_id WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND LOCATE(:search, CONCAT_WS(\' \', u.identifier_number, u.first_name, u.last_name)) > 0';
        $params['search'] = $search;
    }
    if ($status !== '') {
        $sql .= ' AND u.account_status = :status';
        $params['status'] = $status;
    }
    $offset = ($page - 1) * 20;
    $lookup = $db->prepare($sql . " ORDER BY u.user_id DESC LIMIT 21 OFFSET $offset");
    $lookup->execute($params);
    return $lookup->fetchAll();
}

function getAdministrationReport(PDO $db, string $from, string $to): array
{
    $requestParams = [];
    $requestFilter = '';
    $visitParams = [];
    $visitFilter = '';
    if ($from !== '') {
        $requestFilter .= ' AND dr.requested_at >= :from';
        $requestParams['from'] = $from;
        $visitFilter .= ' AND pw.pickup_date >= :from';
        $visitParams['from'] = $from;
    }
    if ($to !== '') {
        $requestFilter .= ' AND dr.requested_at < DATE_ADD(:to, INTERVAL 1 DAY)';
        $requestParams['to'] = $to;
        $visitFilter .= ' AND pw.pickup_date <= :to';
        $visitParams['to'] = $to;
    }
    $documents = $db->prepare(
        "SELECT dt.type_name, COUNT(dr.request_id) AS total,
                COALESCE(SUM(dr.status = 'Pending Review'), 0) AS pending,
                COALESCE(SUM(dr.status IN ('Approved', 'Processing')), 0) AS preparing,
                COALESCE(SUM(dr.status = 'Ready for Pickup'), 0) AS ready,
                COALESCE(SUM(dr.status = 'Completed'), 0) AS completed,
                COALESCE(SUM(dr.status IN ('Rejected', 'Cancelled')), 0) AS closed
         FROM document_types dt LEFT JOIN document_requests dr ON dr.doc_type_id = dt.doc_type_id $requestFilter
         GROUP BY dt.doc_type_id, dt.type_name ORDER BY dt.type_name"
    );
    $documents->execute($requestParams);
    $visits = $db->prepare(
        "SELECT pa.appointment_status, COUNT(*) AS total FROM pickup_appointments pa
         JOIN pickup_windows pw ON pw.window_id = pa.window_id
         WHERE 1=1 $visitFilter GROUP BY pa.appointment_status ORDER BY pa.appointment_status"
    );
    $visits->execute($visitParams);
    return ['documents' => $documents->fetchAll(), 'visits' => $visits->fetchAll()];
}

function listActivityRecords(PDO $db, string $action, int $page): array
{
    $sql = 'SELECT al.timestamp, al.action_type, al.target_id, al.ip_address, al.metadata, u.identifier_number
            FROM audit_logs al LEFT JOIN users u ON u.user_id = al.actor_id';
    $params = [];
    if ($action !== '') {
        $sql .= ' WHERE al.action_type = :action';
        $params['action'] = $action;
    }
    $offset = ($page - 1) * 20;
    $lookup = $db->prepare($sql . " ORDER BY al.log_id DESC LIMIT 21 OFFSET $offset");
    $lookup->execute($params);
    return $lookup->fetchAll();
}
