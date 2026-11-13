<?php
declare(strict_types=1);

function getDocumentCatalog(): array
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'docuclaim_catalog';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS');

    $connection = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password === false ? '' : $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $documents = $connection->query(
        'SELECT dt.group_code, dt.type_name, dt.description,
                dg.group_name, dg.requires_academic_year, dg.requires_academic_term
         FROM document_types dt
         INNER JOIN document_groups dg ON dg.group_code = dt.group_code
         WHERE dt.is_active = 1
         ORDER BY dt.group_code ASC, dt.type_name ASC'
    );

    $groups = [];
    foreach ($documents as $document) {
        $code = $document['group_code'];
        if (!isset($groups[$code])) {
            $requirements = match ([(bool) $document['requires_academic_year'], (bool) $document['requires_academic_term']]) {
                [true, true] => 'Academic year and term required',
                [true, false] => 'Academic year required',
                [false, true] => 'Academic term required',
                default => 'No academic year or term required',
            };

            $groups[$code] = [
                'code' => $code,
                'name' => $document['group_name'],
                'requirements' => $requirements,
                'documents' => [],
            ];
        }

        $groups[$code]['documents'][] = $document;
    }

    return $groups;
}
