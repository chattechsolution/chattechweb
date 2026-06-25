<?php
// api/admin_api.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'stats') {
        // Total clients
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'");
        $total_clients = $stmt->fetch()['count'];

        // Pending Revenue
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM invoices WHERE status = 'pending'");
        $pending_revenue = $stmt->fetch()['total'] ?? 0;

        // Expiring services
        $target_date = date('Y-m-d', strtotime('+15 days'));
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE expiry_date <= ? AND expiry_date >= CURRENT_DATE");
        $stmt->execute([$target_date]);
        $expiring_services_count = $stmt->fetch()['count'] ?? 0;

        jsonResponse([
            'total_clients' => $total_clients,
            'pending_revenue' => $pending_revenue,
            'expiring_services_count' => $expiring_services_count
        ]);
    } elseif ($action === 'financials') {
        // Total Revenue (paid invoices)
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM invoices WHERE status = 'paid'");
        $total_revenue = $stmt->fetch()['total'] ?? 0;

        // Total Expenses
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses");
        $total_expenses = $stmt->fetch()['total'] ?? 0;

        $profit = $total_revenue - $total_expenses;
        $profit_margin = $total_revenue > 0 ? ($profit / $total_revenue) * 100 : 0;

        // List of expenses
        $stmt = $pdo->query("SELECT id, description, amount, date as expense_date FROM expenses ORDER BY date DESC, id DESC LIMIT 50");
        $expenses = $stmt->fetchAll();

        jsonResponse([
            'total_revenue' => $total_revenue,
            'total_expenses' => $total_expenses,
            'profit' => $profit,
            'profit_margin' => $profit_margin,
            'expenses' => $expenses
        ]);
    } elseif ($action === 'clients') {
        $stmt = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role = 'client' ORDER BY id DESC");
        $clients = $stmt->fetchAll();
        jsonResponse($clients);
    } elseif ($action === 'advanced_analytics') {
        // Group revenue by month for the current year
        global $db_type;
        $date_func = $db_type === 'sqlite' ? "strftime('%Y-%m', due_date)" : "DATE_FORMAT(due_date, '%Y-%m')";

        $stmt = $pdo->query("
            SELECT $date_func as month, SUM(amount) as revenue
            FROM invoices
            WHERE status = 'paid'
            GROUP BY month
            ORDER BY month ASC
        ");

        $results = $stmt->fetchAll();

        $months = [];
        $revenues = [];

        foreach ($results as $row) {
            $months[] = $row['month'];
            $revenues[] = $row['revenue'];
        }

        jsonResponse([
            'labels' => $months,
            'data' => $revenues
        ]);
    } elseif ($action === 'settings') {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        jsonResponse($settings);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create_client') {
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            jsonResponse(['error' => 'Missing required fields'], 400);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (role, name, email, password) VALUES ('client', ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (\PDOException $e) {
            jsonResponse(['error' => 'Database error, possibly duplicate email'], 500);
        }
    } elseif ($action === 'create_expense') {
        $description = $input['description'] ?? '';
        $amount = $input['amount'] ?? '';
        $expense_date = $input['date'] ?? '';

        if (empty($description) || empty($amount) || empty($expense_date)) {
            jsonResponse(['error' => 'Missing required fields'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, date) VALUES (?, ?, ?)");
        $stmt->execute([$description, $amount, $expense_date]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'provision_service') {
        $client_id = $input['client_id'] ?? '';
        $service_type = $input['service_type'] ?? '';
        $service_name = $input['service_name'] ?? '';
        $service_url = $input['service_url'] ?? null;
        $expiry_date = $input['expiry_date'] ?? '';

        if (empty($client_id) || empty($service_type) || empty($service_name) || empty($expiry_date)) {
            jsonResponse(['error' => 'Missing required fields'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO services (client_id, service_type, service_name, service_url, expiry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $service_type, $service_name, $service_url, $expiry_date]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'create_invoice') {
        $client_id = $input['client_id'] ?? '';
        $amount = $input['amount'] ?? '';
        $due_date = $input['due_date'] ?? '';

        if (empty($client_id) || empty($amount) || empty($due_date)) {
            jsonResponse(['error' => 'Missing required fields'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO invoices (client_id, amount, status, due_date) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$client_id, $amount, $due_date]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'save_settings') {
        $key_id = $input['razorpay_key_id'] ?? '';
        $key_secret = $input['razorpay_key_secret'] ?? '';

        // DB-agnostic insert or update
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");

        $stmt->execute(['razorpay_key_id']);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$key_id, 'razorpay_key_id']);
        } else {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['razorpay_key_id', $key_id]);
        }

        $stmt->execute(['razorpay_key_secret']);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$key_secret, 'razorpay_key_secret']);
        } else {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['razorpay_key_secret', $key_secret]);
        }

        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
