<?php

/**
 * API pentru expunerea configurației site-ului
 * Folosește site-config.json și permite accesul prin CORS
 */

// Activează raportarea erorilor pentru debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers pentru CORS și JSON
header('Content-Type: application/json; charset=utf-8');

// CORS headers - PERMITE TOATE ORIGIN-URILE PENTRU TESTARE
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

// Log pentru debugging CORS
error_log("CORS Debug - Origin: " . $origin . ", Host: " . $host);

// Permite toate origin-urile pentru testare
header('Access-Control-Allow-Origin: *');
error_log("CORS - Permis pentru toate origin-urile (testare): " . $origin);

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin');
header('Access-Control-Allow-Credentials: true');

// Răspunde la preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Funcție pentru logging
function logRequest($message, $data = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $logData = [
        'timestamp' => $timestamp,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'message' => $message
    ];

    if ($data) {
        $logData['data'] = $data;
    }

    error_log(json_encode($logData) . "\n", 3, 'api-requests.log');
}

// Calea către fișierul de configurație
$configFile = __DIR__ . '/site-config.json';

try {
    // Verifică dacă fișierul există
    if (!file_exists($configFile)) {
        logRequest('ERROR: site-config.json not found', ['file' => $configFile]);
        http_response_code(404);
        echo json_encode([
            'error' => 'Configuration file not found',
            'message' => 'site-config.json is missing from the server',
            'timestamp' => date('c')
        ]);
        exit();
    }

    // Determină operația bazată pe metoda HTTP și URL
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathInfo = parse_url($requestUri, PHP_URL_PATH);

    // Extrage domeniul din URL dacă este prezent
    // Suportă atât /site-config/domain.com cât și /site-config
    $pathParts = explode('/', trim($pathInfo, '/'));
    $domain = null;

    if (count($pathParts) >= 2 && $pathParts[0] === 'site-config') {
        $domain = $pathParts[1];
    }

    switch ($requestMethod) {
        case 'GET':
            // Încarcă configurația
            $configContent = file_get_contents($configFile);

            if ($configContent === false) {
                logRequest('ERROR: Failed to read site-config.json');
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to read configuration',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            // Verifică dacă JSON-ul este valid
            $configData = json_decode($configContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logRequest('ERROR: Invalid JSON in site-config.json', ['json_error' => json_last_error_msg()]);
                http_response_code(500);
                echo json_encode([
                    'error' => 'Invalid JSON in configuration file',
                    'details' => json_last_error_msg(),
                    'timestamp' => date('c')
                ]);
                exit();
            }

            logRequest('SUCCESS: Configuration loaded', ['domain' => $domain, 'size' => strlen($configContent)]);

            // Returnează configurația
            http_response_code(200);
            echo $configContent;
            break;

        case 'POST':
            // Salvează configurația (pentru testare)
            $input = file_get_contents('php://input');
            $inputData = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                logRequest('ERROR: Invalid JSON in POST request', ['json_error' => json_last_error_msg()]);
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid JSON in request',
                    'details' => json_last_error_msg(),
                    'timestamp' => date('c')
                ]);
                exit();
            }

            // Verifică dacă avem datele necesare
            if (!isset($inputData['config'])) {
                logRequest('ERROR: Missing config in POST request');
                http_response_code(400);
                echo json_encode([
                    'error' => 'Missing configuration data',
                    'message' => 'Expected "config" field in request body',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            // Salvează configurația cu imaginile ca base64 în JSON
            $configToSave = json_encode($inputData['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (file_put_contents($configFile, $configToSave) === false) {
                logRequest('ERROR: Failed to save site-config.json');
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to save configuration',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            logRequest('SUCCESS: Configuration saved', [
                'domain' => $inputData['domain'] ?? 'unknown',
                'size' => strlen($configToSave)
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved successfully',
                'timestamp' => date('c')
            ]);
            break;

        default:
            logRequest('ERROR: Unsupported HTTP method', ['method' => $requestMethod]);
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed',
                'allowed_methods' => ['GET', 'POST'],
                'timestamp' => date('c')
            ]);
            break;
    }

} catch (Exception $e) {
    logRequest('FATAL ERROR: Exception caught', ['exception' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred',
        'timestamp' => date('c'),
        'debug' => $e->getMessage() // Elimină în producție
    ]);
}
