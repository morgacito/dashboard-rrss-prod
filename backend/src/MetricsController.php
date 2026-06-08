<?php
declare(strict_types=1);

namespace App;

use PDO;
use Exception;

class MetricsController
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function getReportMetadata(): void
    {
        header('Content-Type: application/json');
        try {
            $connection = $this->db->getConnection();
            $stmt = $connection->query('SELECT month, year FROM report_metadata LIMIT 1');
            $meta = $stmt->fetch();

            if (!$meta) {
                echo json_encode(['month' => null, 'year' => null]);
                return;
            }

            echo json_encode([
                'month' => $meta['month'],
                'year' => (int)$meta['year']
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getSummary(): void
    {
        header('Content-Type: application/json');
        try {
            $semana = isset($_GET['semana']) && $_GET['semana'] !== '' ? (int)$_GET['semana'] : null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $orgSummary = ['count' => 0, 'likes' => 0, 'shares' => 0, 'saves' => 0, 'comments' => 0, 'views' => 0];
            $paidSummary = ['count' => 0, 'likes' => 0, 'shares' => 0, 'saves' => 0, 'comments' => 0, 'views' => 0];

            $connection = $this->db->getConnection();

            // 1. Organic summary
            if ($tipo === null || strtolower($tipo) === 'organic') {
                if ($pilar !== 'Influencers') { // Influencers es solo pago
                    $where = [];
                    $params = [];

                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Kiosco') {
                        $where[] = "categoria_perfil = 'Kiosco'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria_perfil != 'Kiosco'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT 
                                COUNT(*) as count,
                                SUM(likes) as likes,
                                SUM(compartidos) as shares,
                                SUM(guardados) as saves,
                                SUM(comentarios) as comments,
                                SUM(views_semana) as views
                            FROM organic_campaign
                            $whereSql";
                    
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    $res = $stmt->fetch();
                    if ($res && $res['count'] > 0) {
                        $orgSummary = [
                            'count' => (int)$res['count'],
                            'likes' => (int)($res['likes'] ?? 0),
                            'shares' => (int)($res['shares'] ?? 0),
                            'saves' => (int)($res['saves'] ?? 0),
                            'comments' => (int)($res['comments'] ?? 0),
                            'views' => (int)($res['views'] ?? 0),
                        ];
                    }
                }
            }

            // 2. Paid summary
            if ($tipo === null || strtolower($tipo) === 'paid') {
                if ($pilar !== 'Kiosco') { // Kiosco es solo orgánico
                    $where = [];
                    $params = [];

                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Influencers') {
                        $where[] = "categoria = 'PRESUPUESTO'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria = 'CANJE'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT 
                                COUNT(*) as count,
                                SUM(likes) as likes,
                                SUM(compartidos) as shares,
                                SUM(guardados) as saves,
                                SUM(comentarios) as comments,
                                SUM(views_semana) as views
                            FROM paid_campaign
                            $whereSql";
                    
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    $res = $stmt->fetch();
                    if ($res && $res['count'] > 0) {
                        $paidSummary = [
                            'count' => (int)$res['count'],
                            'likes' => (int)($res['likes'] ?? 0),
                            'shares' => (int)($res['shares'] ?? 0),
                            'saves' => (int)($res['saves'] ?? 0),
                            'comments' => (int)($res['comments'] ?? 0),
                            'views' => (int)($res['views'] ?? 0),
                        ];
                    }
                }
            }

            echo json_encode([
                'menciones_totales' => $orgSummary['count'] + $paidSummary['count'],
                'menciones_organicas' => $orgSummary['count'],
                'menciones_pagas' => $paidSummary['count'],
                'likes' => $orgSummary['likes'] + $paidSummary['likes'],
                'shares' => $orgSummary['shares'] + $paidSummary['shares'],
                'saves' => $orgSummary['saves'] + $paidSummary['saves'],
                'comments' => $orgSummary['comments'] + $paidSummary['comments'],
                'views' => $orgSummary['views'] + $paidSummary['views'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getChartsData(): void
    {
        header('Content-Type: application/json');
        try {
            $semana = isset($_GET['semana']) && $_GET['semana'] !== '' ? (int)$_GET['semana'] : null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $connection = $this->db->getConnection();

            // 1. Weekly evolution (mentions and engagement)
            $orgWeeks = [];
            if ($tipo === null || strtolower($tipo) === 'organic') {
                if ($pilar !== 'Influencers') {
                    $where = [];
                    $params = [];
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Kiosco') {
                        $where[] = "categoria_perfil = 'Kiosco'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria_perfil != 'Kiosco'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT 
                                semana,
                                COUNT(*) as count,
                                SUM(likes + compartidos + comentarios + guardados) as engagement
                            FROM organic_campaign
                            $whereSql
                            GROUP BY semana";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    $orgWeeks = $stmt->fetchAll();
                }
            }

            $paidWeeks = [];
            if ($tipo === null || strtolower($tipo) === 'paid') {
                if ($pilar !== 'Kiosco') {
                    $where = [];
                    $params = [];
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Influencers') {
                        $where[] = "categoria = 'PRESUPUESTO'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria = 'CANJE'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT 
                                semana,
                                COUNT(*) as count,
                                SUM(likes + compartidos + comentarios + guardados) as engagement
                            FROM paid_campaign
                            $whereSql
                            GROUP BY semana";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    $paidWeeks = $stmt->fetchAll();
                }
            }

            // Merge weekly data
            $weeklyData = [];
            foreach ($orgWeeks as $row) {
                $w = (int)$row['semana'];
                $weeklyData[$w] = [
                    'semana' => $w,
                    'organic_mentions' => (int)$row['count'],
                    'organic_engagement' => (int)($row['engagement'] ?? 0),
                    'paid_mentions' => 0,
                    'paid_engagement' => 0
                ];
            }
            foreach ($paidWeeks as $row) {
                $w = (int)$row['semana'];
                if (!isset($weeklyData[$w])) {
                    $weeklyData[$w] = [
                        'semana' => $w,
                        'organic_mentions' => 0,
                        'organic_engagement' => 0,
                        'paid_mentions' => (int)$row['count'],
                        'paid_engagement' => (int)($row['engagement'] ?? 0)
                    ];
                } else {
                    $weeklyData[$w]['paid_mentions'] = (int)$row['count'];
                    $weeklyData[$w]['paid_engagement'] = (int)($row['engagement'] ?? 0);
                }
            }
            ksort($weeklyData);
            $weeklyChart = array_values($weeklyData);

            // 2. Sentiments distribution
            $sentimentsCount = ['POSITIVO' => 0, 'NEGATIVO' => 0, 'NEUTRO' => 0];

            if ($tipo === null || strtolower($tipo) === 'organic') {
                if ($pilar !== 'Influencers') {
                    $where = [];
                    $params = [];
                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($pilar === 'Kiosco') {
                        $where[] = "categoria_perfil = 'Kiosco'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria_perfil != 'Kiosco'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT sentiment, COUNT(*) as count FROM organic_campaign $whereSql GROUP BY sentiment";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    foreach ($stmt->fetchAll() as $row) {
                        $sent = strtoupper(trim($row['sentiment']));
                        if (isset($sentimentsCount[$sent])) {
                            $sentimentsCount[$sent] += (int)$row['count'];
                        }
                    }
                }
            }

            if ($tipo === null || strtolower($tipo) === 'paid') {
                if ($pilar !== 'Kiosco') {
                    $where = [];
                    $params = [];
                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($pilar === 'Influencers') {
                        $where[] = "categoria = 'PRESUPUESTO'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria = 'CANJE'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT sentiment, COUNT(*) as count FROM paid_campaign $whereSql GROUP BY sentiment";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
                    foreach ($stmt->fetchAll() as $row) {
                        $sent = strtoupper(trim($row['sentiment']));
                        if (isset($sentimentsCount[$sent])) {
                            $sentimentsCount[$sent] += (int)$row['count'];
                        }
                    }
                }
            }

            // 3. Content Pillars distribution
            $kioscoCount = 0;
            $ugcCount = 0;
            $influencersCount = 0;

            if ($tipo === null || strtolower($tipo) === 'organic') {
                $where = [];
                $params = [];
                if ($semana !== null) {
                    $where[] = 'semana = :semana';
                    $params['semana'] = $semana;
                }
                if ($plataforma) {
                    $where[] = 'plataforma = :plataforma';
                    $params['plataforma'] = $plataforma;
                }
                if ($sentiment) {
                    $where[] = 'sentiment = :sentiment';
                    $params['sentiment'] = strtoupper($sentiment);
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sqlKiosco = "SELECT COUNT(*) FROM organic_campaign $whereSql " . (count($where) > 0 ? 'AND' : 'WHERE') . " categoria_perfil = 'Kiosco'";
                $stmt = $connection->prepare($sqlKiosco);
                $stmt->execute($params);
                $kioscoCount = (int)$stmt->fetchColumn();

                $sqlUgc = "SELECT COUNT(*) FROM organic_campaign $whereSql " . (count($where) > 0 ? 'AND' : 'WHERE') . " categoria_perfil != 'Kiosco'";
                $stmt = $connection->prepare($sqlUgc);
                $stmt->execute($params);
                $ugcCount += (int)$stmt->fetchColumn();
            }

            if ($tipo === null || strtolower($tipo) === 'paid') {
                $where = [];
                $params = [];
                if ($semana !== null) {
                    $where[] = 'semana = :semana';
                    $params['semana'] = $semana;
                }
                if ($plataforma) {
                    $where[] = 'plataforma = :plataforma';
                    $params['plataforma'] = $plataforma;
                }
                if ($sentiment) {
                    $where[] = 'sentiment = :sentiment';
                    $params['sentiment'] = strtoupper($sentiment);
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $sqlUgcPaid = "SELECT COUNT(*) FROM paid_campaign $whereSql " . (count($where) > 0 ? 'AND' : 'WHERE') . " categoria = 'CANJE'";
                $stmt = $connection->prepare($sqlUgcPaid);
                $stmt->execute($params);
                $ugcCount += (int)$stmt->fetchColumn();

                $sqlInf = "SELECT COUNT(*) FROM paid_campaign $whereSql " . (count($where) > 0 ? 'AND' : 'WHERE') . " categoria = 'PRESUPUESTO'";
                $stmt = $connection->prepare($sqlInf);
                $stmt->execute($params);
                $influencersCount = (int)$stmt->fetchColumn();
            }

            echo json_encode([
                'weekly_evolution' => $weeklyChart,
                'sentiment' => $sentimentsCount,
                'pillars' => [
                    'Kiosco' => $kioscoCount,
                    'UGC' => $ugcCount,
                    'Influencers' => $influencersCount
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getTableData(): void
    {
        header('Content-Type: application/json');
        try {
            $semana = isset($_GET['semana']) && $_GET['semana'] !== '' ? (int)$_GET['semana'] : null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $records = [];
            $connection = $this->db->getConnection();

            // 1. Fetch Organic
            if ($tipo === null || strtolower($tipo) === 'organic') {
                if ($pilar !== 'Influencers') {
                    $where = [];
                    $params = [];
                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Kiosco') {
                        $where[] = "categoria_perfil = 'Kiosco'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria_perfil != 'Kiosco'";
                    }

                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT * FROM organic_campaign $whereSql";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);

                    foreach ($stmt->fetchAll() as $row) {
                        $records[] = [
                            'id' => 'org_' . $row['id'],
                            'mencion_id' => (int)$row['mencion_id'],
                            'semana' => (int)$row['semana'],
                            'usuario' => $row['usuario'],
                            'plataforma' => $row['plataforma'],
                            'link_publicacion' => $row['link_publicacion'],
                            'tipo' => 'Orgánico',
                            'pilar' => $row['categoria_perfil'] === 'Kiosco' ? 'Kiosco' : 'UGC',
                            'categoria_det' => $row['categoria_perfil'],
                            'views' => (int)$row['views_semana'],
                            'likes' => (int)$row['likes'],
                            'compartidos' => (int)$row['compartidos'],
                            'comentarios' => (int)$row['comentarios'],
                            'guardados' => (int)$row['guardados'],
                            'sentiment' => $row['sentiment']
                        ];
                    }
                }
            }
 
            // 2. Fetch Paid
            if ($tipo === null || strtolower($tipo) === 'paid') {
                if ($pilar !== 'Kiosco') {
                    $where = [];
                    $params = [];
                    if ($semana !== null) {
                        $where[] = 'semana = :semana';
                        $params['semana'] = $semana;
                    }
                    if ($plataforma) {
                        $where[] = 'plataforma = :plataforma';
                        $params['plataforma'] = $plataforma;
                    }
                    if ($sentiment) {
                        $where[] = 'sentiment = :sentiment';
                        $params['sentiment'] = strtoupper($sentiment);
                    }
                    if ($pilar === 'Influencers') {
                        $where[] = "categoria = 'PRESUPUESTO'";
                    } elseif ($pilar === 'UGC') {
                        $where[] = "categoria = 'CANJE'";
                    }
 
                    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                    $sql = "SELECT * FROM paid_campaign $whereSql";
                    $stmt = $connection->prepare($sql);
                    $stmt->execute($params);
 
                    foreach ($stmt->fetchAll() as $row) {
                        $records[] = [
                            'id' => 'paid_' . $row['id'],
                            'mencion_id' => (int)$row['mencion_id'],
                            'semana' => (int)$row['semana'],
                            'usuario' => $row['usuario'],
                            'plataforma' => $row['plataforma'],
                            'link_publicacion' => $row['link_publicacion'],
                            'tipo' => 'Pago',
                            'pilar' => $row['categoria'] === 'PRESUPUESTO' ? 'Influencers' : 'UGC',
                            'categoria_det' => $row['categoria'],
                            'views' => (int)$row['views_semana'],
                            'likes' => (int)$row['likes'],
                            'compartidos' => (int)$row['compartidos'],
                            'comentarios' => (int)$row['comentarios'],
                            'guardados' => (int)$row['guardados'],
                            'sentiment' => $row['sentiment']
                        ];
                    }
                }
            }

            // Ordenar por semana desc, luego likes desc
            usort($records, function($a, $b) {
                if ($a['semana'] !== $b['semana']) {
                    return $b['semana'] <=> $a['semana']; // Semana desc
                }
                return $b['likes'] <=> $a['likes']; // Likes desc
            });

            echo json_encode($records, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function downloadReport(): void
    {
        try {
            $generator = new ReportGenerator($this->db);
            $docxData = $generator->generate();

            if ($docxData === null) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No hay datos de reporte disponibles para descargar.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Limpiar cualquier salida previa del búfer para evitar corrupción del archivo binario
            if (ob_get_length()) {
                ob_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="Reporte_Consolidado_Mogul_360.docx"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($docxData));

            echo $docxData;
            if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists('PHPUnit\\Framework\\TestCase')) {
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error al generar el reporte Word: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
