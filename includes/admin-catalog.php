<h2>Add a Document Type</h2>
<?php
$document = ['doc_type_id' => '', 'group_code' => 'B', 'type_code' => '', 'type_name' => '', 'description' => '', 'is_active' => 1];
require __DIR__ . '/admin-document-form.php';
?>
<h2>Document Types</h2>
<?php foreach ($documents as $document): ?>
    <?php require __DIR__ . '/admin-document-form.php'; ?>
<?php endforeach; ?>
