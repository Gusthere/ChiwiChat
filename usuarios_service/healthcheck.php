<?php
header('Content-Type: application/json');
try {
    $db = new PDO(
        "mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB_DATABASE'),
        getenv('DB_USER'),
        getenv('DB_PASSWORD')
    );
    echo json_encode(['status' => 'healthy', 'db' => true]);
} catch (Exception $e) {
    header('HTTP/1.1 503 Service Unavailable');
    echo json_encode(['status' => 'unhealthy', 'error' => $e->getMessage()]);
    exit(1);
}