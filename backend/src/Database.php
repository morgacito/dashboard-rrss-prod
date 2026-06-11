<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private ?PDO $pdo = null;
    private float $connectionTime = 0;
    private float $totalQueryTime = 0;

    public function __construct(
        private ?string $host = null,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $dbname = null
    ) {
        $this->host = $host ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $this->username = $username ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $this->password = $password ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $this->dbname = $dbname ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: '';
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $start = microtime(true);
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->dbname);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            $this->connectionTime = (microtime(true) - $start) * 1000;
        }

        return $this->pdo;
    }

    public function getConnectionTime(): float
    {
        return $this->connectionTime;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);
        $pdo = $this->getConnection();
        if (empty($params)) {
            $stmt = $pdo->query($sql);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        $this->totalQueryTime += (microtime(true) - $start) * 1000;
        return $stmt;
    }

    public function getTotalQueryTime(): float
    {
        return $this->totalQueryTime;
    }

    public function addQueryTime(float $ms): void
    {
        $this->totalQueryTime += $ms;
    }
}
