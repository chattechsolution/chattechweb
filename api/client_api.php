<?php
// api/client_api.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Admins can potentially view client data, but this is primarily for the logged-in client
$client_id = getUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'services') {
        $stmt = $pdo->prepare("SELECT id, service_type, service_name, service_url, expiry_date FROM services WHERE client_id = ? ORDER BY expiry_date ASC");
        $stmt->execute([$client_id]);
        $services = $stmt->fetchAll();
        jsonResponse($services);
    } elseif ($action === 'invoices') {
        $stmt = $pdo->prepare("SELECT id, amount, status, due_date FROM invoices WHERE client_id = ? ORDER BY due_date ASC");
        $stmt->execute([$client_id]);
        $invoices = $stmt->fetchAll();
        jsonResponse($invoices);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
