<?php

/**
 * AI Service Backend
 *
 * Handles AI operations securely on the server side:
 * - Text generation with Gemini
 * - Image search with Unsplash
 * - Email sending with EmailJS
 *
 * All API keys are kept secure on the server.
 */

// Disable all output buffering and error display to prevent JSON corruption
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_clean();

// Include constants
require_once 'constants.php';

// Set headers
header('Content-Type: application/json');
// Simplified CORS for development - allow all origins
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Log request start
error_log("=== AI Service Request Start ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none'));

// Get request data
$rawInput = file_get_contents('php://input');
error_log("Raw Input: " . $rawInput);

$input = json_decode($rawInput, true);
error_log("Parsed Input: " . print_r($input, true));

if (!$input || !isset($input['action'])) {
    error_log("ERROR: Missing action parameter");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing action parameter']);
    exit();
}

$action = $input['action'];
error_log("Action: " . $action);

try {
    switch ($action) {
        case 'generate_text':
            $result = generateText($input);
            break;

        case 'search_images':
            $result = searchImages($input);
            break;

        case 'send_email':
            $result = sendEmail($input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }

    error_log("Final Result: " . print_r($result, true));

    // Ensure result has success field
    if (!isset($result['success'])) {
        $result['success'] = true;
    }

    // Clear any output buffer and ensure clean JSON
    if (ob_get_level()) {
        ob_clean();
    }

    $jsonResponse = json_encode($result);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON encoding error: " . json_last_error_msg());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'JSON encoding failed']);
        exit();
    }

    echo $jsonResponse;

} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);

    // Clear any output buffer and ensure clean JSON
    if (ob_get_level()) {
        ob_clean();
    }

    $errorResponse = [
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ];

    error_log("Sending error response: " . json_encode($errorResponse));

    $jsonResponse = json_encode($errorResponse);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON encoding error in catch: " . json_last_error_msg());
        echo json_encode(['success' => false, 'error' => 'JSON encoding failed']);
        exit();
    }

    echo $jsonResponse;
}

/**
 * Generate text using Gemini API
 */
function generateText($input)
{
    error_log("=== GenerateText Function Start ===");
    error_log("Input: " . print_r($input, true));

    if (!isset($input['prompt']) || !isset($input['format'])) {
        error_log("ERROR: Missing prompt or format parameter");
        throw new Exception('Missing prompt or format parameter');
    }

    // Check if API key is configured
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
        error_log("ERROR: Gemini API key not configured");
        throw new Exception('AI service not configured. Please contact administrator.');
    }

    $prompt = $input['prompt'];
    $format = $input['format']; // 'text' or 'json'

    error_log("Prompt: " . $prompt);
    error_log("Format: " . $format);

    // Prepare the prompt for JSON format
    if ($format === 'json') {
        $prompt = $prompt . "\n\nIMPORTANT: Return ONLY valid JSON. Do not include any text before or after the JSON. Do not use markdown formatting like ```json`. The response must start with { and end with }.";
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    error_log("Gemini API URL: " . $url);
    error_log("Gemini API Key: " . (defined('GEMINI_API_KEY') && GEMINI_API_KEY ? 'SET' : 'NOT SET'));

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];
    error_log("Request Data: " . json_encode($data));

    // Try up to 3 times with exponential backoff
    $maxAttempts = 3;
    $response = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        error_log("Attempt $attempt of $maxAttempts to Gemini API");
        $response = makeHttpRequest($url, 'POST', json_encode($data));
        error_log("Response attempt $attempt: " . print_r($response, true));

        if ($response['success']) {
            error_log("Success on attempt $attempt");
            break;
        }

        // Check if it's a 503 error (service unavailable) or timeout
        $isRetryableError = false;
        if (isset($response['error'])) {
            $error = $response['error'];
            $isRetryableError = (
                strpos($error, 'HTTP 503') !== false ||
                strpos($error, 'HTTP 502') !== false ||
                strpos($error, 'HTTP 504') !== false ||
                strpos($error, 'timeout') !== false ||
                strpos($error, 'connection') !== false
            );
        }

        if ($attempt < $maxAttempts && $isRetryableError) {
            $delay = pow(2, $attempt) * 5; // Exponential backoff: 10s, 20s, 40s
            error_log("Retryable error detected. Retrying in $delay seconds...");
            sleep($delay);
        } elseif ($attempt < $maxAttempts) {
            error_log("Non-retryable error. Stopping retries.");
            break;
        }
    }

    if (!$response['success']) {
        error_log("Final attempt failed: " . print_r($response, true));
        throw new Exception('Gemini API request failed: ' . $response['error']);
    }

    $responseData = json_decode($response['body'], true);

    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Invalid response structure from Gemini API');
    }

    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];

    // Clean up JSON response if needed
    if ($format === 'json') {
        $text = trim($text);
        // Remove markdown formatting if present
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // Validate JSON
        json_decode($text);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from AI');
        }
    }

    error_log("GenerateText completed successfully");
    return [
        'success' => true,
        'text' => $text
    ];
}

/**
 * Search images using Unsplash API
 */
function searchImages($input)
{
    if (!isset($input['query'])) {
        throw new Exception('Missing query parameter');
    }

    $query = urlencode($input['query']);
    $perPage = $input['per_page'] ?? 30;

    $url = "https://api.unsplash.com/search/photos?query={$query}&per_page={$perPage}";

    $headers = [
        'Authorization: Client-ID ' . UNSPLASH_API_KEY
    ];

    $response = makeHttpRequest($url, 'GET', null, $headers);

    if (!$response['success']) {
        throw new Exception('Failed to search images: ' . $response['error']);
    }

    $data = json_decode($response['body'], true);

    if (!$data || !isset($data['results'])) {
        throw new Exception('Invalid response from Unsplash API');
    }

    // Format the response to match frontend expectations
    $photos = [];
    foreach ($data['results'] as $photo) {
        $photos[] = [
            'id' => $photo['id'],
            'urls' => [
                'full' => $photo['urls']['full'],
                'regular' => $photo['urls']['regular'],
                'small' => $photo['urls']['small']
            ],
            'alt_description' => $photo['alt_description'] ?? '',
            'description' => $photo['description'] ?? ''
        ];
    }

    return [
        'success' => true,
        'photos' => $photos
    ];
}

/**
 * Send email using EmailJS
 */
function sendEmail($input)
{
    if (!isset($input['template_data']) || !isset($input['recipient_email'])) {
        throw new Exception('Missing template_data or recipient_email parameter');
    }

    $templateData = $input['template_data'];
    $recipientEmail = $input['recipient_email'];

    // Prepare the data for EmailJS
    $emailData = [
        'service_id' => EMAILJS_SERVICE_ID,
        'template_id' => EMAILJS_TEMPLATE_ID,
        'user_id' => EMAILJS_PUBLIC_KEY,
        'template_params' => array_merge($templateData, [
            'to_email' => $recipientEmail
        ])
    ];

    $url = 'https://api.emailjs.com/api/v1.0/email/send';

    $response = makeHttpRequest($url, 'POST', json_encode($emailData), [
        'Content-Type: application/json'
    ]);

    if (!$response['success']) {
        throw new Exception('Failed to send email: ' . $response['error']);
    }

    return [
        'success' => true,
        'message' => 'Email sent successfully'
    ];
}

/**
 * Make HTTP request with error handling
 */
function makeHttpRequest($url, $method = 'GET', $data = null, $headers = [])
{
    $ch = curl_init();

    // Use longer timeout for AI requests
    $timeout = (strpos($url, 'generativelanguage.googleapis.com') !== false) ? 300 : (defined('TIMEOUT_SECONDS') ? TIMEOUT_SECONDS : 30);
    error_log("Using timeout: $timeout seconds for URL: $url");

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        if (!in_array('Content-Type: application/json', $headers)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlInfo = curl_getinfo($ch);

    curl_close($ch);

    error_log("CURL Info: " . print_r($curlInfo, true));

    if ($error) {
        error_log("CURL Error: $error");
        return [
            'success' => false,
            'error' => $error
        ];
    }

    if ($httpCode >= 400) {
        return [
            'success' => false,
            'error' => 'HTTP ' . $httpCode,
            'body' => $response
        ];
    }

    return [
        'success' => true,
        'body' => $response,
        'http_code' => $httpCode
    ];
}
