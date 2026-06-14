<?php


require_once __DIR__ . '/db_connect.php';

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['status'];
    
    // استدعاء الدالة التي وضعناها في db_connect.php
    updateOrderStatus($conn, $orderId, $newStatus);
    
    header('Location: staff_dashboard.php?msg=updated');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'الطريقة غير مسموحة.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !verify_csrf($input['csrf'] ?? null)) {
    json_response(['success' => false, 'message' => 'انتهت الجلسة، حدّث الصفحة وحاول مجددًا.'], 419);
}

if (empty($_SESSION['customer_phone'])) {
    json_response(['success' => false, 'message' => 'سجّل حسابك أولًا حتى نحفظ الطلب والمكافأة.'], 401);
}

$orderToken = (string) ($input['order_token'] ?? '');
$requestedItems = $input['items'] ?? [];
$useReward = !empty($input['use_reward']);

if (preg_match('/^[a-zA-Z0-9-]{16,64}$/', $orderToken) !== 1) {
    json_response(['success' => false, 'message' => 'تعذر إنشاء رقم آمن للطلب.'], 422);
}

if (!is_array($requestedItems) || $requestedItems === []) {
    json_response(['success' => false, 'message' => 'السلة فارغة.'], 422);
}

$catalog = $menu_items;
foreach ($offers as $id => $offer) {
    $catalog['offer-' . $id] = [
        'title' => $offer['title'],
        'price' => $offer['price'],
    ];
}

$items = [];
$subtotal = 0.0;

foreach ($requestedItems as $requestedItem) {
    $id = (string) ($requestedItem['id'] ?? '');
    $quantity = (int) ($requestedItem['quantity'] ?? 0);

    if (!isset($catalog[$id]) || $quantity < 1 || $quantity > 30) {
        json_response(['success' => false, 'message' => 'يوجد عنصر غير صالح في السلة.'], 422);
    }

    $unitPrice = (float) $catalog[$id]['price'];
    $lineTotal = round($unitPrice * $quantity, 2);
    $subtotal += $lineTotal;
    $items[] = [
        'id' => $id,
        'title' => $catalog[$id]['title'],
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
    ];
}

$subtotal = round($subtotal, 2);
if ($subtotal <= 0 || $subtotal > 5000) {
    json_response(['success' => false, 'message' => 'إجمالي الطلب غير صالح.'], 422);
}

function whatsapp_payload(array $order, array $user): array
{
    $message = "*" . APP_NAME . " - طلب جديد #{$order['id']}*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━\n";
    $message .= "العميل: {$user['name']}\n";
    $message .= "الجوال: {$user['phone']}\n";
    $message .= "━━━━━━━━━━━━━━━━━━━\n";
    $message .= $order['details'] . "\n";
    $message .= "━━━━━━━━━━━━━━━━━━━\n";
    $message .= "الإجمالي قبل الخصم: " . number_format((float) $order['subtotal'], 2) . " ر.س\n";

    if ((float) $order['discount_amount'] > 0) {
        $message .= "خصم المكافأة: -" . number_format((float) $order['discount_amount'], 2) . " ر.س\n";
    }

    $message .= "*المبلغ النهائي: " . number_format((float) $order['total_price'], 2) . " ر.س*\n";

    if ((int) $order['rewards_earned'] > 0) {
        $message .= "مكافآت جديدة: +" . (int) $order['rewards_earned'] . " خصم 5%\n";
    }

    return [
        'message' => $message,
        'url' => 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message),
    ];
}

$existingStmt = $conn->prepare('SELECT * FROM orders WHERE order_token = ? LIMIT 1');
$existingStmt->execute([$orderToken]);
$existingOrder = $existingStmt->fetch();

if ($existingOrder) {
    if (!hash_equals((string) $existingOrder['user_phone'], (string) $_SESSION['customer_phone'])) {
        json_response(['success' => false, 'message' => 'رقم الطلب مستخدم مسبقًا. أعد المحاولة.'], 409);
    }

    $userStmt = $conn->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $userStmt->execute([$_SESSION['customer_phone']]);
    $user = $userStmt->fetch();
    $whatsapp = whatsapp_payload($existingOrder, $user);

    json_response([
        'success' => true,
        'duplicate' => true,
        'order_id' => (int) $existingOrder['id'],
        'whatsapp_url' => $whatsapp['url'],
        'user' => [
            'name' => $user['name'],
            'phone' => $user['phone'],
            'total_spent' => (float) $user['total_spent'],
            'reward_progress' => (float) $user['reward_progress'],
            'reward_count' => (int) $user['reward_count'],
        ],
    ]);
}

try {
    $conn->beginTransaction();

    $userStmt = $conn->prepare('SELECT * FROM users WHERE phone = ? FOR UPDATE');
    $userStmt->execute([$_SESSION['customer_phone']]);
    $user = $userStmt->fetch();

    if (!$user) {
        throw new RuntimeException('الحساب غير موجود.');
    }

    $rewardUsed = $useReward && (int) $user['reward_count'] > 0 ? 1 : 0;
    $discountPercent = $rewardUsed ? REWARD_DISCOUNT_PERCENT : 0;
    $discountAmount = round($subtotal * ($discountPercent / 100), 2);
    $total = round($subtotal - $discountAmount, 2);

    $newProgressTotal = round((float) $user['reward_progress'] + $total, 2);
    $rewardsEarned = (int) floor($newProgressTotal / REWARD_THRESHOLD);
    $rewardProgress = round(fmod($newProgressTotal, REWARD_THRESHOLD), 2);
    $rewardCount = max(0, (int) $user['reward_count'] - $rewardUsed + $rewardsEarned);

    $detailLines = [];
    foreach ($items as $item) {
        $detailLines[] = sprintf(
            '%s × %d = %.2f ر.س',
            $item['title'],
            $item['quantity'],
            $item['line_total']
        );
    }
    $details = implode("\n", $detailLines);

    $insert = $conn->prepare(
        "INSERT INTO orders (
            order_token, user_phone, customer_name, details, items_json, subtotal,
            discount_percent, discount_amount, total_price, reward_used,
            rewards_earned, source, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'website', 'new')"
    );
    $insert->execute([
        $orderToken,
        $user['phone'],
        $user['name'],
        $details,
        json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $subtotal,
        $discountPercent,
        $discountAmount,
        $total,
        $rewardUsed,
        $rewardsEarned,
    ]);

    $orderId = (int) $conn->lastInsertId();

    $update = $conn->prepare(
        'UPDATE users
         SET total_spent = total_spent + ?,
             reward_progress = ?,
             reward_count = ?,
             points = ?,
             last_order_at = NOW()
         WHERE id = ?'
    );
    $update->execute([$total, $rewardProgress, $rewardCount, $rewardCount, $user['id']]);

    $conn->commit();

    $user['total_spent'] = round((float) $user['total_spent'] + $total, 2);
    $user['reward_progress'] = $rewardProgress;
    $user['reward_count'] = $rewardCount;

    $order = [
        'id' => $orderId,
        'details' => $details,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'total_price' => $total,
        'rewards_earned' => $rewardsEarned,
    ];
    $whatsapp = whatsapp_payload($order, $user);

 json_response([
    'success' => true,
    'message' => 'تم حفظ الطلب وتحديث مكافأتك.',
    'order_id' => $orderId,
    'subtotal' => $subtotal,
    'discount_amount' => $discountAmount,
    'total' => $total,
    'reward_used' => (bool) $rewardUsed,
    'rewards_earned' => $rewardsEarned,
    'whatsapp_url' => $whatsapp['url'],
    'user' => [
        'name' => $user['name'],
        'phone' => $user['phone'],
        'total_spent' => (float) $user['total_spent'],
        'reward_progress' => (float) $user['reward_progress'],
        'reward_count' => (int) $user['reward_count'],
    ],
]);

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    json_response([
        'success' => false,
        'message' => 'تعذر حفظ الطلب. حاول مرة أخرى.'
    ], 500);

}