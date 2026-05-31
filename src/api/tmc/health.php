<?php
/**
 * TMC API — Health Check
 * GET /tmc-api/health
 */
require_once __DIR__ . '/_bootstrap.php';

respond([
    'status'  => 'ok',
    'app'     => $_ENV['APP_NAME'] ?? 'The Model Church',
    'version' => '1.0.0',
    'time'    => date('c'),
    'env'     => $_ENV['APP_ENV'] ?? 'production',
]);
