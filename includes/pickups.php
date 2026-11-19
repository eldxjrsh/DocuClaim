<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/activity.php';

const PICKUP_STATUSES = ['Scheduled', 'Completed', 'Missed', 'Cancelled'];

function lockPickupStudent(PDO $db, int $studentId): void
{
    // Serialize bookings, collection and request cancellation for the same student.
    $lookup = $db->prepare(
        "SELECT u.user_id FROM users u
         JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = :student AND r.role_slug = 'student' FOR UPDATE"
    );
    $lookup->execute(['student' => $studentId]);
    if ($lookup->fetchColumn() === false) {
        throw new DomainException('The student account is unavailable.');
    }
}

function lockStudentPickupWindows(PDO $db, int $studentId, ?int $targetWindow = null): void
{
    $lookup = $db->prepare(
        "SELECT DISTINCT window_id FROM pickup_appointments
         WHERE student_user_id = :student AND appointment_status = 'Scheduled'"
    );
    $lookup->execute(['student' => $studentId]);
    $windowIds = $lookup->fetchAll(PDO::FETCH_COLUMN);
    if ($targetWindow !== null) {
        $windowIds[] = $targetWindow;
    }
    $windowIds = array_values(array_unique(array_map('intval', $windowIds)));
    if ($windowIds === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($windowIds), '?'));
    $lock = $db->prepare("SELECT window_id FROM pickup_windows WHERE window_id IN ($placeholders) ORDER BY window_id FOR UPDATE");
    $lock->execute($windowIds);
    $lock->fetchAll();
}

function requireActivePickupActor(PDO $db, int $userId, string $role): void
{
    $lookup = $db->prepare(
        "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = :user AND r.role_slug = :role
           AND u.account_status = 'Active' AND u.is_activated = 1"
    );
    $lookup->execute(['user' => $userId, 'role' => $role]);
    if (!$lookup->fetch()) {
        throw new DomainException('Your account is unavailable for this action.');
    }
}

function createPickupWindow(PDO $db, int $adminId, string $date, string $start, string $end, string $capacity): void
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start, new DateTimeZone('Asia/Manila'));
    if (!$parsed || $parsed->format('Y-m-d H:i') !== $date . ' ' . $start
        || !preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $end)
        || $end <= $start || $parsed <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'))) {
        throw new DomainException('Choose a future date and an end time later than the start time.');
    }
    $capacityValue = filter_var($capacity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($capacityValue === false) {
        throw new DomainException('Capacity must be between 1 and 1,000 visits.');
    }
    requireActivePickupActor($db, $adminId, 'admin');
    databaseTransaction($db, function () use ($db, $adminId, $date, $start, $end, $capacityValue): void {
        $insert = $db->prepare(
            'INSERT INTO pickup_windows (pickup_date, start_time, end_time, max_capacity, created_by_admin_id)
             VALUES (:date, :start, :end, :capacity, :admin)'
        );
        try {
            $insert->execute(['date' => $date, 'start' => $start, 'end' => $end, 'capacity' => $capacityValue, 'admin' => $adminId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('A pickup window with these times already exists.');
            }
            throw $exception;
        }
        recordActivity($db, $adminId, 'PICKUP_WINDOW_CREATED', $db->lastInsertId(), ['date' => $date, 'start' => $start, 'end' => $end, 'capacity' => $capacityValue]);
    });
}

function expirePickupAppointments(PDO $db): void
{
    $students = $db->query(
        "SELECT DISTINCT pa.student_user_id FROM pickup_appointments pa
         JOIN pickup_windows pw ON pw.window_id = pa.window_id
         WHERE pa.appointment_status = 'Scheduled' AND TIMESTAMP(pw.pickup_date, pw.end_time) <= NOW()"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($students as $studentId) {
        databaseTransaction($db, function () use ($db, $studentId): void {
            lockPickupStudent($db, (int) $studentId);
            lockStudentPickupWindows($db, (int) $studentId);
            $documents = $db->prepare(
                "UPDATE appointment_documents ad
                 JOIN pickup_appointments pa ON pa.appointment_id = ad.appointment_id
                 JOIN pickup_windows pw ON pw.window_id = pa.window_id
                 SET ad.collection_state = 'Uncollected'
                 WHERE pa.student_user_id = :student AND pa.appointment_status = 'Scheduled'
                   AND ad.collection_state = 'Attached' AND TIMESTAMP(pw.pickup_date, pw.end_time) <= NOW()"
            );
            $documents->execute(['student' => $studentId]);
            $visits = $db->prepare(
                "UPDATE pickup_appointments pa JOIN pickup_windows pw ON pw.window_id = pa.window_id
                 SET pa.appointment_status = 'Missed'
                 WHERE pa.student_user_id = :student AND pa.appointment_status = 'Scheduled'
                   AND TIMESTAMP(pw.pickup_date, pw.end_time) <= NOW()"
            );
            $visits->execute(['student' => $studentId]);
            if ($visits->rowCount() > 0) {
                recordActivity($db, null, 'APPOINTMENTS_MARKED_MISSED', $studentId, ['visits' => $visits->rowCount()]);
            }
        });
    }
}

function getPickupWindows(PDO $db): array
{
    return $db->query(
        "SELECT pw.window_id, pw.pickup_date, pw.start_time, pw.end_time, pw.max_capacity,
                pw.max_capacity - (SELECT COUNT(*) FROM pickup_appointments pa
                    WHERE pa.window_id = pw.window_id AND pa.appointment_status IN ('Scheduled', 'Completed')) AS remaining_slots
         FROM pickup_windows pw WHERE pw.window_status = 'Open'
           AND TIMESTAMP(pw.pickup_date, pw.start_time) > NOW()
         ORDER BY pw.pickup_date, pw.start_time, pw.window_id"
    )->fetchAll();
}

function getReadyPickupDocuments(PDO $db, int $studentId): array
{
    $lookup = $db->prepare(
        "SELECT dr.request_id, dr.reference_no, dt.type_name
         FROM document_requests dr JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
         WHERE dr.student_user_id = :student AND dr.status = 'Ready for Pickup'
           AND NOT EXISTS (SELECT 1 FROM appointment_documents ad
                           WHERE ad.request_id = dr.request_id AND ad.collection_state = 'Attached')
         ORDER BY dr.request_id"
    );
    $lookup->execute(['student' => $studentId]);
    return $lookup->fetchAll();
}

function bookPickup(PDO $db, int $studentId, int $windowId, array $requestIds): string
{
    return databaseTransaction($db, function () use ($db, $studentId, $windowId, $requestIds): string {
        lockPickupStudent($db, $studentId);
        lockStudentPickupWindows($db, $studentId, $windowId);
        requireActivePickupActor($db, $studentId, 'student');
        lockAvailablePickupWindow($db, $windowId);
        $ready = getReadyPickupDocuments($db, $studentId);
        $readyIds = array_column($ready, 'request_id');
        if ($requestIds === [] || array_diff($requestIds, $readyIds) !== []) {
            throw new DomainException('Choose your ready documents that are available for booking.');
        }

        $reference = 'APT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
        $insert = $db->prepare(
            'INSERT INTO pickup_appointments (booking_reference, student_user_id, window_id)
             VALUES (:reference, :student, :window)'
        );
        $insert->execute(['reference' => $reference, 'student' => $studentId, 'window' => $windowId]);
        $appointmentId = $db->lastInsertId();
        $attach = $db->prepare('INSERT INTO appointment_documents (appointment_id, request_id) VALUES (:appointment, :request)');
        foreach ($requestIds as $requestId) {
            $attach->execute(['appointment' => $appointmentId, 'request' => $requestId]);
        }
        recordActivity($db, $studentId, 'APPOINTMENT_BOOKED', $reference, ['window' => $windowId, 'documents' => count($requestIds)]);
        return $reference;
    });
}

function lockAvailablePickupWindow(PDO $db, int $windowId): void
{
    $lookup = $db->prepare(
        "SELECT max_capacity FROM pickup_windows WHERE window_id = :window
         AND window_status = 'Open' AND TIMESTAMP(pickup_date, start_time) > NOW() FOR UPDATE"
    );
    $lookup->execute(['window' => $windowId]);
    $capacity = $lookup->fetchColumn();
    if ($capacity === false) {
        throw new DomainException('Choose an open pickup window that has not started.');
    }
    $count = $db->prepare(
        "SELECT appointment_id FROM pickup_appointments
         WHERE window_id = :window AND appointment_status IN ('Scheduled', 'Completed') FOR UPDATE"
    );
    $count->execute(['window' => $windowId]);
    if (count($count->fetchAll()) >= (int) $capacity) {
        throw new DomainException('This pickup window is full. Choose another window.');
    }
}

function changeStudentPickup(PDO $db, int $studentId, string $reference, ?int $windowId): void
{
    databaseTransaction($db, function () use ($db, $studentId, $reference, $windowId): void {
        lockPickupStudent($db, $studentId);
        lockStudentPickupWindows($db, $studentId, $windowId);
        requireActivePickupActor($db, $studentId, 'student');
        $lookup = $db->prepare(
            "SELECT pa.appointment_id, pa.window_id FROM pickup_appointments pa
             JOIN pickup_windows pw ON pw.window_id = pa.window_id
             WHERE pa.booking_reference = :reference AND pa.student_user_id = :student
               AND pa.appointment_status = 'Scheduled' AND TIMESTAMP(pw.pickup_date, pw.start_time) > NOW() FOR UPDATE"
        );
        $lookup->execute(['reference' => $reference, 'student' => $studentId]);
        $appointment = $lookup->fetch();
        if (!$appointment) {
            throw new DomainException('Only your scheduled visits whose windows have not started can be changed.');
        }
        if ($windowId !== null) {
            if ($windowId === (int) $appointment['window_id']) {
                throw new DomainException('Choose a different pickup window.');
            }
            lockAvailablePickupWindow($db, $windowId);
            $move = $db->prepare('UPDATE pickup_appointments SET window_id = :window WHERE appointment_id = :appointment');
            $move->execute(['window' => $windowId, 'appointment' => $appointment['appointment_id']]);
            $window = $db->prepare('SELECT pickup_date, start_time, end_time FROM pickup_windows WHERE window_id = :window');
            $window->execute(['window' => $windowId]);
            $times = $window->fetch();
            notifyStudent($db, $studentId, 'APPOINTMENT_RESCHEDULED', 'Pickup Appointment Rescheduled',
                "Pickup $reference is now on {$times['pickup_date']}, {$times['start_time']}–{$times['end_time']} (Philippine time).",
                null, (int) $appointment['appointment_id']);
            recordActivity($db, $studentId, 'APPOINTMENT_RESCHEDULED', $reference, ['previous_window' => $appointment['window_id'], 'window' => $windowId]);
            return;
        }
        $detach = $db->prepare(
            "UPDATE appointment_documents SET collection_state = 'Detached'
             WHERE appointment_id = :appointment AND collection_state = 'Attached'"
        );
        $detach->execute(['appointment' => $appointment['appointment_id']]);
        $cancel = $db->prepare(
            "UPDATE pickup_appointments SET appointment_status = 'Cancelled', cancelled_at = NOW(),
             cancellation_reason = 'Cancelled by student' WHERE appointment_id = :appointment"
        );
        $cancel->execute(['appointment' => $appointment['appointment_id']]);
        notifyStudent($db, $studentId, 'APPOINTMENT_CANCELLED', 'Pickup Appointment Cancelled',
            "Pickup $reference was cancelled at your request.", null, (int) $appointment['appointment_id']);
        recordActivity($db, $studentId, 'APPOINTMENT_CANCELLED', $reference);
    });
}

function collectPickupDocuments(PDO $db, int $staffId, string $reference, array $requestIds): void
{
    $find = $db->prepare('SELECT student_user_id FROM pickup_appointments WHERE booking_reference = :reference');
    $find->execute(['reference' => $reference]);
    $studentId = $find->fetchColumn();
    if ($studentId === false || $requestIds === []) {
        throw new DomainException('Choose a scheduled visit and at least one document handed to the student.');
    }

    databaseTransaction($db, function () use ($db, $staffId, $reference, $requestIds, $studentId): void {
        lockPickupStudent($db, (int) $studentId);
        lockStudentPickupWindows($db, (int) $studentId);
        requireActivePickupActor($db, $staffId, 'staff');
        $lookup = $db->prepare(
            "SELECT pa.appointment_id FROM pickup_appointments pa JOIN pickup_windows pw ON pw.window_id = pa.window_id
             WHERE pa.booking_reference = :reference AND pa.appointment_status = 'Scheduled'
               AND TIMESTAMP(pw.pickup_date, pw.start_time) <= NOW()
               AND TIMESTAMP(pw.pickup_date, pw.end_time) > NOW() FOR UPDATE"
        );
        $lookup->execute(['reference' => $reference]);
        $appointmentId = $lookup->fetchColumn();
        if ($appointmentId === false) {
            throw new DomainException('Collection is available during a scheduled visit’s pickup window.');
        }
        $documents = $db->prepare(
            "SELECT ad.request_id, dr.status, dr.reference_no FROM appointment_documents ad
             JOIN document_requests dr ON dr.request_id = ad.request_id
             WHERE ad.appointment_id = :appointment AND ad.collection_state = 'Attached' FOR UPDATE"
        );
        $documents->execute(['appointment' => $appointmentId]);
        $attached = $documents->fetchAll();
        if (array_diff($requestIds, array_column($attached, 'request_id')) !== []
            || array_diff(array_column($attached, 'status'), ['Ready for Pickup']) !== []) {
            throw new DomainException('The selected documents are unavailable for collection. Refresh the visit.');
        }

        $markDocument = $db->prepare(
            'UPDATE appointment_documents SET collection_state = :state, collected_at = :collected,
             released_by_staff_id = :staff WHERE appointment_id = :appointment AND request_id = :request'
        );
        $completeRequest = $db->prepare(
            "UPDATE document_requests SET status = 'Completed', completed_at = NOW(), handled_by_staff_id = :staff
             WHERE request_id = :request"
        );
        foreach ($attached as $document) {
            $collected = in_array((int) $document['request_id'], $requestIds, true);
            $markDocument->execute([
                'state' => $collected ? 'Collected' : 'Uncollected',
                'collected' => $collected ? date('Y-m-d H:i:s') : null,
                'staff' => $collected ? $staffId : null,
                'appointment' => $appointmentId,
                'request' => $document['request_id'],
            ]);
            if ($collected) {
                $completeRequest->execute(['staff' => $staffId, 'request' => $document['request_id']]);
                notifyRequestStatus($db, $document['reference_no'], 'Completed');
            }
        }
        $complete = $db->prepare(
            "UPDATE pickup_appointments SET appointment_status = 'Completed', completed_at = NOW(),
             processed_by_staff_id = :staff WHERE appointment_id = :appointment"
        );
        $complete->execute(['staff' => $staffId, 'appointment' => $appointmentId]);
        recordActivity($db, $staffId, 'APPOINTMENT_COLLECTION_RECORDED', $reference, ['documents_collected' => count($requestIds)]);
    });
}

function detachCancelledPickupRequest(PDO $db, string $reference, int $staffId): void
{
    $detach = $db->prepare(
        "UPDATE appointment_documents ad JOIN document_requests dr ON dr.request_id = ad.request_id
         SET ad.collection_state = 'Detached'
         WHERE dr.reference_no = :reference AND ad.collection_state = 'Attached'"
    );
    $detach->execute(['reference' => $reference]);
    $lookup = $db->prepare(
        "SELECT pa.appointment_id, pa.student_user_id, pa.booking_reference
         FROM pickup_appointments pa
         JOIN appointment_documents affected ON affected.appointment_id = pa.appointment_id
         JOIN document_requests dr ON dr.request_id = affected.request_id
         WHERE dr.reference_no = :reference AND pa.appointment_status = 'Scheduled'
           AND NOT EXISTS (SELECT 1 FROM appointment_documents remaining
                           WHERE remaining.appointment_id = pa.appointment_id AND remaining.collection_state = 'Attached')"
    );
    $lookup->execute(['reference' => $reference]);
    $cancel = $db->prepare(
        "UPDATE pickup_appointments SET appointment_status = 'Cancelled', cancelled_at = NOW(),
         cancellation_reason = 'All attached document requests were cancelled' WHERE appointment_id = :appointment"
    );
    foreach ($lookup->fetchAll() as $appointment) {
        $cancel->execute(['appointment' => $appointment['appointment_id']]);
        notifyStudent($db, (int) $appointment['student_user_id'], 'APPOINTMENT_CANCELLED', 'Pickup Appointment Cancelled',
            "Pickup {$appointment['booking_reference']} was cancelled because all its document requests were cancelled.",
            null, (int) $appointment['appointment_id']);
        recordActivity($db, $staffId, 'APPOINTMENT_CANCELLED', $appointment['booking_reference'], ['reason' => 'All documents cancelled']);
    }
}


function listPickupAppointments(PDO $db, ?int $studentId, string $status, string $search, int $page): array
{
    $sql = "SELECT pa.*, pw.pickup_date, pw.start_time, pw.end_time, u.identifier_number,
                   CONCAT_WS(' ', u.first_name, u.last_name) AS student_name,
                   TIMESTAMP(pw.pickup_date, pw.start_time) > NOW() AS can_change,
                   NOW() >= TIMESTAMP(pw.pickup_date, pw.start_time)
                     AND NOW() < TIMESTAMP(pw.pickup_date, pw.end_time) AS can_collect
            FROM pickup_appointments pa JOIN pickup_windows pw ON pw.window_id = pa.window_id
            JOIN users u ON u.user_id = pa.student_user_id WHERE 1=1";
    $params = [];
    if ($studentId !== null) {
        $sql .= ' AND pa.student_user_id = :student';
        $params['student'] = $studentId;
    }
    if ($status !== '') {
        $sql .= ' AND pa.appointment_status = :status';
        $params['status'] = $status;
    }
    if ($search !== '') {
        $sql .= ' AND (pa.booking_reference = :reference OR u.identifier_number = :identifier)';
        $params['reference'] = $search;
        $params['identifier'] = $search;
    }
    $offset = ($page - 1) * 20;
    $sql .= " ORDER BY pa.booked_at DESC, pa.appointment_id DESC LIMIT 21 OFFSET $offset";
    $lookup = $db->prepare($sql);
    $lookup->execute($params);
    $appointments = $lookup->fetchAll();
    $documents = $db->prepare(
        'SELECT ad.request_id, ad.collection_state, dr.reference_no, dt.type_name
         FROM appointment_documents ad JOIN document_requests dr ON dr.request_id = ad.request_id
         JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
         WHERE ad.appointment_id = :appointment ORDER BY ad.appointment_doc_id'
    );
    foreach ($appointments as &$appointment) {
        $documents->execute(['appointment' => $appointment['appointment_id']]);
        $appointment['documents'] = $documents->fetchAll();
    }
    unset($appointment);
    return $appointments;
}
