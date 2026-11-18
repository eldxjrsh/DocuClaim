<form class="dc-card dc-account-form" method="post" action="pickups.php#pickup-feedback">
    <h2>Create a Pickup Window</h2>
    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
    <input type="hidden" name="action" value="create_window">
    <label for="pickup_date">Pickup date</label>
    <input type="date" id="pickup_date" name="pickup_date" min="<?= escape(date('Y-m-d')) ?>" required>
    <div class="dc-form-row">
        <div class="dc-field">
            <label for="start_time">Start time</label>
            <input type="time" id="start_time" name="start_time" required>
        </div>
        <div class="dc-field">
            <label for="end_time">End time</label>
            <input type="time" id="end_time" name="end_time" required>
        </div>
    </div>
    <label for="capacity">Maximum visits</label>
    <input type="number" id="capacity" name="capacity" min="1" max="1000" required>
    <button type="submit">Create Window</button>
</form>
