<?php
// api/admin_api.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

function logAudit($pdo, $action, $details) {
    $user_id = getUserId();
    if ($user_id) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    }
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
        $stmt = $pdo->query("SELECT SUM(amount) as total FROM invoices WHERE status = 'paid'");
        $total_revenue = $stmt->fetch()['total'] ?? 0;

        $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses");
        $total_expenses = $stmt->fetch()['total'] ?? 0;

        $profit = $total_revenue - $total_expenses;
        $profit_margin = $total_revenue > 0 ? ($profit / $total_revenue) * 100 : 0;

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
        $stmt = $pdo->query("SELECT id, name, email, phone, address, status, created_at FROM users WHERE role = 'client' ORDER BY id DESC");
        $clients = $stmt->fetchAll();
        jsonResponse($clients);
    } elseif ($action === 'staff') {
        $stmt = $pdo->query("SELECT id, name, email, role, phone, address, created_at FROM users WHERE role IN ('admin', 'staff') ORDER BY id DESC");
        $staff = $stmt->fetchAll();
        jsonResponse($staff);
    } elseif ($action === 'projects') {
        $stmt = $pdo->query("
            SELECT p.id, p.title, p.description, p.status, p.assigned_to, u.name as assignee_name
            FROM project_tasks p
            LEFT JOIN users u ON p.assigned_to = u.id
            ORDER BY p.status ASC, p.id DESC
        ");
        jsonResponse($stmt->fetchAll());
    } elseif ($action === 'tickets') {
        $stmt = $pdo->query("SELECT t.id, t.subject, t.message, t.status, t.admin_reply, t.created_at, u.name as client_name FROM tickets t JOIN users u ON t.client_id = u.id ORDER BY t.status DESC, t.id DESC");
        jsonResponse($stmt->fetchAll());
    } elseif ($action === 'advanced_analytics') {
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
            $client_id = $pdo->lastInsertId();
            logAudit($pdo, 'Create Client', 'Created client ID: ' . $client_id);
            jsonResponse(['success' => true, 'id' => $client_id]);
        } catch (\PDOException $e) {
            jsonResponse(['error' => 'Database error, possibly duplicate email'], 500);
        }
    } elseif ($action === 'update_client') {
        $id = $input['id'] ?? '';
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $address = $input['address'] ?? '';
        if (empty($id) || empty($name) || empty($email)) jsonResponse(['error' => 'Missing fields'], 400);

        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, address=? WHERE id=? AND role='client'");
        $stmt->execute([$name, $email, $phone, $address, $id]);
        logAudit($pdo, 'Update Client', 'Updated client ID ' . $id);
        jsonResponse(['success' => true]);

    } elseif ($action === 'suspend_client') {
        $id = $input['id'] ?? '';
        if (empty($id)) jsonResponse(['error' => 'Missing fields'], 400);
        $pdo->prepare("UPDATE users SET status='suspended' WHERE id=? AND role='client'")->execute([$id]);
        logAudit($pdo, 'Suspend Client', 'Suspended client ID ' . $id);
        jsonResponse(['success' => true]);

    } elseif ($action === 'delete_client') {
        $id = $input['id'] ?? '';
        if (empty($id)) jsonResponse(['error' => 'Missing fields'], 400);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='client'");
        $stmt->execute([$id]);
        logAudit($pdo, 'Delete Client', 'Deleted client ID ' . $id);
        jsonResponse(['success' => true]);
    } elseif ($action === 'create_staff') {
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'staff';
        if (empty($name) || empty($email) || empty($password)) jsonResponse(['error' => 'Missing fields'], 400);
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (role, name, email, password) VALUES (?, ?, ?, ?)")->execute([$role, $name, $email, $hashed]);
        logAudit($pdo, 'Create Staff', 'Created staff: ' . $email);
        jsonResponse(['success' => true]);
    } elseif ($action === 'update_staff') {
        $id = $input['id'] ?? '';
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $role = $input['role'] ?? 'staff';
        $password = $input['password'] ?? '';
        if (empty($id) || empty($name) || empty($email)) jsonResponse(['error' => 'Missing fields'], 400);
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=? AND role IN ('admin', 'staff')")->execute([$name, $email, $role, $hashed, $id]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=? AND role IN ('admin', 'staff')")->execute([$name, $email, $role, $id]);
        }
        logAudit($pdo, 'Update Staff', 'Updated staff ID ' . $id);
        jsonResponse(['success' => true]);
    } elseif ($action === 'delete_staff') {
        $id = $input['id'] ?? '';
        // Prevent deleting oneself
        if (empty($id) || $id == getUserId()) jsonResponse(['error' => 'Invalid operation'], 400);
        $pdo->prepare("DELETE FROM users WHERE id=? AND role IN ('admin', 'staff')")->execute([$id]);
        logAudit($pdo, 'Delete Staff', 'Deleted staff ID ' . $id);
        jsonResponse(['success' => true]);
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
    } elseif ($action === 'pos_checkout') {
        $client_id = $input['client_id'] ?? '';
        $amount = $input['amount'] ?? '';
        $description = $input['description'] ?? 'POS Checkout';
        $payment_method = $input['payment_method'] ?? 'cash';

        if (empty($client_id) || empty($amount)) {
            jsonResponse(['error' => 'Missing required fields'], 400);
        }
        // Generate an instant paid invoice
        $stmt = $pdo->prepare("INSERT INTO invoices (client_id, amount, status, due_date) VALUES (?, ?, 'paid', CURRENT_DATE)");
        $stmt->execute([$client_id, $amount]);
        $invoice_id = $pdo->lastInsertId();

        logAudit($pdo, 'POS Checkout', "Checked out client $client_id for $" . $amount . " via $payment_method. Desc: $description");
        jsonResponse(['success' => true, 'invoice_id' => $invoice_id]);
    } elseif ($action === 'create_task') {
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';
        $status = $input['status'] ?? 'To Do';
        $assigned_to = !empty($input['assigned_to']) ? $input['assigned_to'] : null;

        if (empty($title)) {
            jsonResponse(['error' => 'Title is required'], 400);
        }
        $stmt = $pdo->prepare("INSERT INTO project_tasks (title, description, status, assigned_to) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $description, $status, $assigned_to]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'update_task') {
        $id = $input['id'] ?? '';
        $status = $input['status'] ?? '';
        if (empty($id) || empty($status)) jsonResponse(['error' => 'Missing fields'], 400);

        $stmt = $pdo->prepare("UPDATE project_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        jsonResponse(['success' => true]);
    } elseif ($action === 'reply_ticket') {
        $id = $input['id'] ?? '';
        $admin_reply = $input['admin_reply'] ?? '';
        $status = $input['status'] ?? 'closed';
        if (empty($id) || empty($admin_reply)) jsonResponse(['error' => 'Missing fields'], 400);

        $stmt = $pdo->prepare("UPDATE tickets SET admin_reply = ?, status = ? WHERE id = ?");
        $stmt->execute([$admin_reply, $status, $id]);
        logAudit($pdo, 'Reply Ticket', 'Replied to ticket ID ' . $id);
        jsonResponse(['success' => true]);
    } elseif ($action === 'save_settings') {
        $key_id = $input['razorpay_key_id'] ?? '';
        $key_secret = $input['razorpay_key_secret'] ?? '';

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
