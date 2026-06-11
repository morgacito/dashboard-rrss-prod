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
            $stmt = $this->db->query('SELECT month, year FROM report_metadata LIMIT 1');
            $meta = $stmt->fetch();

            $month = $meta['month'] ?? '';
            $year = (int)($meta['year'] ?? 0);

            if (empty($month)) {
                $stmt = $this->db->query('SELECT mes FROM organic_campaign LIMIT 1');
                $month = $stmt->fetchColumn() ?: '';
                if (empty($month)) {
                    $stmt = $this->db->query('SELECT mes FROM paid_campaign LIMIT 1');
                    $month = $stmt->fetchColumn() ?: '';
                }
            }
            
            if ($year === 0) {
                $year = 2026;
            }

            echo json_encode([
                'month' => $month,
                'year' => $year
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getFilters(): void
    {
        try {
            $sql = "SELECT DISTINCT mes, semana FROM (
                        SELECT mes, semana FROM organic_campaign
                        UNION
                        SELECT mes, semana FROM paid_campaign
                    ) as combined
                    ORDER BY mes, semana";
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $meses = [];
            $semanas = [];

            $monthOrder = [
                'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4,
                'Mayo' => 5, 'Junio' => 6, 'Julio' => 7, 'Agosto' => 8,
                'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12
            ];

            foreach ($results as $row) {
                $mes = $row['mes'];
                $semana = (int)$row['semana'];
                
                if (!in_array($mes, $meses)) {
                    $meses[] = $mes;
                }
                
                if (!isset($semanas[$mes])) {
                    $semanas[$mes] = [];
                }
                if (!in_array($semana, $semanas[$mes])) {
                    $semanas[$mes][] = $semana;
                }
            }

            usort($meses, function($a, $b) use ($monthOrder) {
                $orderA = $monthOrder[$a] ?? 99;
                $orderB = $monthOrder[$b] ?? 99;
                return $orderA <=> $orderB;
            });

            foreach ($semanas as $m => &$s) {
                sort($s);
            }

            header('Content-Type: application/json');
            echo json_encode([
                'meses' => $meses,
                'semanas' => $semanas
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error al obtener filtros: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function getSummary(): void
    {
        header('Content-Type: application/json');
        try {
            $semana = isset($_GET['semana']) && $_GET['semana'] !== '' ? (int)$_GET['semana'] : null;
            $mes = $_GET['mes'] ?? null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $orgSummary = ['count' => 0, 'likes' => 0, 'shares' => 0, 'saves' => 0, 'comments' => 0, 'views' => 0];
            $paidSummary = ['count' => 0, 'likes' => 0, 'shares' => 0, 'saves' => 0, 'comments' => 0, 'views' => 0];

            if ($tipo === null || strtolower($tipo) === 'organic') {
                $where = [];
                $params = [];
                if ($mes) {
                    $where[] = 'mes = :mes';
                    $params['mes'] = $mes;
                }
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
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria_perfil = :pilar';
                    $params['pilar'] = $val;
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT COUNT(*) as count, SUM(likes) as likes, SUM(compartidos) as shares, SUM(guardados) as saves, SUM(comentarios) as comments, SUM(views_semana) as views FROM organic_campaign $whereSql";
                
                $stmt = $this->db->query($sql, $params);
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

            if ($tipo === null || strtolower($tipo) === 'paid') {
                $where = [];
                $params = [];
                if ($mes) {
                    $where[] = 'mes = :mes';
                    $params['mes'] = $mes;
                }
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
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria = :pilar';
                    $params['pilar'] = $val;
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT COUNT(*) as count, SUM(likes) as likes, SUM(compartidos) as shares, SUM(guardados) as saves, SUM(comentarios) as comments, SUM(views_semana) as views FROM paid_campaign $whereSql";
                
                $stmt = $this->db->query($sql, $params);
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
            $mes = $_GET['mes'] ?? null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $orgWeeks = [];
            if ($tipo === null || strtolower($tipo) === 'organic') {
                $where = [];
                $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($sentiment) { $where[] = 'sentiment = :sentiment'; $params['sentiment'] = strtoupper($sentiment); }
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria_perfil = :pilar';
                    $params['pilar'] = $val;
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT mes, semana, COUNT(*) as count, SUM(likes + compartidos + comentarios + guardados) as engagement FROM organic_campaign $whereSql GROUP BY mes, semana";
                $stmt = $this->db->query($sql, $params);
                $orgWeeks = $stmt->fetchAll();
            }

            $paidWeeks = [];
            if ($tipo === null || strtolower($tipo) === 'paid') {
                $where = [];
                $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($sentiment) { $where[] = 'sentiment = :sentiment'; $params['sentiment'] = strtoupper($sentiment); }
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria = :pilar';
                    $params['pilar'] = $val;
                }

                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT mes, semana, COUNT(*) as count, SUM(likes + compartidos + comentarios + guardados) as engagement FROM paid_campaign $whereSql GROUP BY mes, semana";
                $stmt = $this->db->query($sql, $params);
                $paidWeeks = $stmt->fetchAll();
            }

            $monthOrder = ['Enero'=>1,'Febrero'=>2,'Marzo'=>3,'Abril'=>4,'Mayo'=>5,'Junio'=>6,'Julio'=>7,'Agosto'=>8,'Septiembre'=>9,'Octubre'=>10,'Noviembre'=>11,'Diciembre'=>12];
            $weeklyData = [];
            foreach ($orgWeeks as $row) {
                $m = $row['mes']; $w = (int)$row['semana']; $mNum = $monthOrder[$m] ?? 99;
                $key = sprintf("%02d-%02d", $mNum, $w);
                $label = $mes ? $w : "$w ($m)";
                $weeklyData[$key] = ['semana' => $label, 'organic_mentions' => (int)$row['count'], 'organic_engagement' => (int)($row['engagement'] ?? 0), 'paid_mentions' => 0, 'paid_engagement' => 0];
            }
            foreach ($paidWeeks as $row) {
                $m = $row['mes']; $w = (int)$row['semana']; $mNum = $monthOrder[$m] ?? 99;
                $key = sprintf("%02d-%02d", $mNum, $w);
                $label = $mes ? $w : "$w ($m)";
                if (!isset($weeklyData[$key])) {
                    $weeklyData[$key] = ['semana' => $label, 'organic_mentions' => 0, 'organic_engagement' => 0, 'paid_mentions' => (int)$row['count'], 'paid_engagement' => (int)($row['engagement'] ?? 0)];
                } else {
                    $weeklyData[$key]['paid_mentions'] = (int)$row['count'];
                    $weeklyData[$key]['paid_engagement'] = (int)($row['engagement'] ?? 0);
                }
            }
            ksort($weeklyData);
            $weeklyChart = array_values($weeklyData);

            $sentimentsCount = ['POSITIVO' => 0, 'NEGATIVO' => 0, 'NEUTRO' => 0];
            $procSent = function($table, $colPilar) use ($tipo, $pilar, $mes, $semana, $plataforma, &$sentimentsCount) {
                $where = []; $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($semana !== null) { $where[] = 'semana = :semana'; $params['semana'] = $semana; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = "$colPilar = :pilar";
                    $params['pilar'] = $val;
                }
                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT sentiment, COUNT(*) as count FROM $table $whereSql GROUP BY sentiment";
                $stmt = $this->db->query($sql, $params);
                foreach ($stmt->fetchAll() as $row) {
                    $sent = strtoupper(trim($row['sentiment']));
                    if (isset($sentimentsCount[$sent])) $sentimentsCount[$sent] += (int)$row['count'];
                }
            };
            if ($tipo === null || strtolower($tipo) === 'organic') $procSent('organic_campaign', 'categoria_perfil');
            if ($tipo === null || strtolower($tipo) === 'paid') $procSent('paid_campaign', 'categoria');

            $pillars = ['Kiosco' => 0, 'UGC' => 0, 'Influencer' => 0];
            $procPillars = function($table, $colPilar) use ($tipo, $mes, $semana, $plataforma, $sentiment, &$pillars) {
                $where = []; $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($semana !== null) { $where[] = 'semana = :semana'; $params['semana'] = $semana; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($sentiment) { $where[] = 'sentiment = :sentiment'; $params['sentiment'] = strtoupper($sentiment); }
                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT $colPilar, COUNT(*) as count FROM $table $whereSql GROUP BY $colPilar";
                $stmt = $this->db->query($sql, $params);
                foreach ($stmt->fetchAll() as $row) {
                    $p = $row[$colPilar];
                    if (isset($pillars[$p])) $pillars[$p] += (int)$row['count'];
                }
            };
            if ($tipo === null || strtolower($tipo) === 'organic') $procPillars('organic_campaign', 'categoria_perfil');
            if ($tipo === null || strtolower($tipo) === 'paid') $procPillars('paid_campaign', 'categoria');

            echo json_encode([
                'weekly_evolution' => $weeklyChart,
                'sentiment' => $sentimentsCount,
                'pillars' => [
                    'Kiosco' => $pillars['Kiosco'],
                    'UGC' => $pillars['UGC'],
                    'Influencers' => $pillars['Influencer']
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
            $mes = $_GET['mes'] ?? null;
            $plataforma = $_GET['plataforma'] ?? null;
            $sentiment = $_GET['sentiment'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $pilar = $_GET['pilar'] ?? null;

            $records = [];
            if ($tipo === null || strtolower($tipo) === 'organic') {
                $where = []; $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($semana !== null) { $where[] = 'semana = :semana'; $params['semana'] = $semana; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($sentiment) { $where[] = 'sentiment = :sentiment'; $params['sentiment'] = strtoupper($sentiment); }
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria_perfil = :pilar';
                    $params['pilar'] = $val;
                }
                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT * FROM organic_campaign $whereSql";
                foreach ($this->db->query($sql, $params)->fetchAll() as $row) {
                    $records[] = [
                        'id' => 'org_' . $row['id'],
                        'semana' => (int)$row['semana'],
                        'mes' => $row['mes'],
                        'usuario' => $row['usuario'],
                        'plataforma' => $row['plataforma'],
                        'link_publicacion' => $row['link_publicacion'],
                        'tipo' => 'Orgánico',
                        'pilar' => ($row['categoria_perfil'] === 'Influencer') ? 'Influencers' : $row['categoria_perfil'],
                        'views' => (int)$row['views_semana'],
                        'likes' => (int)$row['likes'],
                        'compartidos' => (int)$row['compartidos'],
                        'comentarios' => (int)$row['comentarios'],
                        'guardados' => (int)$row['guardados'],
                        'sentiment' => $row['sentiment']
                    ];
                }
            }

            if ($tipo === null || strtolower($tipo) === 'paid') {
                $where = []; $params = [];
                if ($mes) { $where[] = 'mes = :mes'; $params['mes'] = $mes; }
                if ($semana !== null) { $where[] = 'semana = :semana'; $params['semana'] = $semana; }
                if ($plataforma) { $where[] = 'plataforma = :plataforma'; $params['plataforma'] = $plataforma; }
                if ($sentiment) { $where[] = 'sentiment = :sentiment'; $params['sentiment'] = strtoupper($sentiment); }
                if ($pilar) {
                    $val = ($pilar === 'Influencers') ? 'Influencer' : $pilar;
                    $where[] = 'categoria = :pilar';
                    $params['pilar'] = $val;
                }
                $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $sql = "SELECT * FROM paid_campaign $whereSql";
                foreach ($this->db->query($sql, $params)->fetchAll() as $row) {
                    $records[] = [
                        'id' => 'paid_' . $row['id'],
                        'semana' => (int)$row['semana'],
                        'mes' => $row['mes'],
                        'usuario' => $row['usuario'],
                        'plataforma' => $row['plataforma'],
                        'link_publicacion' => $row['link_publicacion'],
                        'tipo' => 'Pago',
                        'pilar' => ($row['categoria'] === 'Influencer') ? 'Influencers' : $row['categoria'],
                        'views' => (int)$row['views_semana'],
                        'likes' => (int)$row['likes'],
                        'compartidos' => (int)$row['compartidos'],
                        'comentarios' => (int)$row['comentarios'],
                        'guardados' => (int)$row['guardados'],
                        'sentiment' => $row['sentiment']
                    ];
                }
            }

            usort($records, fn($a, $b) => ($a['semana'] !== $b['semana']) ? ($b['semana'] <=> $a['semana']) : ($b['likes'] <=> $a['likes']));
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
                echo json_encode(['error' => 'No hay datos disponibles']);
                return;
            }
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="Reporte_Consolidado_Mogul_360.docx"');
            echo $docxData;
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
