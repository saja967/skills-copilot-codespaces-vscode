
<?php


if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = __DIR__ . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_save_path($sessionPath);
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database_setup.php';

$host = 'localhost';
$dbName = 'alburaih_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    ensure_database_schema($conn);
} catch (PDOException $e) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات. تأكد من تشغيل MySQL داخل XAMPP.');
}

// أضف هذه الدالة في نهاية ملف db_connect.php
function updateOrderStatus($conn, $orderId, $newStatus) {
    $timeColumn = null;
    switch ($newStatus) {
        case 'accepted': $timeColumn = 'accepted_at'; break;
        case 'preparing': $timeColumn = 'prepared_at'; break;
        case 'ready': $timeColumn = 'ready_at'; break;
        case 'completed': $timeColumn = 'completed_at'; break;
    }

    if ($timeColumn) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, $timeColumn = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
    }
}
