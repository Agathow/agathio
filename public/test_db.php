<?php

require_once __DIR__ . '/../config/database.php';

$databaseName = $pdo
    ->query('SELECT DATABASE()')
    ->fetchColumn();

echo 'SIGAP database connected: ' . e($databaseName);