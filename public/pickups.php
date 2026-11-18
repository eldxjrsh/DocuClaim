<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pickups.php';

if (!isset($_SESSION['user_id'])) {
    redirectTo('login.php');
}

function selectedPickupDocuments(): array
{
    $values = $_POST['documents'] ?? [];
    if (!is_array($values) || count($values) > 100) {
        throw new DomainException('Choose up to 100 documents for a visit.');
    }
    $ids = [];
    foreach ($values as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('Choose valid documents for this visit.');
        }
        $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

$error = null;
$notice = $_SESSION['pickup_notice'] ?? null;
unset($_SESSION['pickup_notice']);
$appointments = [];
$windows = [];
$ready = [];
$available = true;
$staff = false;
$admin = false;
$active = false;
$statusInput = $_GET['status'] ?? '';
$status = is_string($statusInput) && in_array($statusInput, PICKUP_STATUSES, true) ? $statusInput : '';
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) && strlen($searchInput) <= 30 ? trim($searchInput) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;

try {
    $db = connectDatabase();
    $user = currentAccount($db);
    if ($user === null) {
        $_SESSION = [];
        redirectTo('login.php');
    }
    $userId = (int) $user['user_id'];
    $staff = $user['role_slug'] === 'staff';
    $admin = $user['role_slug'] === 'admin';
    $active = $user['account_status'] === 'Active' && (bool) $user['is_activated'];

    if ($active) {
        expirePickupAppointments($db);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!validCsrfToken()) {
                http_response_code(403);
                $error = 'The form expired. Please try again.';
            } elseif (!$active) {
                http_response_code(403);
                $error = 'Activate your student account before booking a pickup.';
            } else {
                $action = postValue('action');
                $reference = trim(postValue('reference'));
                if ($admin && $action === 'create_window') {
                    createPickupWindow($db, $userId, postValue('pickup_date'), postValue('start_time'), postValue('end_time'), postValue('capacity'));
                    $_SESSION['pickup_notice'] = 'The pickup window was created.';
                } elseif ($staff && $action === 'collect') {
                    collectPickupDocuments($db, $userId, $reference, selectedPickupDocuments());
                    $_SESSION['pickup_notice'] = 'Collection was recorded. Any uncollected documents remain available for another booking.';
                } elseif (!$staff && !$admin && $action === 'book') {
                    $windowId = filter_var(postValue('window_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($windowId === false) {
                        throw new DomainException('Choose a pickup window.');
                    }
                    $reference = bookPickup($db, $userId, $windowId, selectedPickupDocuments());
                    $_SESSION['pickup_notice'] = "Pickup $reference was booked.";
                } elseif (!$staff && !$admin && in_array($action, ['move', 'cancel'], true)) {
                    $windowId = null;
                    if ($action === 'move') {
                        $windowId = filter_var(postValue('window_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($windowId === false) {
                            throw new DomainException('Choose a pickup window.');
                        }
                    }
                    changeStudentPickup($db, $userId, $reference, $windowId);
                    $_SESSION['pickup_notice'] = $action === 'move' ? 'Your pickup was rescheduled.' : 'Your pickup was cancelled.';
                } else {
                    http_response_code(403);
                    $error = 'This action is unavailable for your account.';
                }
                if ($error === null) {
                    redirectTo('pickups.php#pickup-feedback');
                }
            }
        } catch (DomainException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        }
    }
    if ($active) {
        $windows = getPickupWindows($db);
        if (!$staff && !$admin) {
            $ready = getReadyPickupDocuments($db, $userId);
        }
        if (!$admin) {
            $appointments = listPickupAppointments($db, $staff ? null : $userId, $status, $staff ? $search : '', $page);
        }
    }
} catch (RuntimeException $exception) {
    error_log('DocuClaim pickup request failed (code ' . $exception->getCode() . ').');
    http_response_code(503);
    $error = 'Pickups are temporarily unavailable. Please try again later.';
    $available = false;
}

$pageTitle = $admin ? 'Pickup Windows' : ($staff ? 'Pickup Visits' : 'My Pickups');
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="container dc-main">
    <h1><?= escape($pageTitle) ?></h1>
    <p class="dc-intro">Pickup times are shown in Philippine time (UTC+8).</p>
    <div id="pickup-feedback">
        <?php if ($error !== null): ?>
            <p class="dc-card dc-form-error" role="alert"><?= escape($error) ?></p>
        <?php elseif ($notice !== null): ?>
            <p class="dc-card dc-request-notice" role="status"><?= escape($notice) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($available && !$active): ?>
        <p class="dc-card">Activate your <a href="account.php">student account</a> before booking a pickup.</p>
    <?php elseif ($available): ?>
        <div class="dc-request-layout">
            <?php if ($admin): ?>
                <?php require dirname(__DIR__) . '/includes/pickup-window-form.php'; ?>
                <section class="dc-request-list" aria-label="Upcoming pickup windows">
                    <h2>Upcoming Windows</h2>
                    <?php if ($windows === []): ?>
                        <p>No upcoming pickup windows.</p>
                    <?php endif; ?>
                    <?php foreach ($windows as $window): ?>
                        <p class="dc-card">
                            <?= escape($window['pickup_date']) ?>, <?= escape(substr($window['start_time'], 0, 5)) ?>–<?= escape(substr($window['end_time'], 0, 5)) ?>
                            · <?= (int) $window['remaining_slots'] ?> of <?= (int) $window['max_capacity'] ?> slots remaining
                        </p>
                    <?php endforeach; ?>
                </section>
                <form class="dc-account-form" method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                    <button type="submit">Sign Out</button>
                </form>
            <?php else: ?>
                <?php if (!$staff): ?>
                    <?php require dirname(__DIR__) . '/includes/pickup-booking-form.php'; ?>
                <?php else: ?>
                    <form class="dc-card dc-account-form dc-request-filter" method="get" action="pickups.php">
                        <label for="search">Booking reference or student number</label>
                        <input id="search" name="search" maxlength="30" value="<?= escape($search) ?>">
                        <label for="status">Visit status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            <?php foreach (PICKUP_STATUSES as $option): ?>
                                <option<?= $status === $option ? ' selected' : '' ?>><?= escape($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Filter Visits</button>
                    </form>
                <?php endif; ?>

                <section class="dc-request-list" aria-label="Pickup history">
                    <h2><?= $staff ? 'Visits' : 'My Visits' ?></h2>
                    <?php if ($appointments === []): ?>
                        <p class="dc-card">No pickup visits to display.</p>
                    <?php endif; ?>
                    <?php foreach (array_slice($appointments, 0, 20) as $appointment): ?>
                        <?php require dirname(__DIR__) . '/includes/pickup-card.php'; ?>
                    <?php endforeach; ?>

                    <?php if ($page > 1 || count($appointments) > 20): ?>
                        <?php $filters = ['status' => $status, 'search' => $staff ? $search : '']; ?>
                        <nav class="dc-request-actions" aria-label="Pickup pages">
                            <?php if ($page > 1): ?>
                                <a href="?<?= escape(http_build_query($filters + ['page' => $page - 1])) ?>">Previous</a>
                            <?php endif; ?>
                            <?php if (count($appointments) > 20 && $page < 1000000): ?>
                                <a href="?<?= escape(http_build_query($filters + ['page' => $page + 1])) ?>">Next</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
