<?php
// Só para os testes automáticos: converte database/schema.sql (MySQL) para SQLite.
// Uso: php scripts/schema-sqlite.php > /tmp/schema-sqlite.sql
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/^SET .*;$/m', '', $sql);
$sql = str_replace('INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
$sql = str_replace('INT UNSIGNED', 'INTEGER', $sql);
$sql = preg_replace('/\)\s*ENGINE=[^;]*;/', ');', $sql);
$sql = preg_replace('/UNIQUE KEY \w+ \(/', 'UNIQUE (', $sql);
$sql = preg_replace('/^\s*KEY \w+ \([^)]*\),?\s*$/m', '', $sql);
$sql = preg_replace('/,(\s*)\)\s*;/', '$1);', $sql);
$sql = preg_replace("/\n{2,}/", "\n", $sql);
echo $sql;
