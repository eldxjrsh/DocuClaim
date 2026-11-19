<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    redirectTo('login.php');
}

$error = null;
$notifications = [];
$unread = 0;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
try {
    $db = connectDatabase();
    $user = currentAccount($db);
    if ($user === null) {
        $_SESSION = [];
        redirectTo('login.php');
    }
    $userId = (int) $user['user_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validCsrfToken()) {
            http_response_code(403);
            $error = 'The form expired. Please try again.';
        } elseif (postValue('action') === 'read_all') {
            $update = $db->prepare('UPDATE in_app_notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user AND is_read = 0');
            $update->execute(['user' => $userId]);
            redirectTo('notifications.php');
        } elseif (postValue('action') === 'read') {
            $lookup = $db->prepare('SELECT notification_id FROM in_app_notifications WHERE notification_id = :id AND user_id = :user');
            $lookup->execute(['id' => postValue('notification_id'), 'user' => $userId]);
            if ($lookup->fetchColumn() === false) {
                http_response_code(422);
                $error = 'That notification is unavailable.';
            } else {
                $update = $db->prepare(
                    'UPDATE in_app_notifications SET is_read = 1, read_at = NOW()
                     WHERE notification_id = :id AND user_id = :user AND is_read = 0'
                );
                $update->execute(['id' => postValue('notification_id'), 'user' => $userId]);
                redirectTo('notifications.php');
            }
        } else {
            http_response_code(422);
            $error = 'Choose a notification action.';
        }
    }
    $count = $db->prepare('SELECT COUNT(*) FROM in_app_notifications WHERE user_id = :user AND is_read = 0');
    $count->execute(['user' => $userId]);
    $unread = (int) $count->fetchColumn();
    $offset = ($page - 1) * 20;
    $lookup = $db->prepare(
        "SELECT notification_id, title, message, is_read, created_at, related_request_id, related_appointment_id
         FROM in_app_notifications WHERE user_id = :user ORDER BY notification_id DESC LIMIT 21 OFFSET $offset"
    );
    $lookup->execute(['user' => $userId]);
    $notifications = $lookup->fetchAll();
} catch (RuntimeException $exception) {
    error_log('DocuClaim notifications failed (code ' . $exception->getCode() . ').');
    http_response_code(503);
    $error = 'Notifications are temporarily unavailable. Please try again later.';
}

$pageTitle = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="container dc-main">
    <h1>Notifications</h1>
    <p class="dc-intro"><?= $unread ?> unread</p>
    <?php if ($error !== null): ?>
        <p class="dc-card dc-form-error" role="alert"><?= escape($error) ?></p>
    <?php endif; ?>
    <div class="dc-request-list">
        <?php if ($unread > 0): ?>
            <form class="dc-account-form" method="post" action="notifications.php">
                <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                <button type="submit" name="action" value="read_all">Mark All Read</button>
            </form>
        <?php endif; ?>
        <?php if ($notifications === [] && $error === null): ?>
            <p class="dc-card">No notifications to display.</p>
        <?php endif; ?>
        <?php foreach (array_slice($notifications, 0, 20) as $notification): ?>
            <article class="dc-card dc-request-card">
                <header>
                    <h2><?= escape($notification['title']) ?></h2>
                    <?php if (!$notification['is_read']): ?>
                        <span class="dc-request-status">Unread</span>
                    <?php endif; ?>
                </header>
                <p><?= escape($notification['message']) ?></p>
                <p class="dc-request-meta"><?= escape($notification['created_at']) ?></p>
                <div class="dc-request-actions">
                    <a href="<?= $notification['related_appointment_id'] !== null ? 'pickups.php' : 'requests.php' ?>">View <?= $notification['related_appointment_id'] !== null ? 'pickups' : 'requests' ?></a>
                    <?php if (!$notification['is_read']): ?>
                        <form method="post" action="notifications.php">
                            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                            <input type="hidden" name="notification_id" value="<?= (int) $notification['notification_id'] ?>">
                            <button type="submit" name="action" value="read">Mark Read</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <nav class="dc-request-actions" aria-label="Notification pages">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">Previous</a>
            <?php endif; ?>
            <?php if (count($notifications) > 20 && $page < 1000000): ?>
                <a href="?page=<?= $page + 1 ?>">Next</a>
            <?php endif; ?>
        </nav>
    </div>
</main>
</body>
</html>
