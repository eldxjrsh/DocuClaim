<?php
declare(strict_types=1);

require_once __DIR__ . '/pickups.php';

const REQUEST_TERMS = ['1st Semester', '2nd Semester', 'Midyear'];

const REQUEST_NEXT_STATUS = [
    'Pending Review' => 'Approved',
    'Approved' => 'Processing',
    'Processing' => 'Ready for Pickup',
];

const REQUEST_STATUSES = [
    'Pending Review', 'Approved', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled',
];

function submitDocumentRequest(PDO $db, int $studentId, array $details, array $catalog): string
{
    $document = $catalog[$details['doc_type_id']] ?? null;
    if ($document === null) {
        throw new DomainException('Select an available document type.');
    }
    if (!preg_match('/\A.{3,255}\z/us', $details['purpose'])) {
        throw new DomainException('Enter a purpose between 3 and 255 characters.');
    }
    if (!preg_match('/\A.{0,2000}\z/us', $details['student_note'])) {
        throw new DomainException('Use 2,000 characters or fewer for your note.');
    }

    $year = null;
    $term = null;
    if ($document['requires_academic_year']) {
        $year = $details['academic_year'];
        if (!preg_match('/\A([1-9][0-9]{3})-([0-9]{4})\z/', $year, $years)
            || (int) $years[2] !== (int) $years[1] + 1) {
            throw new DomainException('Enter two consecutive years in YYYY-YYYY format.');
        }
    }
    if ($document['requires_academic_term']) {
        $term = $details['academic_term'];
        if (!in_array($term, REQUEST_TERMS, true)) {
            throw new DomainException('Select an academic term for this document.');
        }
    }

    $reference = 'REQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
    $insert = $db->prepare(
        "INSERT INTO document_requests
             (reference_no, student_user_id, doc_type_id, purpose, academic_year, academic_term, student_note)
         SELECT :reference, u.user_id, dt.doc_type_id, :purpose, :year, :term, :note
         FROM users u JOIN roles r ON r.role_id = u.role_id
         JOIN document_types dt ON dt.doc_type_id = :document AND dt.is_active = 1
         WHERE u.user_id = :student AND r.role_slug = 'student'
           AND u.account_status = 'Active' AND u.is_activated = 1"
    );
    try {
        $insert->execute([
            'reference' => $reference,
            'student' => $studentId,
            'document' => $details['doc_type_id'],
            'purpose' => $details['purpose'],
            'year' => $year,
            'term' => $term,
            'note' => $details['student_note'] === '' ? null : $details['student_note'],
        ]);
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->errorInfo[2] ?? '', 'uq_doc_requests_exclusivity')) {
            throw new DomainException('You already have an active request for this document type.');
        }
        throw $exception;
    }

    if ($insert->rowCount() !== 1) {
        throw new DomainException('This document or student account is unavailable for requests.');
    }
    return $reference;
}

function cancelStudentRequest(PDO $db, int $studentId, string $reference): void
{
    $update = $db->prepare(
        "UPDATE document_requests dr
         JOIN users u ON u.user_id = dr.student_user_id
         JOIN roles r ON r.role_id = u.role_id
         SET dr.status = 'Cancelled', dr.cancelled_by_role = 'Student'
         WHERE dr.reference_no = :reference AND dr.student_user_id = :student AND dr.status = 'Pending Review'
           AND r.role_slug = 'student' AND u.account_status = 'Active' AND u.is_activated = 1"
    );
    $update->execute(['reference' => $reference, 'student' => $studentId]);
    if ($update->rowCount() !== 1) {
        throw new DomainException('Only your own requests awaiting review can be cancelled.');
    }
}

function reviewDocumentRequest(PDO $db, int $staffId, string $reference, string $expected, string $action, string $reason): void
{
    pickupTransaction($db, function () use ($db, $staffId, $reference, $expected, $action, $reason): void {
        $lookup = $db->prepare('SELECT student_user_id FROM document_requests WHERE reference_no = :reference');
        $lookup->execute(['reference' => $reference]);
        $studentId = $lookup->fetchColumn();
        if ($studentId === false) {
            throw new DomainException('The request is unavailable.');
        }
        lockPickupStudent($db, (int) $studentId);
        lockStudentPickupWindows($db, (int) $studentId);
        $next = match ($action) {
            'advance' => REQUEST_NEXT_STATUS[$expected] ?? null,
            'decline' => match ($expected) {
                'Pending Review' => 'Rejected',
                'Approved', 'Processing', 'Ready for Pickup' => 'Cancelled',
                default => null,
            },
            default => null,
        };
        if ($next === null) {
            throw new DomainException('That status change is unavailable.');
        }

        if ($action === 'decline' && !preg_match('/\A.{1,1000}\z/us', $reason)) {
            throw new DomainException('Enter a reason of up to 1,000 characters.');
        }

        $timestamp = match ($next) {
            'Approved' => ', dr.approved_at = NOW()',
            'Processing' => ', dr.processing_at = NOW()',
            'Ready for Pickup' => ', dr.ready_at = NOW()',
            default => '',
        };

        $update = $db->prepare(
            "UPDATE document_requests dr
             JOIN users u ON u.user_id = :staff
             JOIN roles r ON r.role_id = u.role_id
             SET dr.status = :next, dr.handled_by_staff_id = u.user_id,
                 dr.status_reason = :reason, dr.cancelled_by_role = :cancelled_by $timestamp
             WHERE dr.reference_no = :reference AND dr.status = :expected
               AND r.role_slug = 'staff' AND u.account_status = 'Active' AND u.is_activated = 1"
        );
        $update->execute([
            'staff' => $staffId,
            'next' => $next,
            'reason' => $action === 'decline' ? $reason : null,
            'cancelled_by' => $next === 'Cancelled' ? 'Registrar Staff' : null,
            'reference' => $reference,
            'expected' => $expected,
        ]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('The request has changed or is unavailable. Review its current status and try again.');
        }
        if ($next === 'Cancelled') {
            detachCancelledPickupRequest($db, $reference);
        }
    });
}

function listDocumentRequests(PDO $db, ?int $studentId, string $status, string $search, int $page): array
{
    $sql = "SELECT dr.reference_no, dr.purpose, dr.academic_year, dr.academic_term, dr.student_note,
                   dr.status, dr.status_reason, dr.cancelled_by_role, dr.requested_at, dt.type_name,
                   u.identifier_number, CONCAT_WS(' ', u.first_name, u.last_name) AS student_name
            FROM document_requests dr
            JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
            JOIN users u ON u.user_id = dr.student_user_id
            WHERE 1 = 1";
    $params = [];
    if ($studentId !== null) {
        $sql .= ' AND dr.student_user_id = :student';
        $params['student'] = $studentId;
    }
    if ($status !== '') {
        $sql .= ' AND dr.status = :status';
        $params['status'] = $status;
    }
    if ($search !== '') {
        $sql .= ' AND (dr.reference_no = :reference OR u.identifier_number = :identifier)';
        $params['reference'] = $search;
        $params['identifier'] = $search;
    }
    $offset = ($page - 1) * 20;
    $sql .= " ORDER BY dr.requested_at DESC, dr.request_id DESC LIMIT 21 OFFSET $offset";
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}
