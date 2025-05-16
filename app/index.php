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

    // Calculate interesting statistics
    $totalHits = array_sum(array_column($rows, 'hits'));
    $averageHits = $totalHits > 0 ? round($totalHits / count($rows), 2) : 0;
    $mostVisited = !empty($rows) ? max(array_column($rows, 'hits')) : 0;

    // Add Bootstrap 5 layout with borders and frame
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Directories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container my-4 border border-dark rounded p-4">
<h1 class="text-center mb-4">Saved Directories</h1>
<h2>Statistics</h2>
<ul class="list-group mb-4">
    <li class="list-group-item">Total Hits: {$totalHits}</li>
    <li class="list-group-item">Average Hits per URL: {$averageHits}</li>
    <li class="list-group-item">Most Visited URL Hits: {$mostVisited}</li>
</ul>
<table class="table table-striped border border-secondary rounded">
    <thead class="table-dark">
        <tr>
            <th>URL</th>
            <th>Total Hits</th>
            <th>Hits (1 Year)</th>
            <th>Hit Link</th>
            <th>Chart Links</th>
            <th>Remove Records</th>
            <th>Set Visit Count</th>
        </tr>
    </thead>
    <tbody>
HTML;
    // Add a button in the list view to open the country chart view
    foreach ($rows as $row) {
        $url = htmlspecialchars($row['url']);
        $hits = $row['hits'];
        $yearlyHits = $row['yearly_hits'];
        $hitLink = "?url=" . urlencode($url);
        $chartLiveLink = "?url=" . urlencode($url) . "&chart=true&chart_type=live";
        $chartPngLink = "?url=" . urlencode($url) . "&chart=true&chart_type=png";
        $chartSvgLink = "?url=" . urlencode($url) . "&chart_type=svg";
        $countryChartLink = "?country_chart=$secretKey";
        $removeLink = "?action=remove&url=" . urlencode($url) . "&confirm=1";
        $setCountLink = "?action=set_count&url=" . urlencode($url);

        echo <<<HTML
        <tr>
            <td>{$url}</td>
            <td>{$hits}</td>
            <td>{$yearlyHits}</td>
            <td><a href="{$hitLink}" class="btn btn-primary btn-sm">Hit Link</a></td>
            <td>
                <a href="{$chartLiveLink}" class="btn btn-success btn-sm">Live Chart</a>
                <a href="{$chartPngLink}" class="btn btn-warning btn-sm">PNG Chart</a>
                <a href="{$chartSvgLink}" class="btn btn-info btn-sm">SVG Chart</a>
                <a href="{$countryChartLink}" class="btn btn-secondary btn-sm">Country Chart</a>
            </td>
            <td>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="url" value="{$url}">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove all records for this URL?');">Remove</button>
                </form>
            </td>
            <td>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="set_count">
                    <input type="hidden" name="url" value="{$url}">
                    <input type="number" name="new_count" min="0" placeholder="Set new count" class="form-control form-control-sm">
                    <button type="submit" class="btn btn-secondary btn-sm mt-2">Set Count</button>
                </form>
            </td>
        </tr>
HTML;
    }
    echo <<<HTML
    </tbody>
</table>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;

    // Fetch top 20 URLs by hits with their last access time
    $topHitsStmt = $pdo->query("SELECT h.url, h.hits, MAX(a.access_time) as last_access FROM hits h LEFT JOIN access_logs a ON h.url = a.url GROUP BY h.url ORDER BY h.hits DESC LIMIT 20");
    $topHits = $topHitsStmt->fetchAll();

    // Fetch last 20 distinct pages with their last access time and total hits
    $lastPagesStmt = $pdo->query("SELECT h.url, MAX(a.access_time) as last_access, h.hits FROM access_logs a LEFT JOIN hits h ON a.url = h.url GROUP BY a.url ORDER BY last_access DESC LIMIT 20");
    $lastPages = $lastPagesStmt->fetchAll();

    // Add Bootstrap 5 styling to the tables
    echo <<<HTML
<h2 class="mt-5">Top 20 URLs by Hits</h2>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>URL</th>
            <th>Total Hits</th>
            <th>Last Access Time</th>
        </tr>
    </thead>
    <tbody>
HTML;
    foreach ($topHits as $row) {
        $url = htmlspecialchars($row['url']);
        $hits = $row['hits'];
        $lastAccess = $row['last_access'] ?? 'N/A';
        echo <<<HTML
        <tr>
            <td>{$url}</td>
            <td>{$hits}</td>
            <td>{$lastAccess}</td>
        </tr>
HTML;
    }
    echo <<<HTML
    </tbody>
</table>

<h2 class="mt-5">Last 20 Distinct Pages and Their Last Access Time</h2>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>URL</th>
            <th>Last Access Time</th>
            <th>Total Hits</th>
        </tr>
    </thead>
    <tbody>
HTML;
    foreach ($lastPages as $row) {
        $url = htmlspecialchars($row['url']);
        $lastAccess = $row['last_access'] ?? 'N/A';
        $hits = $row['hits'] ?? 0;
        echo <<<HTML
        <tr>
            <td>{$url}</td>
            <td>{$lastAccess}</td>
            <td>{$hits}</td>
        </tr>
HTML;
    }
    echo <<<HTML
    </tbody>
</table>
HTML;

    // Add a JavaScript-based chart at the end of the page to display last year's visit data

    // Fetch chart data for the last year
    $stmt = $pdo->query("SELECT DATE(access_time) as date, COUNT(*) as count FROM access_logs WHERE access_time >= NOW() - INTERVAL 1 YEAR GROUP BY DATE(access_time) ORDER BY date ASC");
    $chartData = $stmt->fetchAll();

    // Prepare data for the JavaScript chart
    $dates = json_encode(array_column($chartData, 'date'));
    $counts = json_encode(array_column($chartData, 'count'));

    // Add the chart container and script
    echo <<<HTML
<h2 class="mt-5">Last Year's Visit Data</h2>
<div>
    <canvas id="yearlyChart"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('yearlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: $dates,
            datasets: [{
                label: 'Visits',
                data: $counts,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: "Last Year's Visit Data"
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Visits'
                    },
                    beginAtZero: true
                }
            }
        }
    });
</script>
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

if (isset($_GET['country_chart']) && $_GET['country_chart'] === $secretKey) {
    // Fetch country distribution data
    $stmt = $pdo->query("SELECT DE, AT, CZ, SK, PL, US, UK, FR, IT, ES, NL, BE, SE, NO, FI, DK, CH, PT, IE, CN, RU, `IN`, OTHER FROM hits");
    $countryData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare data for the chart
    $labels = json_encode(array_keys($countryData));
    $values = json_encode(array_values($countryData));

    // Render the chart
    echo <<<HTML
<h2 class="mt-5">Country Distribution</h2>
<div>
    <canvas id="countryChart"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('countryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: $labels,
            datasets: [{
                label: 'Hits by Country',
                data: $values,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#C9CBCF', '#FF9F40', '#FFCD56', '#4BC0C0', '#36A2EB', '#9966FF', '#C9CBCF', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#C9CBCF', '#FF9F40', '#FFCD56', '#4BC0C0', '#36A2EB', '#9966FF', '#C9CBCF'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Hits by Country'
                }
            }
        }
    });
</script>
HTML;
    exit;
}

// Validate the 'url' parameter
if (!isset($_GET['url']) || empty($_GET['url'])) {
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

    // Fetch the user's IP address
    $ip = $_SERVER['REMOTE_ADDR'];

    // Handle loopback IP addresses
    if ($ip === 'x::1' || $ip === '127.0.0.1') {
        $countryCode = 'OTHER';
    } else {
        // Fetch the country code using the external API
        $apiUrl = "https://api.country.is/$ip";
        $response = @file_get_contents($apiUrl);
        $countryData = @json_decode($response, true);

        // Check if the API response contains an error or is invalid
        if (isset($countryData['error']) || !$countryData) {
            $countryCode = 'OTHER';
        } else {
            $countryCode = $countryData['country'] ?? 'OTHER';
        }
    }

    // Add China, Russia, and India to the country column logic
    $countryColumn = in_array($countryCode, ['DE', 'AT', 'CZ', 'SK', 'PL', 'US', 'UK', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'FI', 'DK', 'CH', 'PT', 'IE', 'CN', 'RU', 'IN']) ? $countryCode : 'OTHER';

    // Wrap the country column update in a try-catch block
    try {
        // Update the country column in the hits table
        $stmt = $pdo->prepare("UPDATE hits SET $countryColumn = $countryColumn + 1 WHERE url = ?");
        $stmt->execute([$url]);

        // Ensure the country column exists in the hits table
        $pdo->exec("ALTER TABLE hits ADD COLUMN IF NOT EXISTS $countryColumn INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Log the error and proceed without updating
        error_log("Failed to update country column: " . $e->getMessage());
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