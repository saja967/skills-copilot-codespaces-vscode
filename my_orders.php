<?php

require_once __DIR__ . '/db_connect.php';

$statusClass = [
    'new' => 'status-new',
    'accepted' => 'status-accepted',
    'preparing' => 'status-preparing',
    'ready' => 'status-ready',
    'completed' => 'status-completed'
];

if (empty($_SESSION['customer_phone'])) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE user_phone = ?
    ORDER BY id DESC
");

$stmt->execute([$_SESSION['customer_phone']]);
$orders = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_order'])) {

    $orderId = (int)$_POST['order_id'];

    $stmt = $conn->prepare("
        SELECT *
        FROM orders
        WHERE id = ?
        AND user_phone = ?
        LIMIT 1
    ");

    $stmt->execute([
        $orderId,
        $_SESSION['customer_phone']
    ]);

    $order = $stmt->fetch();

    if ($order && $order['status'] === 'ready') {

        updateOrderStatus(
            $conn,
            $orderId,
            'completed'
        );

        header('Location: my_orders.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<link rel="stylesheet" href="Style.css">
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طلباتي</title>
<link rel="stylesheet" href="Style.css">
</head>
<body>
<style>
body{
    background:#16120f;
    color:#fff;
    font-family:Tajawal,sans-serif;
    padding:20px;
}

.orders-grid{
    max-width:1000px;
    margin:auto;
}

.order-card{
    background:#221a15;
    border-radius:15px;
    padding:20px;
    margin-bottom:20px;
    border:1px solid #3a2c23;
}

.order-card h3{
    margin:0 0 15px;
    color:#f5c16c;
}

.status{
    padding:6px 12px;
    border-radius:8px;
    font-weight:bold;
}

.status-new{
    background:#ffc107;
    color:#000;
}

.status-accepted{
    background:#17a2b8;
}

.status-preparing{
    background:#fd7e14;
}

.status-ready{
    background:#28a745;
}

.status-completed{
    background:#6c757d;
}

.receive-btn{
    background:#28a745;
    color:white;
    border:none;
    padding:10px 20px;
    border-radius:10px;
    cursor:pointer;
    margin-top:10px;
}

.receive-btn:hover{
    opacity:.9;
}
</style>
<h1>طلباتي</h1>

<?php foreach($orders as $order): ?>
<div class="order-card">

<h3>طلب رقم #<?= $order['id'] ?></h3>

<p><?= nl2br(htmlspecialchars($order['details'])) ?></p>

<p>
الإجمالي:
<?= number_format($order['total_price'],2) ?> ر.س
</p>

<?php
$statusText = [
'new'=>'جديد',
'accepted'=>'تم قبول الطلب',
'preparing'=>'جاري التجهيز',
'ready'=>'جاهز للاستلام',
'completed'=>'تم الاستلام'
];
?>

<p>
الحالة:
<span class="status status-<?= $order['status'] ?>">
<?= $statusText[$order['status']] ?? $order['status'] ?>
</span>
</p>

<?php if($order['status'] === 'ready'): ?>
<form method="post">
<input type="hidden" name="order_id" value="<?= $order['id'] ?>">
<button class="receive-btn" name="receive_order">
تم الاستلام
</button>
</form>
<?php endif; ?>

</div>

<?php endforeach; ?>

</body>
</html>