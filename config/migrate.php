<?php
require_once __DIR__ . '/db.php';

try {
    echo "Starting DB Migrations...\n";

    // 1. Notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('success', 'info', 'warning', 'danger') NOT NULL DEFAULT 'info',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        link VARCHAR(255) NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");
    echo "Notifications table verified/created.\n";

    // 2. Payments table columns
    $cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('payment_method', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_method ENUM('Bank Transfer', 'Bank Deposit', 'Cheque', 'Cash', 'Online Payment') NOT NULL DEFAULT 'Bank Transfer' AFTER amount");
        echo "Added payment_method column.\n";
    }
    if (!in_array('bank_name', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN bank_name VARCHAR(100) NULL AFTER payment_method");
        echo "Added bank_name column.\n";
    }
    if (!in_array('account_number', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN account_number VARCHAR(50) NULL AFTER bank_name");
        echo "Added account_number column.\n";
    }
    if (!in_array('reference_number', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN reference_number VARCHAR(100) NULL AFTER account_number");
        echo "Added reference_number column.\n";
    }
    if (!in_array('slip_path', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN slip_path VARCHAR(255) NULL AFTER reference_number");
        echo "Added slip_path column.\n";
    }
    if (!in_array('notes', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN notes TEXT NULL AFTER slip_path");
        echo "Added notes column.\n";
    }
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN status ENUM('Approved', 'Verified', 'Pending', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER notes");
        echo "Added status column.\n";
    } else {
        $pdo->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('Approved', 'Verified', 'Pending', 'Rejected') NOT NULL DEFAULT 'Pending'");
        $pdo->exec("UPDATE payments SET status = 'Approved' WHERE status = 'Verified'");
        echo "Updated payments status enum and legacy records.\n";
    }

    // 3. Employees table columns
    $empCols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('nic_number', $empCols)) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN nic_number VARCHAR(20) NULL AFTER full_name");
        echo "Added nic_number column to employees.\n";
    }
    if (!in_array('address', $empCols)) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN address TEXT NULL AFTER email");
        echo "Added address column to employees.\n";
    }

    // 4. Contact Messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_name VARCHAR(100) NOT NULL,
        sender_email VARCHAR(100) NOT NULL,
        sender_phone VARCHAR(30) NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        recipient_email VARCHAR(100) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Received',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    echo "Contact messages table verified/created.\n";

    echo "Migration Completed Successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
