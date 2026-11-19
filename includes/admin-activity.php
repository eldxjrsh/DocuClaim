<form class="dc-card dc-account-form dc-request-filter" method="get" action="admin.php">
    <input type="hidden" name="section" value="activity">
    <label for="event">Action type</label>
    <input id="event" name="event" maxlength="80" value="<?= escape($event) ?>" placeholder="For example, REQUEST_STATUS_CHANGED">
    <button type="submit">Filter Activity</button>
</form>
<?php if ($records === []): ?>
    <p class="dc-card">No activity records to display.</p>
<?php endif; ?>
<?php foreach (array_slice($records, 0, 20) as $record): ?>
    <article class="dc-card dc-request-card">
        <h2><?= escape(ucwords(strtolower(str_replace('_', ' ', $record['action_type'])))) ?></h2>
        <p><?= escape($record['timestamp']) ?> · <?= escape($record['identifier_number'] ?? 'System') ?></p>
        <?php if ($record['target_id'] !== null): ?>
            <p><strong>Record</strong> <?= escape($record['target_id']) ?></p>
        <?php endif; ?>
        <p><strong>IP address</strong> <?= escape($record['ip_address']) ?></p>
        <?php $details = json_decode($record['metadata'] ?? '{}', true) ?? []; ?>
        <?php if ($details !== []): ?>
            <dl class="dc-audit-details">
                <?php foreach ($details as $key => $value): ?>
                    <dt><?= escape(ucwords(str_replace('_', ' ', (string) $key))) ?></dt>
                    <dd><?= escape(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
<?php
$hasNext = count($records) > 20;
$paginationFilters = ['section' => 'activity', 'event' => $event];
require __DIR__ . '/admin-pagination.php';
?>
