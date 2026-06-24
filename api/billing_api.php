<?php
// api/billing_api.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Fetch Razorpay credentials from DB
function getRazorpaySettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id', 'razorpay_key_secret')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'key_id' => $settings['razorpay_key_id'] ?? '',
        'key_secret' => $settings['razorpay_key_secret'] ?? ''
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create_order') {
        $invoice_id = $input['invoice_id'] ?? '';

        if (empty($invoice_id)) {
            jsonResponse(['error' => 'Invoice ID required'], 400);
        }

        // Verify invoice belongs to the logged-in client
        $client_id = getUserId();
        $stmt = $pdo->prepare("SELECT id, amount, status FROM invoices WHERE id = ? AND client_id = ?");
        $stmt->execute([$invoice_id, $client_id]);
        $invoice = $stmt->fetch();

        if (!$invoice || $invoice['status'] === 'paid') {
            jsonResponse(['error' => 'Invalid invoice or already paid'], 400);
        }

        $settings = getRazorpaySettings($pdo);
        if (empty($settings['key_id']) || empty($settings['key_secret'])) {
            jsonResponse(['error' => 'Razorpay settings not configured'], 500);
        }

        // Razorpay API requires amount in subunits (e.g., paise for INR)
        // Assuming base currency is INR or USD, multiply by 100.
        $amount_in_subunits = round((float)$invoice['amount'] * 100);

        $order_data = [
            'amount' => $amount_in_subunits,
            'currency' => 'USD', // Adjust to required currency
            'receipt' => 'inv_' . $invoice['id'],
            'payment_capture' => 1 // Auto capture
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_USERPWD, $settings['key_id'] . ':' . $settings['key_secret']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $order = json_decode($response, true);

            // Save Razorpay order ID to our DB
            $stmt = $pdo->prepare("UPDATE invoices SET razorpay_order_id = ? WHERE id = ?");
            $stmt->execute([$order['id'], $invoice['id']]);

            jsonResponse([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency'],
                'key_id' => $settings['key_id']
            ]);
        } else {
            jsonResponse(['error' => 'Failed to create Razorpay order', 'details' => json_decode($response, true)], 500);
        }

    } elseif ($action === 'verify_payment') {
        $razorpay_order_id = $input['razorpay_order_id'] ?? '';
        $razorpay_payment_id = $input['razorpay_payment_id'] ?? '';
        $razorpay_signature = $input['razorpay_signature'] ?? '';

        if (empty($razorpay_order_id) || empty($razorpay_payment_id) || empty($razorpay_signature)) {
            jsonResponse(['error' => 'Missing payment details'], 400);
        }

        $settings = getRazorpaySettings($pdo);

        // Verify signature
        $generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $settings['key_secret']);

        if (hash_equals($generated_signature, $razorpay_signature)) {
            // Payment is successful
            $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid', razorpay_payment_id = ? WHERE razorpay_order_id = ?");
            $stmt->execute([$razorpay_payment_id, $razorpay_order_id]);

            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Payment verification failed'], 400);
        }
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
