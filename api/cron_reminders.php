<?php
// api/cron_reminders.php

// Designed to be run via CLI or an automated cron job.
// E.g., `0 0 * * * php /path/to/api/cron_reminders.php`

// Enforce CLI only or add a secret token check if accessed via HTTP
if (php_sapi_name() !== 'cli' && !isset($_GET['token'])) {
    http_response_code(403);
    die("Forbidden");
}

require_once __DIR__ . '/config.php';

// We want to query services expiring in exactly 30, 7, and 1 days.
// DATEDIFF in MySQL, or we can just compute the dates in PHP and query.
// This is more database-agnostic.
$intervals = [30, 7, 1];

// Find admin email for notifications
$admin_stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
$admin = $admin_stmt->fetch();
$admin_email = $admin ? $admin['email'] : 'admin@chattechsolutions.com';

echo "Starting expiry reminders processing...\n";

foreach ($intervals as $days) {
    $target_date = date('Y-m-d', strtotime("+$days days"));

    $stmt = $pdo->prepare("
        SELECT s.id, s.service_name, s.service_type, s.expiry_date, u.name as client_name, u.email as client_email
        FROM services s
        JOIN users u ON s.client_id = u.id
        WHERE s.expiry_date = ?
    ");
    $stmt->execute([$target_date]);
    $expiring_services = $stmt->fetchAll();

    foreach ($expiring_services as $service) {
        $client_email = $service['client_email'];
        $client_name = $service['client_name'];
        $service_name = $service['service_name'];
        $expiry_date = $service['expiry_date'];

        $subject = "Notice: Your service '$service_name' expires in $days days";

        $message_body = "Hello $client_name,\n\n";
        $message_body .= "This is an automated reminder that your service ($service_name) is scheduled to expire on $expiry_date.\n";
        $message_body .= "Please log into the Chat Tech Solutions client portal to arrange renewal and avoid service interruption.\n\n";
        $message_body .= "Thank you,\nChat Tech Solutions";

        $headers = "From: $admin_email\r\n";
        $headers .= "Reply-To: $admin_email\r\n";

        // Send email to client
        mail($client_email, $subject, $message_body, $headers);

        // Notify admin as well
        $admin_subject = "Admin Copy: Expiry Reminder Sent to $client_name";
        $admin_message = "A $days-day expiry reminder was sent to $client_name ($client_email) for service: $service_name.";
        mail($admin_email, $admin_subject, $admin_message, $headers);

        echo "Sent $days-day reminder for service '{$service['service_name']}' to {$client_email} and admin.\n";
    }
}

echo "Finished processing expiry reminders.\n";
