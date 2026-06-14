
<?php


function column_exists(PDO $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (bool) $stmt->fetchColumn();
}

function index_exists(PDO $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);

    return (bool) $stmt->fetchColumn();
}

function ensure_database_schema(PDO $conn): void
{
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(15) NOT NULL UNIQUE,
            points INT NOT NULL DEFAULT 0,
            total_spent DECIMAL(10,2) NOT NULL DEFAULT 0,
            reward_progress DECIMAL(10,2) NOT NULL DEFAULT 0,
            reward_count INT NOT NULL DEFAULT 0,
            registered_by VARCHAR(20) NOT NULL DEFAULT 'customer',
            last_order_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_token VARCHAR(64) NULL,
            user_phone VARCHAR(15) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            details TEXT NOT NULL,
            items_json LONGTEXT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_price DECIMAL(10,2) NOT NULL,
            reward_used TINYINT(1) NOT NULL DEFAULT 0,
            rewards_earned INT NOT NULL DEFAULT 0,
            source VARCHAR(20) NOT NULL DEFAULT 'website',
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $userColumns = [
        'total_spent' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER points",
        'reward_progress' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_spent",
        'reward_count' => "INT NOT NULL DEFAULT 0 AFTER reward_progress",
        'registered_by' => "VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER reward_count",
        'last_order_at' => "DATETIME NULL AFTER registered_by",
    ];

    foreach ($userColumns as $column => $definition) {
        if (!column_exists($conn, 'users', $column)) {
            $conn->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }

    $orderColumns = [
        'order_token' => "VARCHAR(64) NULL AFTER id",
        'items_json' => "LONGTEXT NULL AFTER details",
        'subtotal' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER items_json",
        'discount_percent' => "DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER subtotal",
        'discount_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_percent",
        'reward_used' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER total_price",
        'rewards_earned' => "INT NOT NULL DEFAULT 0 AFTER reward_used",
        'source' => "VARCHAR(20) NOT NULL DEFAULT 'website' AFTER rewards_earned",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'new' AFTER source",
    ];

    foreach ($orderColumns as $column => $definition) {
        if (!column_exists($conn, 'orders', $column)) {
            $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
        }
    }

    if (!column_exists($conn, 'staff', 'created_at')) {
        $conn->exec('ALTER TABLE staff ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    if (!index_exists($conn, 'orders', 'uniq_order_token')) {
        $conn->exec('ALTER TABLE orders ADD UNIQUE INDEX uniq_order_token (order_token)');
    }

    if (!index_exists($conn, 'orders', 'idx_orders_created_at')) {
        $conn->exec('ALTER TABLE orders ADD INDEX idx_orders_created_at (created_at)');
    }

    if (!index_exists($conn, 'orders', 'idx_orders_phone')) {
        $conn->exec('ALTER TABLE orders ADD INDEX idx_orders_phone (user_phone)');
    }

    if (!index_exists($conn, 'staff', 'uniq_staff_username')) {
        $conn->exec('ALTER TABLE staff ADD UNIQUE INDEX uniq_staff_username (username)');
    }

    $conn->exec('UPDATE orders SET subtotal = total_price WHERE subtotal = 0');
}


