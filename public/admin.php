<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/administration.php';

if (!isset($_SESSION['user_id'])) {
    redirectTo('login.php');
}

$sectionInput = $_GET['section'] ?? '';
$section = is_string($sectionInput) && in_array($sectionInput, ['users', 'catalog', 'activity'], true) ? $sectionInput : 'reports';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) && strlen($searchInput) <= 100 ? trim($searchInput) : '';
$statusInput = $_GET['status'] ?? '';
$status = is_string($statusInput) && in_array($statusInput, ACCOUNT_STATUSES, true) ? $statusInput : '';
$actionInput = $_GET['event'] ?? '';
$event = is_string($actionInput) && preg_match('/\A[A-Z_]{1,80}\z/', $actionInput) ? $actionInput : '';
$error = null;
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$available = true;
$accounts = [];
$documents = [];
$records = [];
$report = ['documents' => [], 'visits' => []];
$from = '';
$to = '';

try {
    $db = connectDatabase();
    $user = currentAccount($db);
    if ($user === null) {
        $_SESSION = [];
        redirectTo('login.php');
    }
    if ($user['role_slug'] !== 'admin') {
        http_response_code(403);
        exit('Administrator access is required.');
    }
    $adminId = (int) $user['user_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!validCsrfToken()) {
                http_response_code(403);
                $error = 'The form expired. Please try again.';
            } elseif (postValue('action') === 'update_user') {
                updateManagedAccount(
                    $db, $adminId, trim(postValue('identifier')), postValue('role'), postValue('account_status'),
                    postValue('expected_role'), postValue('expected_status')
                );
                $_SESSION['admin_notice'] = 'The account was updated.';
                redirectTo('admin.php?section=users');
            } elseif (postValue('action') === 'save_document') {
                saveManagedDocument($db, $adminId, [
                    'doc_type_id' => postValue('doc_type_id'), 'group_code' => postValue('group_code'),
                    'type_code' => strtoupper(trim(postValue('type_code'))), 'type_name' => trim(postValue('type_name')),
                    'description' => trim(postValue('description')), 'is_active' => postValue('is_active') === '1',
                ]);
                $_SESSION['admin_notice'] = 'The document catalog was updated.';
                redirectTo('admin.php?section=catalog');
            } else {
                throw new DomainException('Choose an administration action.');
            }
        } catch (DomainException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        }
    }

    if ($section === 'users') {
        $accounts = listManagedAccounts($db, $search, $status, $page);
    } elseif ($section === 'catalog') {
        $documents = $db->query('SELECT * FROM document_types ORDER BY group_code, type_name')->fetchAll();
    } elseif ($section === 'activity') {
        $records = listActivityRecords($db, $event, $page);
    } else {
        foreach (['from', 'to'] as $key) {
            $input = $_GET[$key] ?? '';
            if (is_string($input) && $input !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $input);
                if (!$parsed || $parsed->format('Y-m-d') !== $input) {
                    throw new DomainException('Enter valid report dates.');
                }
                if ($key === 'from') {
                    $from = $input;
                } else {
                    $to = $input;
                }
            } elseif (!is_string($input)) {
                throw new DomainException('Enter valid report dates.');
            }
        }
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new DomainException('The start date must be on or before the end date.');
        }
        expirePickupAppointments($db);
        $report = getAdministrationReport($db, $from, $to);
    }
} catch (DomainException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
} catch (RuntimeException $exception) {
    error_log('DocuClaim administration failed (code ' . $exception->getCode() . ').');
    http_response_code(503);
    $error = 'Administration is temporarily unavailable. Please try again later.';
    $available = false;
}

$pageTitle = 'Administration';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="container dc-main">
    <h1>Administration</h1>
    <nav class="dc-admin-tabs" aria-label="Administration">
        <?php foreach (['reports' => 'Reports', 'users' => 'Accounts', 'catalog' => 'Document Types', 'activity' => 'Activity'] as $key => $label): ?>
            <a href="?section=<?= escape($key) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= escape($label) ?></a>
        <?php endforeach; ?>
        <a href="pickups.php">Pickup Windows</a>
    </nav>
    <?php if ($error !== null): ?>
        <p class="dc-card dc-form-error" role="alert"><?= escape($error) ?></p>
    <?php elseif ($notice !== null): ?>
        <p class="dc-card dc-request-notice" role="status"><?= escape($notice) ?></p>
    <?php endif; ?>

    <?php if ($available): ?>
        <div class="dc-request-list dc-admin-content">
            <?php require dirname(__DIR__) . '/includes/admin-' . $section . '.php'; ?>
        </div>
    <?php endif; ?>

    <form class="dc-account-form dc-admin-signout" method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
        <button type="submit">Sign Out</button>
    </form>
</main>
</body>
</html>
