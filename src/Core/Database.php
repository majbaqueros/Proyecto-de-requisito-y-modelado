<?php
declare(strict_types=1);
namespace App\Core;
use PDO;

final class Database {
    public static function connect(): PDO {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'clinica';
        $username = getenv('DB_USER') ?: 'clinica';
        $password = getenv('DB_PASSWORD') ?: '';
        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$name}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
}
