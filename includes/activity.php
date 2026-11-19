<?php
declare(strict_types=1);

function recordActivity(PDO $db, ?int $actorId, string $action, string|int|null $target = null, array $details = []): void
{
    $insert = $db->prepare(
        'INSERT INTO audit_logs (actor_id, action_type, target_id, ip_address, metadata)
         VALUES (:actor, :action, :target, :ip, :details)'
    );
    $insert->execute([
        'actor' => $actorId,
        'action' => $action,
        'target' => $target,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
}

function notifyStudent(PDO $db, int $studentId, string $event, string $title, string $message, ?int $requestId = null, ?int $appointmentId = null): void
{
    $insert = $db->prepare(
        'INSERT INTO in_app_notifications (user_id, event_type, title, message, related_request_id, related_appointment_id)
         VALUES (:student, :event, :title, :message, :request, :appointment)'
    );
    $insert->execute([
        'student' => $studentId,
        'event' => $event,
        'title' => $title,
        'message' => $message,
        'request' => $requestId,
        'appointment' => $appointmentId,
    ]);
}

function notifyRequestStatus(PDO $db, string $reference, string $status, ?string $reason = null): void
{
    $lookup = $db->prepare('SELECT request_id, student_user_id FROM document_requests WHERE reference_no = :reference');
    $lookup->execute(['reference' => $reference]);
    $request = $lookup->fetch();
    $message = "Request $reference is now $status.";
    if ($reason !== null && $reason !== '') {
        $message .= ' Reason: ' . $reason;
    }
    notifyStudent(
        $db,
        (int) $request['student_user_id'],
        $status === 'Ready for Pickup' ? 'DOCUMENT_READY' : 'REQUEST_STATUS_CHANGED',
        $status === 'Ready for Pickup' ? 'Document Ready for Pickup' : 'Request Status Updated',
        $message,
        (int) $request['request_id']
    );
}
