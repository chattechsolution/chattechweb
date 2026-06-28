<?php
// api/config.php

// Database configuration
// Using environment variables for security. Do not hardcode credentials.
$db_host = getenv('DB_HOST') ?: 'localhost:3306';
$db_name = getenv('DB_NAME') ?: 'homesaf1_chattech_portal';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// Testing override
$db_type = getenv('DB_TYPE') ?: 'mysql';

$pdo = null;

try {
    if ($db_type === 'sqlite') {
        // Use sqlite in memory or local file
        $sqlite_path = __DIR__ . '/../database/crm.sqlite';
        $dsn = "sqlite:$sqlite_path";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    }
} catch (\PDOException $e) {
    // Return JSON error if connection fails, instead of raw HTML
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Ensure session is started for authentication
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isSuperAdmin();
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

function isSalesBilling() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'sales_billing';
}

function isFieldStaff() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'field_staff';
}

function isCustomer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
