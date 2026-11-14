<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/registration.php';

require_once dirname(__DIR__) . '/includes/session.php';

if (isset($_SESSION['user_id'])) {
    redirectTo('account.php');
}

$registered = $_SESSION['registration_complete'] ?? false;
unset($_SESSION['registration_complete']);

$details = [
    'student_number' => strtoupper(trim(postValue('student_number'))),
    'first_name' => trim(postValue('first_name')),
    'middle_name' => trim(postValue('middle_name')),
    'last_name' => trim(postValue('last_name')),
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registered = false;
    if (!validCsrfToken()) {
        http_response_code(403);
        $error = 'The form expired. Please enter your password and submit it again.';
    } else {
        try {
            $error = registerStudent($details, postValue('password'), postValue('confirm_password'));
            if ($error === null) {
                $_SESSION['registration_complete'] = true;
                redirectTo('register.php');
            }
            http_response_code(422);
        } catch (RuntimeException $exception) {
            error_log('DocuClaim registration failed (' . $exception::class . ', code ' . $exception->getCode() . ').');
            http_response_code(503);
            $error = 'Registration is temporarily unavailable. Please try again later.';
        }
    }
}

$pageTitle = 'Student Registration';
require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="container dc-main">
        <h1>Student Registration</h1>
        <?php if ($registered): ?>
            <div class="dc-card dc-account-form" role="status">
                <h2>Registration received</h2>
                <p>Bring your university ID to the Registrar for in-person verification and an activation slip.</p>
                <a href="login.php">Sign in to your account</a>
            </div>
        <?php else: ?>
            <p class="dc-intro">Register with your official student number. The Registrar verifies your identity before activating your account.</p>

            <form class="dc-card dc-account-form" method="post" action="register.php">
                <?php if ($error !== null): ?>
                    <p class="dc-form-error" role="alert"><?= escape($error) ?></p>
                <?php endif; ?>

                <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">

                <label for="student_number">Official student number</label>
                <input id="student_number" name="student_number" value="<?= escape($details['student_number']) ?>"
                       autocomplete="username" minlength="4" maxlength="30" required>

                <div class="dc-form-row">
                    <div class="dc-field">
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" value="<?= escape($details['first_name']) ?>"
                               autocomplete="given-name" maxlength="100" required>
                    </div>
                    <div class="dc-field">
                        <label for="last_name">Last name</label>
                        <input id="last_name" name="last_name" value="<?= escape($details['last_name']) ?>"
                               autocomplete="family-name" maxlength="100" required>
                    </div>
                </div>

                <label for="middle_name">Middle name (optional)</label>
                <input id="middle_name" name="middle_name" value="<?= escape($details['middle_name']) ?>"
                       autocomplete="additional-name" maxlength="100">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       minlength="8" maxlength="72" aria-describedby="password-hint" required>
                <p class="dc-form-hint" id="password-hint">Use at least 8 characters and no more than 72 bytes.</p>

                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                       minlength="8" maxlength="72" required>

                <button type="submit">Register</button>
                <p>Already registered? <a href="login.php">Sign in</a></p>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
