<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Router;
use App\MetricsController;
use App\UploadController;

$db = new Database();

try {
    $migration = new \App\DatabaseMigration($db);
    $migration->migrate();
} catch (\Exception $e) {
    // Si falla la conexión de forma temporal al inicio, se manejará en la consulta posterior.
}

$router = new Router();

$router->get('/api/report-metadata', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->getReportMetadata();
});

$router->get('/api/metrics/summary', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->getSummary();
});

$router->get('/api/metrics/charts', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->getChartsData();
});

$router->get('/api/metrics/table', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->getTableData();
});

$router->get('/api/filters', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->getFilters();
});

$router->get('/api/report/download', function() use ($db) {
    $controller = new MetricsController($db);
    $controller->downloadReport();
});

$router->post('/api/upload', function() use ($db) {
    $controller = new UploadController($db);
    $controller->upload();
});

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);

// Registro de performance para diagnóstico
$responseTime = $router->getLastResponseTime();
$dbConnTime = $db->getConnectionTime();
$dbQueryTime = $db->getTotalQueryTime();

header("X-DB-Conn-Time: " . number_format($dbConnTime, 2) . "ms");
header("X-DB-Query-Time: " . number_format($dbQueryTime, 2) . "ms");

$path = parse_url($uri)['path'] ?? '/';
if (str_starts_with($path, '/api/')) {
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf(
        "[%s] %s %s - Total: %.2fms, DB Conn: %.2fms, DB Query: %.2fms\n",
        date('Y-m-d H:i:s'),
        $method,
        $path,
        $responseTime,
        $dbConnTime,
        $dbQueryTime
    );
    file_put_contents($logDir . '/performance.log', $logEntry, FILE_APPEND);
}
