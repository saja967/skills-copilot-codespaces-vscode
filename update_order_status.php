<?php

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['staff_id'])) {
    die('غير مصرح');
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$allowedStatuses = [
    'accepted',
    'preparing',
    'ready'
];

if (!in_array($status, $allowedStatuses, true)) {
    die('حالة غير مسموح بها');
}

$stmt = $conn->prepare("
    UPDATE orders
    SET status = ?
    WHERE id = ?
");

$stmt->execute([$status, $orderId]);

header('Location: staff_dashboard.php');
exit;