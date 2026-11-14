<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/activation.php';

if (!isset($_SESSION['user_id'])) {
    redirectTo('login.php');
}

$user = null;
$error = null;
$activated = $_SESSION['activation_complete'] ?? false;
$slip = $_SESSION['activation_slip'] ?? null;
unset($_SESSION['activation_complete'], $_SESSION['activation_slip']);

try {
    $db = connectDatabase();
    $user = currentAccount($db);
    if ($user === null) {
        $_SESSION = [];
        redirectTo('login.php');
    }

    $staff = $user['role_slug'] === 'staff';
    $pending = !$staff && $user['account_status'] === 'Pending Activation';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validCsrfToken()) {
            http_response_code(403);
            $error = 'The form expired. Please try again.';
        } elseif ($staff && postValue('action') === 'issue') {
            $identifier = strtoupper(trim(postValue('student_number')));
            $issued = preg_match('/\A[A-Z0-9-]{4,30}\z/', $identifier)
                ? issueActivationSlip($db, (int) $user['user_id'], $identifier)
                : null;
            if ($issued !== null) {
                $_SESSION['activation_slip'] = $issued;
                redirectTo('account.php');
            }
            http_response_code(422);
            $error = 'No student account is pending activation for that number.';
        } elseif ($pending && postValue('action') === 'activate') {
            $code = strtoupper(preg_replace('/\s+/', '', postValue('activation_code')) ?? '');
            if (preg_match('/\AACT-(?:[0-9A-F]{5}-){3}[0-9A-F]{5}\z/', $code)
                && activateStudent($db, (int) $user['user_id'], $code)) {
                $_SESSION['activation_complete'] = true;
                redirectTo('account.php');
            }
            http_response_code(422);
            $error = 'The activation code is invalid or expired. Check your slip or visit the Registrar.';
        } else {
            http_response_code(403);
            $error = 'This action is unavailable for your account.';
        }
    }
} catch (RuntimeException $exception) {
    error_log('DocuClaim account request failed (code ' . $exception->getCode() . ').');
    http_response_code(503);
    $error = 'The account page is temporarily unavailable. Please try again later.';
}

$pageTitle = 'Account';
require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="container dc-main">
        <h1 class="dc-no-print">Account</h1>
        <?php if ($error !== null): ?>
            <p class="dc-card dc-form-error dc-no-print" role="alert"><?= escape($error) ?></p>
        <?php endif; ?>

        <?php if ($user !== null): ?>
            <section class="dc-card dc-account-form dc-no-print" aria-labelledby="account-details">
                <h2 id="account-details"><?= escape($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <p><?= escape($user['identifier_number']) ?> · <?= escape($user['role_name']) ?></p>
                <p>Status: <?= escape($user['account_status']) ?></p>
                <?php if ($activated && !$staff): ?>
                    <p role="status">Your student account is active.</p>
                <?php endif; ?>

                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                    <button type="submit">Sign Out</button>
                </form>
            </section>

            <?php if ($staff): ?>
                <form class="dc-card dc-account-form dc-no-print" method="post" action="account.php">
                    <h2>Issue an Activation Slip</h2>
                    <p>Verify the student’s university ID in person before issuing an activation slip.</p>
                    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="issue">

                    <label for="student_number">Student number</label>
                    <input id="student_number" name="student_number" minlength="4" maxlength="30" required>
                    <button type="submit">Issue Slip</button>
                </form>

                <?php if ($slip !== null): ?>
                    <section class="dc-card dc-account-form" aria-labelledby="activation-slip">
                        <h2 id="activation-slip">Student Activation Slip</h2>
                        <p><?= escape($slip['name']) ?> · <?= escape($slip['identifier']) ?></p>
                        <p>Sign in with your student number and enter this code. The code is valid for seven days after issue.</p>
                        <p><strong>Activation code</strong> <code><?= escape($slip['code']) ?></code></p>
                        <button class="dc-no-print" type="button" onclick="window.print()">Print Slip</button>
                    </section>
                <?php endif; ?>
            <?php elseif ($pending): ?>
                <form class="dc-card dc-account-form dc-no-print" method="post" action="account.php">
                    <h2>Activate Your Account</h2>
                    <p>Bring your university ID to the Registrar and enter the code from your activation slip.</p>
                    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="activate">

                    <label for="activation_code">Activation code</label>
                    <input id="activation_code" name="activation_code" maxlength="40" required>
                    <button type="submit">Activate Account</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
