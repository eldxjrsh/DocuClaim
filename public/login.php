<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirectTo('account.php');
}

$identifier = strtoupper(trim(postValue('identifier_number')));
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = postValue('password');
    if (!validCsrfToken()) {
        http_response_code(403);
        $error = 'The form expired. Please try again.';
    } elseif (!preg_match('/\A[A-Z0-9-]{4,30}\z/', $identifier) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
        http_response_code(422);
        $error = 'Enter your student number or employee ID and password.';
    } else {
        try {
            if (signIn(connectDatabase(), $identifier, $password, $_SERVER['REMOTE_ADDR'] ?? '')) {
                redirectTo('account.php');
            }
            http_response_code(401);
            $error = 'The ID or password was incorrect, or the account is unavailable.';
        } catch (OverflowException $exception) {
            http_response_code(429);
            $error = $exception->getMessage();
        } catch (RuntimeException $exception) {
            error_log('DocuClaim sign-in failed (code ' . $exception->getCode() . ').');
            http_response_code(503);
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}

$pageTitle = 'Sign In';
require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="container dc-main">
        <h1>Sign In</h1>
        <form class="dc-card dc-account-form" method="post" action="login.php">
            <?php if ($error !== null): ?>
                <p class="dc-form-error" role="alert"><?= escape($error) ?></p>
            <?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">

            <label for="identifier_number">Student number or employee ID</label>
            <input id="identifier_number" name="identifier_number" value="<?= escape($identifier) ?>"
                   autocomplete="username" maxlength="30" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" maxlength="72" required>

            <button type="submit">Sign In</button>
            <p>New student? <a href="register.php">Create an account</a></p>
        </form>
    </main>
</body>
</html>
