<form class="dc-card dc-account-form dc-request-filter" method="post" action="admin.php?section=catalog">
    <?php if ($document['doc_type_id'] !== ''): ?>
        <h3><?= escape($document['type_name']) ?></h3>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_document">
    <input type="hidden" name="doc_type_id" value="<?= escape((string) $document['doc_type_id']) ?>">
    <label>Document code
        <input name="type_code" maxlength="20" value="<?= escape($document['type_code']) ?>" required>
    </label>
    <label>Document name
        <input name="type_name" maxlength="100" value="<?= escape($document['type_name']) ?>" required>
    </label>
    <label>Document group
        <select name="group_code" required>
            <option value="A"<?= $document['group_code'] === 'A' ? ' selected' : '' ?>>Group A · Academic year and term required</option>
            <option value="B"<?= $document['group_code'] === 'B' ? ' selected' : '' ?>>Group B · General records</option>
        </select>
    </label>
    <label>Description
        <textarea name="description" rows="2" maxlength="255"><?= escape($document['description'] ?? '') ?></textarea>
    </label>
    <label>Availability
        <select name="is_active">
            <option value="1"<?= $document['is_active'] ? ' selected' : '' ?>>Available for requests</option>
            <option value="0"<?= !$document['is_active'] ? ' selected' : '' ?>>Inactive</option>
        </select>
    </label>
    <button type="submit"><?= $document['doc_type_id'] === '' ? 'Add Document Type' : 'Save Document Type' ?></button>
</form>
