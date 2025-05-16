<?php
require __DIR__ . '/vendor/autoload.php'; // Ensure Composer's autoloader is included

use Dotenv\Dotenv;
use Amenadiel\JpGraph\Graph\Graph;
use Amenadiel\JpGraph\Plot\BarPlot;
use Bbsnly\ChartJs\Chart;
use Bbsnly\ChartJs\Config\Data;
use Bbsnly\ChartJs\Config\Dataset;
use Bbsnly\ChartJs\Config\Options;
use Maantje\Charts\Bar\Bar;
use Maantje\Charts\Bar\Bars;
use Maantje\Charts\Chart as MaantjeChart;

// Check if .env file exists and load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} else {
    error_log(".env file not found. Using system environment variables.");
}

// Add this block after loading environment variables
$secretKey = $_ENV['SECRET_KEY'];


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

// Ensure the database connection is established before using $pdo
if (!isset($pdo)) {
    exit('Database connection not initialized. Please check your configuration.');
}

if (isset($_GET['list']) && $_GET['list'] === $secretKey) {
    // Fetch the count of hits stored for 1 year for each URL
    $stmt = $pdo->query("SELECT url, hits, (SELECT COUNT(*) FROM access_logs WHERE access_logs.url = hits.url AND access_time >= NOW() - INTERVAL 1 YEAR) AS yearly_hits FROM hits ORDER BY hits DESC");
    $rows = $stmt->fetchAll();

    // Debugging output to check if rows are fetched
    if (empty($rows)) {
        echo '<p>No data found in the hits table. Please ensure the table is populated.</p>';
        exit;
    }

    // Generate HTML response
    header('Content-Type: text/html');
    echo <<<HTML
<html>
<head><title>Saved Directories</title></head>
<body>
<h1>Saved Directories</h1>
<table border="1">
    <tr>
        <th>URL</th>
        <th>Total Hits</th>
        <th>Hits (1 Year)</th>
        <th>Hit Link</th>
        <th>Chart Links</th>
        <th>Remove Records</th>
        <th>Set Visit Count</th>
    </tr>
HTML;

// Add buttons for removing records and setting visit count
    foreach ($rows as $row) {
        $url = htmlspecialchars($row['url']);
        $hits = $row['hits'];
        $yearlyHits = $row['yearly_hits'];
        $hitLink = "?url=" . urlencode($url);
        $chartLiveLink = "?url=" . urlencode($url) . "&chart=true&chart_type=live";
        $chartPngLink = "?url=" . urlencode($url) . "&chart=true&chart_type=png";
        $chartSvgLink = "?url=" . urlencode($url) . "&chart=true&chart_type=svg";
        $removeLink = "?action=remove&url=" . urlencode($url) . "&confirm=1";
        $setCountLink = "?action=set_count&url=" . urlencode($url);

        echo <<<HTML
    <tr>
        <td>{$url}</td>
        <td>{$hits}</td>
        <td>{$yearlyHits}</td>
        <td><a href="{$hitLink}">Hit Link</a></td>
        <td>
            <a href="{$chartLiveLink}">Live Chart</a> |
            <a href="{$chartPngLink}">PNG Chart</a> |
            <a href="{$chartSvgLink}">SVG Chart</a>
        </td>
        <td>
            <form method="POST" action="">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="url" value="{$url}">
                <button type="submit" onclick="return confirm('Are you sure you want to remove all records for this URL?');">Remove</button>
            </form>
        </td>
        <td>
            <form method="POST" action="">
                <input type="hidden" name="action" value="set_count">
                <input type="hidden" name="url" value="{$url}">
                <input type="number" name="new_count" min="0" placeholder="Set new count">
                <button type="submit">Set Count</button>
            </form>
        </td>
    </tr>
HTML;
    }

    echo <<<HTML
</table>
</body>
</html>
HTML;

    // Handle POST actions for removing records and setting visit count
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $url = $_POST['url'] ?? '';

        if ($action === 'remove' && !empty($url)) {
            $stmt = $pdo->prepare("DELETE FROM hits WHERE url = ?");
            $stmt->execute([$url]);

            $stmt = $pdo->prepare("DELETE FROM access_logs WHERE url = ?");
            $stmt->execute([$url]);

            echo '<p>All records for the URL have been removed.</p>';
        } elseif ($action === 'set_count' && !empty($url)) {
            $newCount = (int) ($_POST['new_count'] ?? 0);

            $stmt = $pdo->prepare("INSERT INTO hits (url, hits) VALUES (?, ?) ON DUPLICATE KEY UPDATE hits = ?");
            $stmt->execute([$url, $newCount, $newCount]);

            echo '<p>The visit count has been updated.</p>';
        }
    }

    exit;
}


// Validate the 'url' parameter
if (empty($_GET['url'])) {
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

// Parameters
$url = $_GET['url'];
$count_bg = $_GET['count_bg'] ?? '#79C83D';
$title_bg = $_GET['title_bg'] ?? '#555555';
$title = $_GET['title'] ?? '👀';
$chart = isset($_GET['chart']) ? true : false;

// Exclude 'favicon.ico' from being counted
if ($url === 'favicon.ico') {
    exit;
}

// Prevent caching
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');


if ($chart) {
    $chartType = $_GET['chart_type'] ?? 'live'; // Default to 'live'

    // Validate chart_type parameter
    $validChartTypes = ['live', 'png', 'svg'];
    if (!in_array($chartType, $validChartTypes)) {
        http_response_code(400); // Bad Request
        echo <<<HTML
<html>
<head><title>Error</title></head>
<body>
<h1>400 Bad Request</h1>
<p>Invalid chart_type parameter. Allowed values are: live, png, svg.</p>
</body>
</html>
HTML;
        exit;
    }

    // Fetch total hit count from the `hits` table
    $stmt = $pdo->prepare("SELECT hits FROM hits WHERE url = ?");
    $stmt->execute([$url]);
    $totalHits = $stmt->fetchColumn() ?? 0;

    // Fetch chart data from the `access_logs` table for the past year
    $stmt = $pdo->prepare("SELECT DATE(access_time) as date, COUNT(*) as count FROM access_logs WHERE url = ? AND access_time >= NOW() - INTERVAL 1 YEAR GROUP BY DATE(access_time)");
    $stmt->execute([$url]);
    $chartData = $stmt->fetchAll();

    if ($chartType === 'live') {
        // Set the correct Content-Type header for HTML output
        header('Content-Type: text/html');

        // Generate chart using ChartJS-PHP
        $dates = array_column($chartData, 'date');
        $counts = array_column($chartData, 'count');

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
        $options->plugins = [
            'title' => [
                'display' => true,
                'text' => "Hits for $url (Total: $totalHits)"
            ]
        ];
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
        $dates = array_column($chartData, 'date');
        $counts = array_column($chartData, 'count');

        $width = 800;
        $height = 400;

        $graph = new Graph($width, $height);
        $graph->SetScale('textlin');

        $barplot = new BarPlot($counts);
        $barplot->SetFillColor('#79C83D');

        $graph->Add($barplot);
        $graph->xaxis->SetTickLabels($dates);
        $graph->xaxis->SetLabelAngle(50);

        $graph->title->Set("Hits for $url (Total: $totalHits)");
        $graph->xaxis->title->Set('Date');
        $graph->yaxis->title->Set('Count');

        $graph->img->SetImgFormat('png');
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
    } elseif ($chartType === 'svg') {
        // Ensure no output before SVG rendering
        ob_clean(); // Clear any previous output buffer
        header('Content-Type: image/svg+xml');

        // Generate chart using Maantje Charts
        $bars = [];
        foreach ($chartData as $row) {
            $bars[] = new Bar(name: $row['date'], value: $row['count']);
        }

        $chart = new MaantjeChart(
            series: [
                new Bars(
                    bars: $bars,
                ),
            ],
            // title: "Access Logs for $url (Total: $totalHits)"
        );

        echo $chart->render();
    }
} else {
    // Update total hit count
    // Debugging output to check hit count update
    error_log("Updating hit count for URL: $url");
    $stmt = $pdo->prepare("INSERT INTO hits (url, hits) VALUES (?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1");
    $stmt->execute([$url]);

    // Log access for chart data
    $stmt = $pdo->prepare("INSERT INTO access_logs (url) VALUES (?)");
    $stmt->execute([$url]);

    // Delete logs older than 1 year
    $pdo->exec("DELETE FROM access_logs WHERE access_time < NOW() - INTERVAL 1 YEAR");

    // Retrieve current hit count
    $stmt = $pdo->prepare("SELECT hits FROM hits WHERE url = ?");
    $stmt->execute([$url]);
    $count = $stmt->fetchColumn();
	if ($count === false) {
		$count = 0; // Default to 0 if no count is found
	}

    // Format the count for display
    if ($count >= 1000) {
        if ($count >= 1000000) {
            $count = round($count / 1000000, 1) . ' M';
        } else {
            $count = round($count / 1000, 1) . ' k';
        }
    }

    // Allow customization of the title text
    $title = $_GET['title'] ?? '👀';

    // Render SVG counter with clickable link to the selected chart version
    $chartType = $_GET['chart_type'] ?? 'svg'; // Default to 'svg'
    // Fix the SVG `onclick` attribute by encoding the URL properly
    $chartLink = htmlspecialchars("?url=" . urlencode($url) . "&chart=true&chart_type=" . urlencode($chartType), ENT_QUOTES, 'UTF-8');

	$onClick = "";
	// if not set, do not set the onclick
	if (isset($_GET['chart_type'])) {
		$onClick = "onclick=\"window.location.href='$chartLink'\"";
	}

    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="25" style="cursor: pointer;" $onClick>
  <rect width="40" height="25" fill="$title_bg" />
  <rect x="40" width="80" height="25" fill="$count_bg" />
  <text x="20" y="17" font-size="12" fill="#FFFFFF" font-family="Arial, sans-serif" text-anchor="middle">$title</text>
  <text x="80" y="17" font-size="12" fill="#FFFFFF" font-family="Arial, sans-serif" text-anchor="middle">$count</text>
</svg>
SVG;
}