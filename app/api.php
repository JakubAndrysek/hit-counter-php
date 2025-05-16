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

// Return the version code as a JSON response
header('Content-Type: application/json');
echo json_encode(["version" => $versionCode]);
