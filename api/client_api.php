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
        $stmt = $pdo->prepare("SELECT id, subject, message, status, admin_reply, created_at FROM tickets WHERE client_id = ? ORDER BY id DESC");
        $stmt->execute([$client_id]);
        $tickets = $stmt->fetchAll();
        jsonResponse($tickets);
    } elseif ($action === 'quotes') {
        $stmt = $pdo->prepare("SELECT id, amount, description, status, created_at FROM quotes WHERE client_id = ? ORDER BY id DESC");
        $stmt->execute([$client_id]);
        $quotes = $stmt->fetchAll();
        jsonResponse($quotes);
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
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $input['phone'] ?? '', $input['address'] ?? '', $hashed_password, $client_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $email, $input['phone'] ?? '', $input['address'] ?? '', $client_id]);
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
    } elseif ($action === 'approve_quote') {
        $quote_id = $input['quote_id'] ?? '';

        $stmt = $pdo->prepare("SELECT amount, description, status FROM quotes WHERE id = ? AND client_id = ?");
        $stmt->execute([$quote_id, $client_id]);
        $quote = $stmt->fetch();

        if (!$quote || $quote['status'] !== 'pending') {
            jsonResponse(['error' => 'Invalid or already processed quote'], 400);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE quotes SET status = 'approved' WHERE id = ?")->execute([$quote_id]);

            // Auto generate invoice
            $pdo->prepare("INSERT INTO invoices (client_id, amount, status, due_date) VALUES (?, ?, 'pending', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY))")
                ->execute([$client_id, $quote['amount']]);

            $pdo->commit();
            jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Failed to approve quote'], 500);
        }
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
