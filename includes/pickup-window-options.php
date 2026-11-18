<?php foreach ($windows as $window): ?>
    <?php if ((int) $window['remaining_slots'] > 0): ?>
        <option value="<?= (int) $window['window_id'] ?>">
            <?= escape($window['pickup_date']) ?>, <?= escape(substr($window['start_time'], 0, 5)) ?>–<?= escape(substr($window['end_time'], 0, 5)) ?>
            (<?= (int) $window['remaining_slots'] ?> slots remaining)
        </option>
    <?php endif; ?>
<?php endforeach; ?>
