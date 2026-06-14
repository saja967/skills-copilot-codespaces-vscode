
<?php


require_once __DIR__ . '/db_connect.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'الطريقة غير مسموحة.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !verify_csrf($input['csrf'] ?? null)) {
    json_response(['success' => false, 'message' => 'انتهت الجلسة، حدّث الصفحة وحاول مجددًا.'], 419);
}

$name = trim((string) ($input['name'] ?? ''));
$phone = normalize_phone((string) ($input['phone'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    json_response(['success' => false, 'message' => 'اكتب اسمًا صحيحًا.'], 422);
}

if (!valid_saudi_mobile($phone)) {
    json_response(['success' => false, 'message' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.'], 422);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
$stmt->execute([$phone]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $conn->prepare(
        "INSERT INTO users (name, phone, points, total_spent, reward_progress, reward_count, registered_by)
         VALUES (?, ?, 0, 0, 0, 0, 'customer')"
    );
    $stmt->execute([$name, $phone]);

    $stmt = $conn->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
}

$_SESSION['customer_phone'] = $user['phone'];

json_response([
    'success' => true,
    'message' => 'تم تفعيل حسابك وحفظه.',
    'user' => [
        'name' => $user['name'],
        'phone' => $user['phone'],
        'total_spent' => (float) $user['total_spent'],
        'reward_progress' => (float) $user['reward_progress'],
        'reward_count' => (int) $user['reward_count'],
    ],
]);


