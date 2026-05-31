<?php
/**
 * The Model Church — API Bootstrap Helper
 * Include this at the top of every TMC API endpoint
 * Handles: CORS, JSON headers, env loading, Supabase init
 */

// --- CORS ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Load .env if available ---
$envFile = dirname(__DIR__, 4) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// --- Load Supabase client ---
require_once __DIR__ . '/SupabaseClient.php';

// --- Helper: send JSON response ---
function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Helper: get request body as array ---
function requestBody(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
