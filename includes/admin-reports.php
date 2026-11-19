<form class="dc-card dc-account-form dc-request-filter" method="get" action="admin.php">
    <input type="hidden" name="section" value="reports">
    <div class="dc-form-row">
        <div class="dc-field">
            <label for="from">From date</label>
            <input type="date" id="from" name="from" value="<?= escape($from) ?>">
        </div>
        <div class="dc-field">
            <label for="to">Through date</label>
            <input type="date" id="to" name="to" value="<?= escape($to) ?>">
        </div>
    </div>
    <button type="submit">View Report</button>
</form>
<section aria-labelledby="request-summary">
    <h2 id="request-summary">Requests by Submission Date</h2>
    <div class="dc-table-scroll" tabindex="0" role="region" aria-label="Request report">
        <table class="dc-table">
            <thead><tr><th scope="col">Document</th><th scope="col">Total</th><th scope="col">Pending</th><th scope="col">Preparing</th><th scope="col">Ready</th><th scope="col">Completed</th><th scope="col">Rejected or cancelled</th></tr></thead>
            <tbody>
                <?php foreach ($report['documents'] as $row): ?>
                    <tr>
                        <th scope="row"><?= escape($row['type_name']) ?></th>
                        <?php foreach (['total', 'pending', 'preparing', 'ready', 'completed', 'closed'] as $column): ?>
                            <td><?= (int) $row[$column] ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="dc-request-list" aria-labelledby="visit-summary">
    <h2 id="visit-summary">Visits by Pickup Date</h2>
    <?php if ($report['visits'] === []): ?>
        <p>No visits in this date range.</p>
    <?php endif; ?>
    <?php foreach ($report['visits'] as $visit): ?>
        <p class="dc-card"><?= escape($visit['appointment_status']) ?> · <?= (int) $visit['total'] ?> visits</p>
    <?php endforeach; ?>
</section>
