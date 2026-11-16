<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_once dirname(__DIR__) . '/includes/document-requests.php';

if (!isset($_SESSION['user_id'])) {
    redirectTo('login.php');
}

$error = null;
$notice = $_SESSION['request_notice'] ?? null;
unset($_SESSION['request_notice']);
$requests = [];
$groups = [];
$staff = false;
$canRequest = false;
$available = true;

$filter = $_GET['status'] ?? '';
$status = is_string($filter) && in_array($filter, REQUEST_STATUSES, true) ? $filter : '';
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) && strlen($searchInput) <= 30 ? trim($searchInput) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
$details = [
    'doc_type_id' => trim(postValue('doc_type_id')),
    'purpose' => trim(postValue('purpose')),
    'academic_year' => trim(postValue('academic_year')),
    'academic_term' => trim(postValue('academic_term')),
    'student_note' => trim(postValue('student_note')),
];

try {
    $db = connectDatabase();
    $user = currentAccount($db);
    if ($user === null) {
        $_SESSION = [];
        redirectTo('login.php');
    }
    $userId = (int) $user['user_id'];
    $staff = $user['role_slug'] === 'staff';
    $canRequest = !$staff && $user['account_status'] === 'Active' && (bool) $user['is_activated'];

    if ($canRequest) {
        $groups = getDocumentCatalog($db);
        $catalog = [];
        foreach ($groups as $group) {
            foreach ($group['documents'] as $document) {
                $catalog[$document['doc_type_id']] = $document;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!validCsrfToken()) {
                http_response_code(403);
                $error = 'The form expired. Please try again.';
            } elseif (!$staff && !$canRequest) {
                http_response_code(403);
                $error = 'Activate your account before managing document requests.';
            } else {
                $reference = trim(postValue('reference'));
                $action = postValue('action');
                if ($staff) {
                    reviewDocumentRequest($db, $userId, $reference, postValue('expected_status'), $action, trim(postValue('reason')));
                    $_SESSION['request_notice'] = 'The request status was updated.';
                } elseif ($action === 'submit') {
                    $reference = submitDocumentRequest($db, $userId, $details, $catalog);
                    $_SESSION['request_notice'] = "Request $reference was submitted.";
                } elseif ($action === 'cancel') {
                    cancelStudentRequest($db, $userId, $reference);
                    $_SESSION['request_notice'] = 'Your request was cancelled.';
                } else {
                    throw new DomainException('Choose a valid request action.');
                }
                redirectTo('requests.php#request-feedback');
            }
        } catch (DomainException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        }
    }

    if ($staff || $canRequest) {
        $requests = listDocumentRequests($db, $staff ? null : $userId, $status, $staff ? $search : '', $page);
    }
} catch (RuntimeException $exception) {
    error_log('DocuClaim request page failed (code ' . $exception->getCode() . ').');
    http_response_code(503);
    $error = 'Document requests are temporarily unavailable. Please try again later.';
    $available = false;
}

$pageTitle = $staff ? 'Request Queue' : 'My Requests';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="container dc-main">
    <h1><?= escape($pageTitle) ?></h1>
    <div id="request-feedback">
        <?php if ($error !== null): ?>
            <p class="dc-card dc-form-error" role="alert"><?= escape($error) ?></p>
        <?php elseif ($notice !== null): ?>
            <p class="dc-card dc-request-notice" role="status"><?= escape($notice) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($available && !$staff && !$canRequest): ?>
        <p class="dc-intro">Activate your <a href="account.php">student account</a> before requesting university documents.</p>
    <?php elseif ($available): ?>
        <div class="dc-request-layout<?= $staff ? '' : ' dc-request-layout-student' ?>">
            <?php if (!$staff && $groups !== []): ?>
                <?php require dirname(__DIR__) . '/includes/request-form.php'; ?>
            <?php elseif (!$staff): ?>
                <p class="dc-card">No document types are currently available to request.</p>
            <?php endif; ?>
            <section class="dc-request-list" aria-label="<?= $staff ? 'Registrar request queue' : 'Request history' ?>">
                <?php if (!$staff): ?>
                    <h2>Request History</h2>
                <?php endif; ?>
                <?php if ($staff): ?>
                    <form class="dc-card dc-account-form dc-request-filter" action="requests.php" method="get">
                        <div class="dc-form-row">
                            <div class="dc-field">
                                <label for="search">Reference or student number</label>
                                <input id="search" name="search" maxlength="30" value="<?= escape($search) ?>">
                            </div>
                            <div class="dc-field">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All statuses</option>
                                    <?php foreach (REQUEST_STATUSES as $option): ?>
                                        <option<?= $option === $status ? ' selected' : '' ?>><?= escape($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit">Filter Requests</button>
                    </form>
                <?php endif; ?>

                <?php if ($requests === []): ?>
                    <p class="dc-card">No requests to display.</p>
                <?php endif; ?>

                <?php foreach (array_slice($requests, 0, 20) as $request): ?>
                    <?php require dirname(__DIR__) . '/includes/request-card.php'; ?>
                <?php endforeach; ?>

                <?php if ($page > 1 || count($requests) > 20): ?>
                    <?php $filters = ['status' => $status, 'search' => $staff ? $search : '']; ?>
                    <nav class="dc-request-actions" aria-label="Request pages">
                        <?php if ($page > 1): ?>
                            <a href="?<?= escape(http_build_query($filters + ['page' => $page - 1])) ?>">Previous</a>
                        <?php endif; ?>
                        <?php if (count($requests) > 20 && $page < 1000000): ?>
                            <a href="?<?= escape(http_build_query($filters + ['page' => $page + 1])) ?>">Next</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
