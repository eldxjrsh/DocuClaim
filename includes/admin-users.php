<form class="dc-card dc-account-form dc-request-filter" method="get" action="admin.php">
    <input type="hidden" name="section" value="users">
    <label for="search">Name or identifier</label>
    <input id="search" name="search" maxlength="100" value="<?= escape($search) ?>">
    <label for="status">Account status</label>
    <select id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (ACCOUNT_STATUSES as $option): ?>
            <option<?= $status === $option ? ' selected' : '' ?>><?= escape($option) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Find Accounts</button>
</form>

<?php if ($accounts === []): ?>
    <p class="dc-card">No accounts to display.</p>
<?php endif; ?>
<?php foreach (array_slice($accounts, 0, 20) as $account): ?>
    <form class="dc-card dc-account-form dc-request-filter" method="post" action="admin.php?section=users">
        <h2><?= escape($account['first_name'] . ' ' . $account['last_name']) ?></h2>
        <p><?= escape($account['identifier_number']) ?></p>
        <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="identifier" value="<?= escape($account['identifier_number']) ?>">
        <input type="hidden" name="expected_role" value="<?= escape($account['role_slug']) ?>">
        <input type="hidden" name="expected_status" value="<?= escape($account['account_status']) ?>">
        <label>Role
            <select name="role" required>
                <?php foreach (['student' => 'Student', 'staff' => 'Registrar Staff', 'admin' => 'System Admin'] as $key => $label): ?>
                    <option value="<?= escape($key) ?>"<?= $account['role_slug'] === $key ? ' selected' : '' ?>><?= escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="account_status" required>
                <?php foreach (ACCOUNT_STATUSES as $option): ?>
                    <option<?= $account['account_status'] === $option ? ' selected' : '' ?>><?= escape($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Save Account</button>
    </form>
<?php endforeach; ?>
<?php
$hasNext = count($accounts) > 20;
$paginationFilters = ['section' => 'users', 'search' => $search, 'status' => $status];
require __DIR__ . '/admin-pagination.php';
?>
