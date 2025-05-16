<?php
// api.php - Returns the version code from the VERSION file

// Define the path to the VERSION file
$versionFilePath = __DIR__ . '/VERSION';

// Check if the VERSION file exists
if (!file_exists($versionFilePath)) {
    http_response_code(404); // Not Found
    echo json_encode(["error" => "VERSION file not found"]);
    exit;
}

// Read the version code from the file
$versionCode = trim(file_get_contents($versionFilePath));

// Determine the user's IP address
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

// Call the counter API to log the IP address
$counterApiUrl = "https://api.country.is/$ip";
$counterApiResponse = @file_get_contents($counterApiUrl);
$counterApiData = @json_decode($counterApiResponse, true);

// Include the IP address and counter API response in the output
header('Content-Type: application/json');
echo json_encode([
    "version" => $versionCode,
    "ip" => $ip,
    "counterApiResponse" => $counterApiData
]);
