<?php
declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | DocuClaim</title>
    <link rel="stylesheet" href="assets/css/docuclaim.css">
</head>
<body>
    <header class="dc-navbar">
        <div class="container dc-navbar-inner">
            <a class="dc-brand" href="./">DocuClaim</a>
            <span class="dc-office">Office of the University Registrar</span>
            <nav class="dc-nav" aria-label="Main navigation">
                <a href="./"<?= $pageTitle === 'Document Catalog' ? ' aria-current="page"' : '' ?>>Catalog</a>
                <a href="register.php"<?= $pageTitle === 'Student Registration' ? ' aria-current="page"' : '' ?>>Student Registration</a>
            </nav>
        </div>
    </header>
