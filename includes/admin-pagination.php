<?php if ($page > 1 || $hasNext): ?>
    <nav class="dc-request-actions" aria-label="Administration pages">
        <?php if ($page > 1): ?>
            <a href="?<?= escape(http_build_query($paginationFilters + ['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($hasNext && $page < 1000000): ?>
            <a href="?<?= escape(http_build_query($paginationFilters + ['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
