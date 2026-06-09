<?php
declare(strict_types=1);

namespace App;

use Exception;
use InvalidArgumentException;

class UploadController
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function upload(): void
    {
        header('Content-Type: application/json');

        $passwordHeader = $_SERVER['HTTP_X_UPLOAD_PASSWORD'] ?? '';
        $uploadPassword = getenv('UPLOAD_PASSWORD') ?: 'mogul360secret';

        if (empty($passwordHeader) || $passwordHeader !== $uploadPassword) {
            http_response_code(401);
            echo json_encode(['error' => 'Contraseña de carga incorrecta o ausente.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $month = $_POST['month'] ?? '';
        $yearVal = $_POST['year'] ?? '';

        $year = $yearVal !== '' ? intval($yearVal) : 0;

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            echo json_encode(['error' => 'Error al subir el archivo. Código de error: ' . $errCode], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tmpName = $_FILES['file']['tmp_name'];

        try {
            $parser = new ExcelParser($this->db);
            $parser->parseAndSave($tmpName);

            $connection = $this->db->getConnection();
            $connection->exec('DELETE FROM report_metadata');

            $stmt = $connection->prepare('INSERT INTO report_metadata (month, year) VALUES (:month, :year)');
            $stmt->execute([
                'month' => $month,
                'year' => $year
            ]);

            http_response_code(200);
            echo json_encode(['message' => 'Archivo procesado y guardado correctamente.'], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno al procesar los datos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
