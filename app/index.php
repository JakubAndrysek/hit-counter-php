<?php
require __DIR__ . '/vendor/autoload.php'; // Ensure Composer's autoloader is included

use Dotenv\Dotenv;
use Amenadiel\JpGraph\Graph\Graph;
use Amenadiel\JpGraph\Plot\BarPlot;
use Bbsnly\ChartJs\Chart;
use Bbsnly\ChartJs\Config\Data;
use Bbsnly\ChartJs\Config\Dataset;
use Bbsnly\ChartJs\Config\Options;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Retrieve database credentials from environment variables
$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_DATABASE'];
$user = $_ENV['DB_USERNAME'];
$pass = $_ENV['DB_PASSWORD'];
$charset = $_ENV['DB_CHARSET'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database error: ' . $e->getMessage());
}

// Parameters
$url = $_GET['url'] ?? 'default';
$count_bg = $_GET['count_bg'] ?? '#79C83D';
$title_bg = $_GET['title_bg'] ?? '#555555';
$title = $_GET['title'] ?? '👀';
$chart = isset($_GET['chart']) ? true : false;

// Validate the 'url' parameter
if (empty($url)) {
    http_response_code(400); // Bad Request
    echo <<<HTML
<html>
<head><title>Error</title></head>
<body>
<h1>400 Bad Request</h1>
<p>The 'url' parameter is required.</p>
</body>
</html>
HTML;
    exit;
}

// Prevent caching
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Log access only if not showing the chart
if (!$chart) {
    $stmt = $pdo->prepare("INSERT INTO access_logs (url) VALUES (?)");
    $stmt->execute([$url]);

    // Delete logs older than 6 months
    $pdo->exec("DELETE FROM access_logs WHERE access_time < NOW() - INTERVAL 6 MONTH");
}

if ($chart) {
    $chartType = $_GET['chart_type'] ?? 'live'; // Default to 'live'

    if ($chartType === 'live') {
        // Set the correct Content-Type header for HTML output
        header('Content-Type: text/html');

        // Generate chart using ChartJS-PHP
        $stmt = $pdo->prepare("SELECT DATE(access_time) as date, COUNT(*) as count FROM access_logs WHERE url = ? GROUP BY DATE(access_time)");
        $stmt->execute([$url]);
        $data = $stmt->fetchAll();

        $dates = array_column($data, 'date');
        $counts = array_column($data, 'count');

        $chart = new Chart;
        $chart->type = 'bar';

        $chartData = new Data();
        $chartData->labels = $dates;

        $dataset = new Dataset();
        $dataset->data = $counts;
        $dataset->backgroundColor = '#79C83D';
        $chartData->datasets[] = $dataset;

        $chart->data($chartData);

        $options = new Options();
        $options->responsive = true;
        $chart->options($options);

        echo <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div>
    <canvas id="myChart"></canvas>
</div>
<script>
    const ctx = document.getElementById('myChart');
    new Chart(ctx, {$chart->toJson()});
</script>
HTML;
    } elseif ($chartType === 'png') {
        // Set the correct Content-Type header for PNG output
        header('Content-Type: image/png');

        // Generate chart using jpgraph
        $stmt = $pdo->prepare("SELECT DATE(access_time) as date, COUNT(*) as count FROM access_logs WHERE url = ? GROUP BY DATE(access_time)");
        $stmt->execute([$url]);
        $data = $stmt->fetchAll();

        $dates = array_column($data, 'date');
        $counts = array_column($data, 'count');

        $width = 800;
        $height = 400;

        $graph = new Graph($width, $height);
        $graph->SetScale('textlin');

        $barplot = new BarPlot($counts);
        $barplot->SetFillColor('#79C83D');

        $graph->Add($barplot);
        $graph->xaxis->SetTickLabels($dates);
        $graph->xaxis->SetLabelAngle(50);

        $graph->title->Set('Access Logs');
        $graph->xaxis->title->Set('Date');
        $graph->yaxis->title->Set('Count');

        $graph->img->SetImgFormat('png');
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
    }
} else {
    // Track hits
    $stmt = $pdo->prepare("INSERT INTO hits (url, hits) VALUES (?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1");
    $stmt->execute([$url]);

    // Retrieve current hit count
    $stmt = $pdo->prepare("SELECT hits FROM hits WHERE url = ?");
    $stmt->execute([$url]);
    $count = $stmt->fetchColumn();

    // Render SVG counter
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="25">
  <rect width="40" height="25" fill="$title_bg" />
  <rect x="40" width="80" height="25" fill="$count_bg" />
  <text x="20" y="17" font-size="12" fill="#FFFFFF" font-family="Arial, sans-serif" text-anchor="middle">$title</text>
  <text x="80" y="17" font-size="12" fill="#FFFFFF" font-family="Arial, sans-serif" text-anchor="middle">$count</text>
</svg>
SVG;
}