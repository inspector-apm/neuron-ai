<?php

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$password = '';
$database = 'neuron_ai_test';

$pdo = new PDO("mysql:host=$host;port=$port", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($database));
$exists = $stmt->fetch();

if (!$exists) {
    echo "🛠️  Database '$database' does not exist, creating...\n";
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✅ Database '$database' created successfully.\n";
} else {
    echo "✅ Database '$database' already exists. Everything is ready.\n";
}
