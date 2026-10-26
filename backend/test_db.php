<?php
// Quick DB connection test - open in browser: http://localhost/safe/test_db.php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getDB();
    echo json_encode([
        'status' => 'ok',
        'message' => 'Connected to database',
        'host' => DB_HOST,
        'port' => DB_PORT,
        'dbname' => DB_NAME
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
