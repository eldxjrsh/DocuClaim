<?php
$reference = $appointment['booking_reference'];
$scheduled = $appointment['appointment_status'] === 'Scheduled';
$collectible = $staff && $scheduled && (bool) $appointment['can_collect'];
?>
<article class="dc-card dc-request-card" aria-labelledby="<?= escape($reference) ?>">
    <header>
        <h3 id="<?= escape($reference) ?>"><?= escape($reference) ?></h3>
        <span class="dc-request-status"><?= escape($appointment['appointment_status']) ?></span>
    </header>
    <p><?= escape($appointment['pickup_date']) ?>, <?= escape(substr($appointment['start_time'], 0, 5)) ?>–<?= escape(substr($appointment['end_time'], 0, 5)) ?></p>
    <?php if ($staff): ?>
        <p><?= escape($appointment['student_name']) ?> · <?= escape($appointment['identifier_number']) ?></p>
    <?php endif; ?>
    <?php if ($appointment['cancellation_reason'] !== null): ?>
        <p><?= escape($appointment['cancellation_reason']) ?></p>
    <?php endif; ?>

    <?php if ($collectible): ?>
        <form class="dc-account-form" method="post" action="pickups.php#pickup-feedback">
            <p>Verify the student’s university ID and select only the documents physically handed over. Recording collection closes this visit. Unselected documents can be booked again.</p>
            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="collect">
            <input type="hidden" name="reference" value="<?= escape($reference) ?>">
            <fieldset class="dc-pickup-documents">
                <legend>Documents handed over</legend>
                <?php foreach ($appointment['documents'] as $document): ?>
                    <?php if ($document['collection_state'] === 'Attached'): ?>
                        <label class="dc-pickup-checkbox">
                            <input type="checkbox" name="documents[]" value="<?= (int) $document['request_id'] ?>">
                            <span><?= escape($document['type_name']) ?> · <?= escape($document['reference_no']) ?></span>
                        </label>
                    <?php else: ?>
                        <p><?= escape($document['type_name']) ?> · <?= escape($document['collection_state']) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit">Record Collection</button>
        </form>
    <?php else: ?>
        <ul class="dc-pickup-document-list">
            <?php foreach ($appointment['documents'] as $document): ?>
                <li><?= escape($document['type_name']) ?> · <?= escape($document['reference_no']) ?> · <?= escape($document['collection_state']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!$staff && $scheduled && $appointment['can_change']): ?>
        <form class="dc-account-form" method="post" action="pickups.php#pickup-feedback">
            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
            <input type="hidden" name="reference" value="<?= escape($reference) ?>">
            <label for="<?= escape($reference) ?>-window">Move to a different window</label>
            <select id="<?= escape($reference) ?>-window" name="window_id">
                <option value="">Choose another pickup window</option>
                <?php require __DIR__ . '/pickup-window-options.php'; ?>
            </select>
            <div class="dc-request-actions">
                <button type="submit" name="action" value="move">Reschedule</button>
                <button class="dc-secondary-button" type="submit" name="action" value="cancel">Cancel Visit</button>
            </div>
        </form>
    <?php endif; ?>
</article>
