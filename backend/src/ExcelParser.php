<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PDO;
use Exception;

class ExcelParser
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function parseAndSave(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Excel file not found at: " . $filePath);
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Failed to read Excel file: " . $e->getMessage());
        }

        $sheetNames = $spreadsheet->getSheetNames();
        if (!in_array('Perfiles Organico', $sheetNames) || !in_array('Perfiles Pagos', $sheetNames)) {
            throw new InvalidArgumentException("Faltan hojas requeridas en el Excel: Perfiles Organico o Perfiles Pagos");
        }

        $organicSheet = $spreadsheet->getSheetByName('Perfiles Organico');
        $paidSheet = $spreadsheet->getSheetByName('Perfiles Pagos');

        $organicRecords = $this->parseSheet($organicSheet, true);
        $paidRecords = $this->parseSheet($paidSheet, false);

        $connection = $this->db->getConnection();

        try {
            $connection->beginTransaction();

            $connection->exec('DELETE FROM organic_campaign');
            $connection->exec('DELETE FROM paid_campaign');

            $sqlOrganic = 'INSERT INTO organic_campaign (
                mencion_id, semana, usuario, plataforma, link_publicacion, categoria_perfil,
                views_semana, aumento_views, likes, compartidos, comentarios, guardados, sentiment
            ) VALUES (
                :mencion_id, :semana, :usuario, :plataforma, :link_publicacion, :categoria_perfil,
                :views_semana, :aumento_views, :likes, :compartidos, :comentarios, :guardados, :sentiment
            )';
            $stmtOrganic = $connection->prepare($sqlOrganic);
            foreach ($organicRecords as $record) {
                $stmtOrganic->execute($record);
            }

            $sqlPaid = 'INSERT INTO paid_campaign (
                mencion_id, semana, usuario, plataforma, link_publicacion, categoria,
                views_semana, aumento_views, likes, compartidos, comentarios, guardados, sentiment
            ) VALUES (
                :mencion_id, :semana, :usuario, :plataforma, :link_publicacion, :categoria,
                :views_semana, :aumento_views, :likes, :compartidos, :comentarios, :guardados, :sentiment
            )';
            $stmtPaid = $connection->prepare($sqlPaid);
            foreach ($paidRecords as $record) {
                $stmtPaid->execute($record);
            }

            $connection->commit();
            return true;
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function parseSheet(Worksheet $sheet, bool $isOrganic): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $headerRowIdx = null;
        $headers = [];

        foreach ($rows as $idx => $row) {
            $cleanValues = array_map(fn($v) => $v !== null ? strtolower(trim((string)$v)) : '', $row);
            if (in_array('usuario', $cleanValues) || in_array('semana', $cleanValues)) {
                $headerRowIdx = $idx;
                foreach ($row as $colKey => $colVal) {
                    if ($colVal !== null) {
                        $headers[$colKey] = trim((string)$colVal);
                    }
                }
                break;
            }
        }

        if ($headerRowIdx === null) {
            throw new InvalidArgumentException("No se pudo encontrar la fila de cabecera con 'Usuario' o 'Semana'.");
        }

        $records = [];
        $dataRows = array_slice($rows, $headerRowIdx, null, true);

        foreach ($dataRows as $row) {
            $linkKey = $this->findKeyByHeader($headers, 'Link publicación');
            $link = $linkKey ? $this->cleanString($row[$linkKey]) : '';
            
            if (!$link || $link === '-') {
                continue;
            }

            $mencionKey = $this->findKeyByHeader($headers, 'nº de Menciones');
            if (!$mencionKey) {
                $mencionKey = $this->findKeyByHeader($headers, 'Nº de menciones');
            }
            $mencionId = $mencionKey ? $this->parseMetric($row[$mencionKey]) : 0;

            $semanaKey = $this->findKeyByHeader($headers, 'Semana');
            $semana = $semanaKey ? $this->parseMetric($row[$semanaKey]) : 0;

            $usuarioKey = $this->findKeyByHeader($headers, 'Usuario');
            $usuario = $usuarioKey ? $this->cleanString($row[$usuarioKey]) : '';

            $plataformaKey = $this->findKeyByHeader($headers, 'Plataforma');
            $plataforma = $plataformaKey ? $this->cleanString($row[$plataformaKey]) : '';

            if ($isOrganic) {
                $catKey = $this->findKeyByHeader($headers, "Categoría de\nPerfil");
                if (!$catKey) {
                    $catKey = $this->findKeyByHeader($headers, "Categoría de Perfil");
                }
                $categoriaPerfil = $catKey ? $this->cleanString($row[$catKey]) : '';
            } else {
                $catKey = $this->findKeyByHeader($headers, 'Categoría');
                $categoria = $catKey ? $this->cleanString($row[$catKey]) : '';
            }

            $viewsKey = $this->findKeyByHeader($headers, 'Views semana actual');
            $viewsSemana = $viewsKey ? $this->parseMetric($row[$viewsKey]) : 0;

            $aumentoKey = $this->findKeyByHeader($headers, 'Aumento de views vs semana anterior');
            $aumentoViews = $aumentoKey ? $this->parseMetric($row[$aumentoKey]) : 0;

            $likesKey = $this->findKeyByHeader($headers, 'Likes');
            $likes = $likesKey ? $this->parseMetric($row[$likesKey]) : 0;

            $compKey = $this->findKeyByHeader($headers, 'Compartido');
            $compartidos = $compKey ? $this->parseMetric($row[$compKey]) : 0;

            $comKey = $this->findKeyByHeader($headers, "Cantidad \nde comentarios");
            if (!$comKey) {
                $comKey = $this->findKeyByHeader($headers, "Cantidad de comentarios");
            }
            $comentarios = $comKey ? $this->parseMetric($row[$comKey]) : 0;

            $gKey = $this->findKeyByHeader($headers, 'Guardados');
            $guardados = $gKey ? $this->parseMetric($row[$gKey]) : 0;

            $sentKey = $this->findKeyByHeader($headers, "Promedio\nSentiment");
            if (!$sentKey) {
                $sentKey = $this->findKeyByHeader($headers, "Promedio Sentiment");
            }
            $sentiment = $sentKey ? strtoupper($this->cleanString($row[$sentKey])) : 'NEUTRO';
            if (!$sentiment) {
                $sentiment = 'NEUTRO';
            }

            $record = [
                'mencion_id' => $mencionId,
                'semana' => $semana,
                'usuario' => $usuario,
                'plataforma' => $plataforma,
                'link_publicacion' => $link,
                'views_semana' => $viewsSemana,
                'aumento_views' => $aumentoViews,
                'likes' => $likes,
                'compartidos' => $compartidos,
                'comentarios' => $comentarios,
                'guardados' => $guardados,
                'sentiment' => $sentiment,
            ];

            if ($isOrganic) {
                $record['categoria_perfil'] = $categoriaPerfil;
            } else {
                $record['categoria'] = $categoria;
            }

            $records[] = $record;
        }

        return $records;
    }

    private function findKeyByHeader(array $headers, string $headerName): ?string
    {
        $cleanSearch = strtolower(str_replace(["\r", "\n"], ' ', $headerName));
        foreach ($headers as $colKey => $val) {
            $cleanVal = strtolower(str_replace(["\r", "\n"], ' ', $val));
            if ($cleanVal === $cleanSearch) {
                return $colKey;
            }
        }
        return null;
    }

    private function cleanString(mixed $val): string
    {
        if ($val === null) {
            return '';
        }
        return trim((string)$val);
    }

    private function parseMetric(mixed $val): int
    {
        if ($val === null) {
            return 0;
        }

        $valStr = strtoupper(trim((string)$val));
        if ($valStr === '-' || $valStr === '') {
            return 0;
        }

        $multiplier = 1;
        if (str_ends_with($valStr, 'K')) {
            $multiplier = 1000;
            $valStr = substr($valStr, 0, -1);
        } elseif (str_ends_with($valStr, 'M')) {
            $multiplier = 1000000;
            $valStr = substr($valStr, 0, -1);
        }

        $valStr = preg_replace('/[^\d.-]/', '', $valStr);

        if (str_contains($valStr, '.')) {
            return (int)(floatval($valStr) * $multiplier);
        }

        return intval($valStr) * $multiplier;
    }
}
