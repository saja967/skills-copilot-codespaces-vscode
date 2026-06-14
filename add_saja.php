
<?php


require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login_staff.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf($_POST['csrf'] ?? null)) {
    header('Location: staff_dashboard.php');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (mb_strlen($username) < 2 || mb_strlen($username) > 50 || strlen($password) < 6) {
    $_SESSION['staff_flash'] = ['type' => 'error', 'message' => 'اسم الموظف مطلوب وكلمة المرور لا تقل عن 6 أحرف.'];
    header('Location: staff_dashboard.php#staff-management');
    exit;
}

try {
    $stmt = $conn->prepare('INSERT INTO staff (username, password) VALUES (?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $_SESSION['staff_flash'] = ['type' => 'success', 'message' => 'تمت إضافة الموظف الجديد بنجاح.'];
} catch (PDOException $e) {
    $_SESSION['staff_flash'] = ['type' => 'error', 'message' => 'اسم الموظف مستخدم مسبقًا.'];
}

header('Location: staff_dashboard.php#staff-management');
exit;

