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
    } elseif ($action === 'tickets') {
        $stmt = $pdo->prepare("SELECT id, subject, message, status, created_at FROM tickets WHERE client_id = ? ORDER BY id DESC");
        $stmt->execute([$client_id]);
        $tickets = $stmt->fetchAll();
        jsonResponse($tickets);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'update_profile') {
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email)) {
            jsonResponse(['error' => 'Name and email are required'], 400);
        }

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hashed_password, $client_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $client_id]);
        }

        $_SESSION['user_name'] = $name; // Update session
        jsonResponse(['success' => true]);
    } elseif ($action === 'create_ticket') {
        $subject = $input['subject'] ?? '';
        $message = $input['message'] ?? '';

        if (empty($subject) || empty($message)) {
            jsonResponse(['error' => 'Subject and message are required'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO tickets (client_id, subject, message, status) VALUES (?, ?, ?, 'open')");
        $stmt->execute([$client_id, $subject, $message]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
