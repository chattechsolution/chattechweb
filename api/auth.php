<?php
// api/auth.php
require_once 'config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Read JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    $action = $input['action'] ?? '';

    if ($action === 'login') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            jsonResponse(['error' => 'Email and password are required'], 400);
        }

        $stmt = $pdo->prepare("SELECT id, role, name, email, phone, address, status, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') { jsonResponse(['error' => 'Account suspended'], 403); }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'role' => $user['role'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'address' => $user['address']
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} elseif ($method === 'GET') {
    // Check current session
    if (isLoggedIn()) {
        jsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'role' => $_SESSION['user_role'],
                'name' => $_SESSION['user_name']
            ]
        ]);
    } else {
        jsonResponse(['authenticated' => false], 401);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
