<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

apply_security_headers();

try {
    getDB()->query('SELECT 1');
    echo json_encode(['status' => 'ok']);
} catch (Throwable $error) {
    error_log('Health check failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}
