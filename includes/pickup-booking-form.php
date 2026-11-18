<section class="dc-card dc-account-form" aria-labelledby="book-pickup">
    <h2 id="book-pickup">Book a Pickup</h2>
    <p>One visit uses one slot. Select the ready documents you will collect together.</p>
    <?php if ($ready === []): ?>
        <p>No ready documents are available for booking. Check <a href="requests.php">your requests</a> for updates.</p>
    <?php elseif (array_filter($windows, fn (array $window): bool => (int) $window['remaining_slots'] > 0) === []): ?>
        <p>No upcoming pickup windows are available. Please check again later.</p>
    <?php else: ?>
        <form class="dc-account-form" method="post" action="pickups.php#pickup-feedback">
            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="book">
            <fieldset class="dc-pickup-documents">
                <legend>Ready documents</legend>
                <?php foreach ($ready as $document): ?>
                    <label class="dc-pickup-checkbox">
                        <input type="checkbox" name="documents[]" value="<?= (int) $document['request_id'] ?>">
                        <span><?= escape($document['type_name']) ?> · <?= escape($document['reference_no']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <label for="window_id">Pickup window</label>
            <select id="window_id" name="window_id" required>
                <option value="">Choose a pickup window</option>
                <?php require __DIR__ . '/pickup-window-options.php'; ?>
            </select>
            <button type="submit">Book Pickup</button>
        </form>
    <?php endif; ?>
</section>
