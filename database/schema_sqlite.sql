CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role TEXT NOT NULL DEFAULT 'client',
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    service_type TEXT NOT NULL,
    service_name TEXT NOT NULL,
    service_url TEXT,
    expiry_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    due_date DATE NOT NULL,
    razorpay_order_id TEXT,
    razorpay_payment_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL,
    amount REAL NOT NULL,
    date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed Data
INSERT INTO users (role, name, email, password) VALUES
('admin', 'Chat Tech Admin', 'admin@chattechsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO users (role, name, email, password) VALUES
('client', 'Green Court Cottages', 'contact@greencourtcottages.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO services (client_id, service_type, service_name, service_url, expiry_date) VALUES
(2, 'hosting', 'Web Hosting Package', 'chattechsolutions.unaux.com', date('now', '+10 days'));

INSERT INTO invoices (client_id, amount, status, due_date) VALUES
(2, 150.00, 'pending', date('now', '+14 days'));

INSERT INTO settings (setting_key, setting_value) VALUES
('razorpay_key_id', 'rzp_test_YOUR_KEY_ID'),
('razorpay_key_secret', 'YOUR_KEY_SECRET');

INSERT INTO expenses (description, amount, date) VALUES
('Server Hosting', 50.00, date('now', '-5 days'));
