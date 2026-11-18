<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'mariadb';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'nada';
$username = getenv('DB_USERNAME') ?: 'nada';
$password = getenv('DB_PASSWORD') ?: 'nada';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$migrations = glob($migrationDirectory . '/*.sql') ?: [];
sort($migrations);

$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(191) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$applied = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

foreach ($migrations as $migration) {
    $version = basename($migration);
    if (in_array($version, $applied, true)) {
        continue;
    }

    try {
        $pdo->exec((string) file_get_contents($migration));
        $statement = $pdo->prepare('INSERT INTO migrations (version) VALUES (:version)');
        $statement->execute(['version' => $version]);
        printf("Applied %s\n", $version);
    } catch (Throwable $exception) {
        throw $exception;
    }
}

$superadminEmail = getenv('SUPERADMIN_EMAIL') ?: 'admin@nada.local';
$superadminPassword = getenv('SUPERADMIN_PASSWORD') ?: '';

if ($superadminPassword !== '') {
    $insert = $pdo->prepare('INSERT INTO users (email, name, password_hash, active, is_superadmin) VALUES (:email, :name, :password_hash, 1, 1) ON DUPLICATE KEY UPDATE is_superadmin = 1');
    $insert->execute([
        'email' => $superadminEmail,
        'name' => 'Superadmin Nada',
        'password_hash' => password_hash($superadminPassword, PASSWORD_DEFAULT),
    ]);
    printf("Superadmin ready: %s\n", $superadminEmail);
}
