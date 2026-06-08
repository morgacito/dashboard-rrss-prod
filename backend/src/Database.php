<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private ?string $host = null,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $dbname = null
    ) {
        $this->host = $host ?? getenv('DB_HOST') ?: '127.0.0.1';
        $this->username = $username ?? getenv('DB_USER') ?: 'root';
        $this->password = $password ?? getenv('DB_PASSWORD') ?: '';
        $this->dbname = $dbname ?? getenv('DB_NAME') ?: '';
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->dbname);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        }

        return $this->pdo;
    }
}
