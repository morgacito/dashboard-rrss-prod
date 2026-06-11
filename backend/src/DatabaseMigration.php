<?php
declare(strict_types=1);

namespace App;

use PDOException;
use RuntimeException;

class DatabaseMigration
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function migrate(): bool
    {
        $schemaPath = __DIR__ . '/../schema.sql';
        if (!file_exists($schemaPath)) {
            throw new RuntimeException("Schema file not found at: " . $schemaPath);
        }

        $sql = file_get_contents($schemaPath);
        $connection = $this->db->getConnection();

        try {
            $connection->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw $e;
        }
    }
}
