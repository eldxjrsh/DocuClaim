<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/catalog.php';

try {
    $groups = getDocumentCatalog();
} catch (PDOException $exception) {
    error_log('DocuClaim catalog query failed (SQLSTATE ' . $exception->getCode() . ').');
    http_response_code(503);
    $groups = null;
}

$pageTitle = 'Document Catalog';
require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="container dc-main">
        <h1>Document Catalog</h1>
        <p class="dc-intro">Browse official university documents and see which records require an academic year and term.</p>

        <?php if ($groups === null): ?>
            <p class="dc-card" role="status">The catalog is temporarily unavailable. Please try again later.</p>
        <?php elseif ($groups === []): ?>
            <p class="dc-card">There are currently no documents in the catalog.</p>
        <?php else: ?>
            <div class="dc-catalog">
                <?php foreach ($groups as $group): ?>
                    <section class="dc-card" aria-labelledby="group-<?= escape($group['code']) ?>">
                        <h2 id="group-<?= escape($group['code']) ?>"><?= escape($group['name']) ?></h2>
                        <p class="dc-requirements"><?= escape($group['requirements']) ?></p>
                        <ul class="dc-documents">
                            <?php foreach ($group['documents'] as $document): ?>
                                <li>
                                    <h3><?= escape($document['type_name']) ?></h3>
                                    <?php if ($document['description'] !== null): ?>
                                        <p><?= escape($document['description']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
