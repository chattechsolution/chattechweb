CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `service_type` ENUM('domain', 'hosting', 'other') NOT NULL,
    `service_name` VARCHAR(255) NOT NULL,
    `service_url` VARCHAR(255) NULL,
    `expiry_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    `due_date` DATE NOT NULL,
    `razorpay_order_id` VARCHAR(255) NULL,
    `razorpay_payment_id` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(255) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed Data
-- Passwords should be hashed. Using dummy hashes for seed data (e.g. password123)
-- Admin: password123
INSERT INTO `users` (`role`, `name`, `email`, `password`) VALUES
('admin', 'Chat Tech Admin', 'admin@chattechsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Client: password123
INSERT INTO `users` (`role`, `name`, `email`, `password`) VALUES
('client', 'Green Court Cottages', 'contact@greencourtcottages.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Service for Green Court Cottages (Client ID 2 assuming AUTO_INCREMENT starts at 1 and admin is 1)
-- Web hosting service expiring in 6 months
INSERT INTO `services` (`client_id`, `service_type`, `service_name`, `service_url`, `expiry_date`) VALUES
(2, 'hosting', 'Web Hosting Package', 'chattechsolutions.unaux.com', DATE_ADD(CURRENT_DATE, INTERVAL 6 MONTH));

-- Optional Invoice for the client
INSERT INTO `invoices` (`client_id`, `amount`, `status`, `due_date`) VALUES
(2, 150.00, 'pending', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY));

-- Seed Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('razorpay_key_id', 'rzp_test_YOUR_KEY_ID'),
('razorpay_key_secret', 'YOUR_KEY_SECRET');

CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
