<?php


require_once __DIR__ . '/db_connect.php';

if (!empty($_SESSION['staff_id'])) {
    header('Location: staff_dashboard.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'انتهت الجلسة، حدّث الصفحة وحاول مجددًا.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $conn->prepare('SELECT * FROM staff WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['password'])) {
            session_regenerate_id(true);
            $_SESSION['staff_id'] = (int) $staff['id'];
            $_SESSION['staff_username'] = $staff['username'];
            header('Location: staff_dashboard.php');
            exit;
        }

        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        usleep(350000);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول الموظفين | <?= h(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Style.css">
</head>
<body class="staff-login-page">
    <main class="staff-login-shell">
        <section class="staff-login-card">
            <div class="staff-login-brand">
                <img src="images/1/7.png" alt="<?= h(APP_NAME) ?>">
                <div>
                    <span>منطقة الموظفين</span>
                    <h1>تسجيل الدخول</h1>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" class="stack-form">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <label>
                    اسم المستخدم
                    <input type="text" name="username" autocomplete="username" required autofocus>
                </label>
                <label>
                    كلمة المرور
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="primary-btn" type="submit">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    دخول لوحة التحكم
                </button>
            </form>
        </section>
    </main>
</body>
</html>


