CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('super_admin', 'sales_billing', 'field_staff', 'customer') NOT NULL DEFAULT 'customer',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `staff_profiles` (
    `user_id` INT PRIMARY KEY,
    `base_salary` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `permissions` JSON NULL,
    `status` ENUM('active', 'inactive', 'on_leave') NOT NULL DEFAULT 'active',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(255) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `leads_quotations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NULL,
    `customer_phone` VARCHAR(20) NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'sent', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `quotation_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quotation_id` INT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `total_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (`quotation_id`) REFERENCES `leads_quotations`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `amc_contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `total_services_included` INT NOT NULL DEFAULT 0,
    `frequency` ENUM('monthly', 'quarterly', 'half_yearly', 'yearly') NOT NULL DEFAULT 'quarterly',
    `status` ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `assigned_technician_id` INT NULL,
    `type` ENUM('one-time', 'amc') NOT NULL DEFAULT 'one-time',
    `amc_id` INT NULL,
    `scheduled_date` DATETIME NOT NULL,
    `treatments_required` TEXT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `customer_signature` TEXT NULL,
    `job_notes` TEXT NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`amc_id`) REFERENCES `amc_contracts`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `job_id` INT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `gst_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'partial', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    `due_date` DATE NOT NULL,
    `hsn_code` VARCHAR(50) NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `total_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_mode` ENUM('cash', 'upi', 'bank_transfer', 'razorpay') NOT NULL,
    `razorpay_tx_id` VARCHAR(255) NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `travel_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `start_km` INT NOT NULL,
    `end_km` INT NULL,
    `allowance_amount` DECIMAL(10, 2) NULL,
    `log_date` DATE NOT NULL,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_name` VARCHAR(255) NOT NULL,
    `current_stock` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `unit` VARCHAR(50) NOT NULL,
    `low_stock_threshold` DECIMAL(10, 2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `inventory_usage` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity_used` DECIMAL(10, 2) NOT NULL,
    `usage_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_name` VARCHAR(255) NOT NULL,
    `assigned_to` INT NULL,
    `condition_status` ENUM('good', 'needs_repair', 'broken') NOT NULL DEFAULT 'good',
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `receipt_url` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `expense_date` DATE NOT NULL,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `salary_advances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `request_date` DATE NOT NULL,
    `reason` TEXT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'deducted_from_salary') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `helpdesk_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `type` ENUM('service_request', 'complaint_service', 'complaint_staff', 'quotation_request') NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Seed Data for Default Admin
INSERT INTO `users` (`role`, `name`, `email`, `password`) VALUES
('super_admin', 'Admin', 'admin@pestcontrol.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Seed initial settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('gst_enabled', '0'),
('razorpay_enabled', '0'),
('travel_rate_per_km', '3'),
('company_name', 'Pest Control Co.'),
('company_logo_url', ''),
('company_address', ''),
('company_gst_number', '');
