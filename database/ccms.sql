CREATE DATABASE IF NOT EXISTS ccms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ccms_db;

-- User & Client Management
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100) NOT NULL,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    email          VARCHAR(100) NOT NULL UNIQUE,
    phone          VARCHAR(20)  NULL,
    nic_number     VARCHAR(20)  NULL,
    date_of_birth  DATE         NULL,
    gender         ENUM('Male','Female','Other') NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('Administrator','Project Manager','Finance Officer','Procurement Staff','Site Staff','Client') NOT NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until   DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clients (
    client_id      INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    nic_number     VARCHAR(20)  NULL,
    date_of_birth  DATE         NULL,
    gender         ENUM('Male','Female','Other') NULL,
    email          VARCHAR(100),
    phone          VARCHAR(20),
    address        VARCHAR(255),
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Project Management
CREATE TABLE employees (
    employee_id  INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100) NOT NULL,
    nic_number   VARCHAR(20)  NULL,
    role_title   VARCHAR(50),
    phone        VARCHAR(20),
    email        VARCHAR(100),
    address      VARCHAR(255) NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE projects (
    project_id         INT AUTO_INCREMENT PRIMARY KEY,
    project_name       VARCHAR(150) NOT NULL,
    client_id          INT NOT NULL,
    project_type       ENUM('Residential','Commercial','Infrastructure') NOT NULL,
    location           VARCHAR(150),
    start_date         DATE NOT NULL,
    end_date           DATE NOT NULL,
    estimated_budget   DECIMAL(14,2) NOT NULL,
    project_manager_id INT,
    status             ENUM('Planned','Ongoing','On Hold','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
    progress_percent   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id),
    FOREIGN KEY (project_manager_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE project_employees (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT NOT NULL,
    employee_id  INT NOT NULL,
    assigned_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    UNIQUE KEY uniq_assignment (project_id, employee_id)
) ENGINE=InnoDB;

CREATE TABLE project_progress_log (
    log_id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL,
    progress_percent TINYINT UNSIGNED NOT NULL,
    note             VARCHAR(255),
    justification    VARCHAR(255) NULL,
    updated_by       INT NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Material & Inventory Management--
CREATE TABLE materials (
    material_id    INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    unit           VARCHAR(20) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level  INT NOT NULL DEFAULT 0,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory (
    material_id      INT PRIMARY KEY,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stock_transactions (
    txn_id       INT AUTO_INCREMENT PRIMARY KEY,
    material_id  INT NOT NULL,
    type         ENUM('IN','OUT') NOT NULL,
    quantity     INT NOT NULL,
    reference    VARCHAR(100),
    project_id   INT NULL,
    created_by   INT NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(material_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE material_requests (
    request_id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id           INT NOT NULL,
    material_id          INT NOT NULL,
    quantity_requested   INT NOT NULL,
    status               ENUM('Pending','Approved','Rejected','Issued') NOT NULL DEFAULT 'Pending',
    requested_by         INT NOT NULL,
    approved_by          INT NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at           TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (material_id) REFERENCES materials(material_id),
    FOREIGN KEY (requested_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Finance Management
CREATE TABLE budgets (
    budget_id        INT AUTO_INCREMENT PRIMARY KEY,
    project_id        INT NOT NULL UNIQUE,
    allocated_amount   DECIMAL(14,2) NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expenses (
    expense_id    INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NOT NULL,
    category       VARCHAR(50) NOT NULL,
    amount         DECIMAL(14,2) NOT NULL,
    description    VARCHAR(255),
    overrun_justification VARCHAR(255) NULL,
    recorded_by    INT NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL,
    amount           DECIMAL(14,2) NOT NULL,
    payment_method   ENUM('Bank Transfer', 'Bank Deposit', 'Cheque', 'Cash', 'Online Payment') NOT NULL DEFAULT 'Bank Transfer',
    bank_name        VARCHAR(100) NULL,
    account_number   VARCHAR(50) NULL,
    reference_number VARCHAR(100) NULL,
    slip_path        VARCHAR(255) NULL,
    notes            TEXT NULL,
    payment_type     ENUM('Client Payment','Supplier Payment','Other') NOT NULL,
    payment_date     DATE NOT NULL,
    status           ENUM('Approved', 'Verified', 'Pending', 'Rejected') NOT NULL DEFAULT 'Pending',
    recorded_by      INT NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Supplier Management
CREATE TABLE suppliers (
    supplier_id     INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    contact_person    VARCHAR(100),
    phone             VARCHAR(20),
    email             VARCHAR(100),
    address           VARCHAR(255),
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
    po_id        INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT NOT NULL,
    status        ENUM('Pending','Approved','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    created_by    INT NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at  TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
    item_id      INT AUTO_INCREMENT PRIMARY KEY,
    po_id         INT NOT NULL,
    material_id   INT NOT NULL,
    quantity      INT NOT NULL,
    unit_price    DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(material_id)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    action      VARCHAR(100) NOT NULL,
    table_name  VARCHAR(50) NOT NULL,
    record_id   INT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    title           VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    type            ENUM('success', 'info', 'warning', 'danger') NOT NULL DEFAULT 'info',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    link            VARCHAR(255) NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, email, password_hash, role, status)
VALUES ('System Administrator', 'admin', 'admin@chaminda.lk',
        '$2b$10$4jR7E/rE7U9VdqrZTjWx7.bg.NSQc1meK8hDOWGFKRPZLC3qQOw56', -- password: Admin@123 (CHANGE after first login)
        'Administrator', 'active');
