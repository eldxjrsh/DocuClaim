    <form class="dc-card dc-account-form" method="post" action="requests.php#request-feedback">
        <h2>New Request</h2>
        <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
        <input type="hidden" name="action" value="submit">

        <label for="doc_type_id">Document type</label>
        <select id="doc_type_id" name="doc_type_id" required>
            <option value="">Choose a document</option>
            <?php foreach ($groups as $group): ?>
                <optgroup label="<?= escape($group['name']) ?>">
                    <?php foreach ($group['documents'] as $document): ?>
                        <?php $id = (string) $document['doc_type_id']; ?>
                        <option value="<?= escape($id) ?>"<?= $details['doc_type_id'] === $id ? ' selected' : '' ?>>
                            <?= escape($document['type_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>

        <label for="purpose">Purpose</label>
        <input id="purpose" name="purpose" required minlength="3" maxlength="255"
               value="<?= escape($details['purpose']) ?>">

        <p class="dc-form-hint dc-request-hint" id="academic-hint">Fill in the academic year and term for term-specific documents.</p>
        <div class="dc-form-row">
            <div class="dc-field">
                <label for="academic_year">Academic year</label>
                <input id="academic_year" name="academic_year" placeholder="YYYY-YYYY" maxlength="9"
                       aria-describedby="academic-hint" value="<?= escape($details['academic_year']) ?>">
            </div>
            <div class="dc-field">
                <label for="academic_term">Academic term</label>
                <select id="academic_term" name="academic_term" aria-describedby="academic-hint">
                    <option value="">Choose a term</option>
                    <?php foreach (REQUEST_TERMS as $term): ?>
                        <option<?= $details['academic_term'] === $term ? ' selected' : '' ?>><?= escape($term) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label for="student_note">Note (optional)</label>
        <textarea id="student_note" name="student_note" rows="3" maxlength="2000"><?= escape($details['student_note']) ?></textarea>
        <button type="submit">Submit Request</button>
    </form>
