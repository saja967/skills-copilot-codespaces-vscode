
<?php

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login_staff.php');
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['staff_id'], $_SESSION['staff_username']);
    session_regenerate_id(true);
    header('Location: login_staff.php');
    exit;
}

$periods = [
    'daily' => ['label' => 'اليوم', 'condition' => 'created_at >= CURDATE()'],
    'weekly' => ['label' => 'آخر 7 أيام', 'condition' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'],
    'monthly' => ['label' => 'آخر 30 يومًا', 'condition' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)'],
];
$period = isset($periods[$_GET['period'] ?? '']) ? (string) $_GET['period'] : 'daily';
$periodCondition = $periods[$period]['condition'];

function set_staff_flash(string $type, string $message): void
{
    $_SESSION['staff_flash'] = ['type' => $type, 'message' => $message];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        set_staff_flash('error', 'انتهت الجلسة، حاول مجددًا.');
        header('Location: staff_dashboard.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_customer') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));

        if (mb_strlen($name) < 2 || !valid_saudi_mobile($phone)) {
            set_staff_flash('error', 'تحقق من اسم العميل ورقم الجوال.');
        } else {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, phone, registered_by) VALUES (?, ?, 'staff')"
                );
                $stmt->execute([$name, $phone]);
                set_staff_flash('success', 'تمت إضافة العميل ويمكنك تسجيل طلبه الآن.');
            } catch (PDOException $e) {
                set_staff_flash('error', 'هذا الرقم مسجل مسبقًا. استخدم البحث للوصول إليه.');
            }
        }

        header('Location: staff_dashboard.php?q=' . rawurlencode($phone));
        exit;
    }

    if ($action === 'manual_order') {
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $subtotal = round((float) ($_POST['subtotal'] ?? 0), 2);
        $details = trim((string) ($_POST['details'] ?? 'طلب من الكاشير'));
        $useReward = !empty($_POST['use_reward']);

        if (!valid_saudi_mobile($phone) || $subtotal <= 0 || $subtotal > 5000) {
            set_staff_flash('error', 'تحقق من رقم العميل وقيمة الطلب.');
            header('Location: staff_dashboard.php?customer=' . rawurlencode($phone));
            exit;
        }

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare('SELECT * FROM users WHERE phone = ? FOR UPDATE');
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new RuntimeException('العميل غير موجود.');
            }

            $rewardUsed = $useReward && (int) $user['reward_count'] > 0 ? 1 : 0;
            $discountPercent = $rewardUsed ? REWARD_DISCOUNT_PERCENT : 0;
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $total = round($subtotal - $discountAmount, 2);
            $progressTotal = round((float) $user['reward_progress'] + $total, 2);
            $earned = (int) floor($progressTotal / REWARD_THRESHOLD);
            $progress = round(fmod($progressTotal, REWARD_THRESHOLD), 2);
            $rewardCount = max(0, (int) $user['reward_count'] - $rewardUsed + $earned);

            $insert = $conn->prepare(
                "INSERT INTO orders (
                    order_token, user_phone, customer_name, details, subtotal,
                    discount_percent, discount_amount, total_price, reward_used,
                    rewards_earned, source, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cashier', 'completed')"
            );
            $insert->execute([
                'cashier-' . bin2hex(random_bytes(16)),
                $phone,
                $user['name'],
                $details !== '' ? $details : 'طلب من الكاشير',
                $subtotal,
                $discountPercent,
                $discountAmount,
                $total,
                $rewardUsed,
                $earned,
            ]);

            $update = $conn->prepare(
                'UPDATE users
                 SET total_spent = total_spent + ?, reward_progress = ?, reward_count = ?,
                     points = ?, last_order_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([$total, $progress, $rewardCount, $rewardCount, $user['id']]);
            $conn->commit();
            set_staff_flash('success', 'تم تسجيل الطلب وتحديث مكافآت العميل.');
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            set_staff_flash('error', 'تعذر تسجيل الطلب.');
        }

        header('Location: staff_dashboard.php?customer=' . rawurlencode($phone));
        exit;
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $conn->prepare('SELECT password FROM staff WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['staff_id']]);
        $currentHash = (string) $stmt->fetchColumn();

        if (!password_verify($currentPassword, $currentHash)) {
            set_staff_flash('error', 'كلمة المرور الحالية غير صحيحة.');
        } elseif (strlen($newPassword) < 8) {
            set_staff_flash('error', 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.');
        } elseif ($newPassword !== $confirmPassword) {
            set_staff_flash('error', 'تأكيد كلمة المرور غير مطابق.');
        } else {
            $stmt = $conn->prepare('UPDATE staff SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $_SESSION['staff_id']]);
            set_staff_flash('success', 'تم تغيير كلمة المرور بنجاح.');
        }

        header('Location: staff_dashboard.php#staff-management');
        exit;
    }
}

$flash = $_SESSION['staff_flash'] ?? null;
unset($_SESSION['staff_flash']);

$statsStmt = $conn->query(
    "SELECT
        COALESCE(SUM(total_price), 0) AS revenue,
        COUNT(*) AS orders_count,
        COUNT(DISTINCT user_phone) AS customers_count
     FROM orders
     WHERE {$periodCondition}"
);
$stats = $statsStmt->fetch();

$newCustomers = (int) $conn->query(
    "SELECT COUNT(*) FROM users WHERE {$periodCondition}"
)->fetchColumn();

$query = trim((string) ($_GET['q'] ?? ''));
$customersSql = 'SELECT u.*,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_phone = u.phone) AS orders_count
                 FROM users u';
$customerParams = [];
if ($query !== '') {
    $customersSql .= ' WHERE u.phone LIKE ? OR u.name LIKE ?';
    $customerParams = ['%' . $query . '%', '%' . $query . '%'];
}
$customersSql .= ' ORDER BY u.last_order_at DESC, u.created_at DESC LIMIT 100';
$customersStmt = $conn->prepare($customersSql);
$customersStmt->execute($customerParams);
$customers = $customersStmt->fetchAll();

$orders = $conn->query(
    "SELECT * FROM orders WHERE {$periodCondition} ORDER BY id DESC LIMIT 100"
)->fetchAll();

$selectedCustomer = null;
$customerOrders = [];
$earnedRewardsTotal = 0;
$usedRewardsTotal = 0;
$lastUsedRewardOrder = null;
$selectedPhone = normalize_phone((string) ($_GET['customer'] ?? ''));
if ($selectedPhone !== '') {
    $stmt = $conn->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$selectedPhone]);
    $selectedCustomer = $stmt->fetch() ?: null;

    if ($selectedCustomer) {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE user_phone = ? ORDER BY id DESC');
        $stmt->execute([$selectedPhone]);
        $customerOrders = $stmt->fetchAll();

        foreach ($customerOrders as $customerOrder) {
            $earnedRewardsTotal += (int) $customerOrder['rewards_earned'];
            $usedRewardsTotal += (int) $customerOrder['reward_used'];

            if ($lastUsedRewardOrder === null && (int) $customerOrder['reward_used'] === 1) {
                $lastUsedRewardOrder = (int) $customerOrder['id'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الموظفين | <?= h(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Style.css">
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <div>
            <span>مرحبًا، <?= h($_SESSION['staff_username'] ?? 'موظف') ?></span>
            <h1>لوحة شاورما برايد</h1>
        </div>
        <a href="?logout=1" class="danger-link"><i class="fa-solid fa-right-from-bracket"></i> خروج</a>
    </header>

    <main class="dashboard-container">
        <?php if (is_array($flash)): ?>
            <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <section class="period-toolbar">
            <div class="period-tabs">
                <?php foreach ($periods as $key => $periodData): ?>
                    <a class="<?= $period === $key ? 'active' : '' ?>" href="?period=<?= h($key) ?>">
                        <?= h($periodData['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a class="export-btn" href="export_report.php?period=<?= h($period) ?>">
                <i class="fa-solid fa-file-arrow-down"></i>
                تنزيل Excel
            </a>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <i class="fa-solid fa-sack-dollar"></i>
                <span>مدخول <?= h($periods[$period]['label']) ?></span>
                <strong><?= number_format((float) $stats['revenue'], 2) ?> ر.س</strong>
            </article>
            <article class="stat-card">
                <i class="fa-solid fa-receipt"></i>
                <span>عدد الطلبات</span>
                <strong><?= (int) $stats['orders_count'] ?></strong>
            </article>
            <article class="stat-card">
                <i class="fa-solid fa-users"></i>
                <span>عملاء الفترة</span>
                <strong><?= (int) $stats['customers_count'] ?></strong>
            </article>
            <article class="stat-card">
                <i class="fa-solid fa-user-plus"></i>
                <span>عملاء جدد</span>
                <strong><?= $newCustomers ?></strong>
            </article>
        </section>

        <section class="dashboard-grid">
            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <span>بحث موحد</span>
                        <h2>العملاء</h2>
                    </div>
                </div>
                <form method="get" class="search-form">
                    <input type="hidden" name="period" value="<?= h($period) ?>">
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="ابحث بالاسم أو رقم الجوال">
                    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> بحث</button>
                </form>

                <div class="table-wrap compact-table">
                    <table>
                        <thead>
                            <tr>
                                <th>العميل</th>
                                <th>التسجيل</th>
                                <th>الطلبات</th>
                                <th>المكافآت</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customers === []): ?>
                                <tr><td colspan="5" class="empty-cell">لا توجد نتيجة مطابقة.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($customer['name']) ?></strong>
                                        <small><?= h($customer['phone']) ?></small>
                                    </td>
                                    <td><?= $customer['registered_by'] === 'staff' ? 'الكاشير' : 'العميل' ?></td>
                                    <td><?= (int) $customer['orders_count'] ?></td>
                                    <td><?= (int) $customer['reward_count'] ?> × 5%</td>
                                    <td><a class="row-link" href="?customer=<?= h($customer['phone']) ?>&period=<?= h($period) ?>">عرض</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="panel">
                <div class="panel-heading">
                    <div>
                        <span>من الكاشير</span>
                        <h2>إضافة عميل</h2>
                    </div>
                </div>
                <form method="post" class="stack-form">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_customer">
                    <label>اسم العميل<input type="text" name="name" minlength="2" required></label>
                    <label>رقم الجوال<input type="tel" name="phone" pattern="05[0-9]{8}" placeholder="05xxxxxxxx" required></label>
                    <button type="submit" class="primary-btn"><i class="fa-solid fa-user-plus"></i> حفظ العميل</button>
                </form>

                <details class="staff-management" id="staff-management">
                    <summary>إضافة موظف جديد</summary>
                    <form method="post" action="add_saja.php" class="stack-form">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <label>اسم المستخدم<input type="text" name="username" minlength="2" required></label>
                        <label>كلمة المرور<input type="password" name="password" minlength="6" required></label>
                        <button type="submit" class="secondary-btn">إضافة الموظف</button>
                    </form>
                </details>

                <details class="staff-management">
                    <summary>تغيير كلمة مروري</summary>
                    <form method="post" class="stack-form">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <label>كلمة المرور الحالية<input type="password" name="current_password" required></label>
                        <label>كلمة المرور الجديدة<input type="password" name="new_password" minlength="8" required></label>
                        <label>تأكيد كلمة المرور<input type="password" name="confirm_password" minlength="8" required></label>
                        <button type="submit" class="secondary-btn">تحديث كلمة المرور</button>
                    </form>
                </details>
            </aside>
        </section>

        <?php if ($selectedCustomer): ?>
            <section class="panel customer-detail">
                <div class="panel-heading">
                    <div>
                        <span>ملف العميل</span>
                        <h2><?= h($selectedCustomer['name']) ?></h2>
                        <p><?= h($selectedCustomer['phone']) ?></p>
                    </div>
                    <div class="customer-badges">
                        <span>إجمالي الدفع: <?= number_format((float) $selectedCustomer['total_spent'], 2) ?> ر.س</span>
                        <span>الرصيد الحالي: <?= (int) $selectedCustomer['reward_count'] ?> خصم</span>
                        <span>كسب: <?= $earnedRewardsTotal ?> | استخدم: <?= $usedRewardsTotal ?></span>
                    </div>
                </div>

                <?php
                $availableRewards = (int) $selectedCustomer['reward_count'];
                $rewardProgress = (float) $selectedCustomer['reward_progress'];
                $remainingForReward = max(0, REWARD_THRESHOLD - $rewardProgress);
                ?>
                <div class="customer-reward-status <?= $availableRewards > 0 ? 'ready' : 'waiting' ?>">
                    <i class="fa-solid <?= $availableRewards > 0 ? 'fa-gift' : 'fa-clock' ?>"></i>
                    <div>
                        <?php if ($availableRewards > 0): ?>
                            <strong>لدى العميل <?= $availableRewards ?> مكافأة جاهزة: خصم 5%</strong>
                            <span>فعّل خيار «استخدام خصم 5%» قبل تسجيل الطلب.</span>
                        <?php elseif ($lastUsedRewardOrder !== null): ?>
                            <strong>لا توجد مكافأة جاهزة الآن</strong>
                            <span>
                                تم استخدام آخر خصم 5% في الطلب #<?= $lastUsedRewardOrder ?>.
                                باقي <?= number_format($remainingForReward, 2) ?> ر.س للمكافأة القادمة.
                            </span>
                        <?php else: ?>
                            <strong>العميل لم يحصل على مكافأة بعد</strong>
                            <span>باقي <?= number_format($remainingForReward, 2) ?> ر.س للحصول على خصم 5%.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" class="manual-order-form">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="manual_order">
                    <input type="hidden" name="phone" value="<?= h($selectedCustomer['phone']) ?>">
                    <label>قيمة الطلب قبل الخصم<input type="number" name="subtotal" min="0.01" max="5000" step="0.01" required></label>
                    <label>تفاصيل الطلب<input type="text" name="details" placeholder="مثال: 2 صاج دجاج + بطاطس"></label>
                    <?php if ((int) $selectedCustomer['reward_count'] > 0): ?>
                        <label class="inline-check"><input type="checkbox" name="use_reward" value="1"> استخدام خصم 5%</label>
                    <?php endif; ?>
                    <button type="submit" class="primary-btn">تسجيل الطلب وتحديث المكافأة</button>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                           <th>الحالة</th>
<th>تحديث الحالة</th>
<th>الوقت</th>
                        </thead>
                        <tbody>
                            <?php if ($customerOrders === []): ?>
                                <tr><td colspan="8" class="empty-cell">لا توجد طلبات لهذا العميل بعد.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($customerOrders as $order): ?>
                                <tr>
                                    <td>#<?= (int) $order['id'] ?></td>
                                    <td class="details-cell"><?= nl2br(h($order['details'])) ?></td>
                                    <td><?= number_format((float) $order['subtotal'], 2) ?></td>
                                    <td><?= number_format((float) $order['discount_amount'], 2) ?></td>
                                    <td><strong><?= number_format((float) $order['total_price'], 2) ?> ر.س</strong></td>
                                    <td>
                                        <?php if ((int) $order['reward_used'] === 1): ?>
                                            <span class="reward-movement used">استخدم خصم 5%</span>
                                        <?php endif; ?>
                                        <?php if ((int) $order['rewards_earned'] > 0): ?>
                                            <span class="reward-movement earned">+<?= (int) $order['rewards_earned'] ?> مكافأة</span>
                                        <?php endif; ?>
                                        <?php if ((int) $order['reward_used'] === 0 && (int) $order['rewards_earned'] === 0): ?>
                                            <span class="reward-movement none">لا توجد حركة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $order['source'] === 'cashier' ? 'الكاشير' : 'الموقع' ?></td>
                                    <td><?= h($order['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <span><?= h($periods[$period]['label']) ?></span>
                    <h2>الطلبات</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>الطلب</th>
                            <th>العميل</th>
                            <th>الجوال</th>
                            <th>التفاصيل</th>
                            <th>الإجمالي</th>
                            <th>المكافأة</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders === []): ?>
                            <tr><td colspan="7" class="empty-cell">لا توجد طلبات في هذه الفترة.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['id'] ?></td>
                                <td><a class="row-link" href="?customer=<?= h($order['user_phone']) ?>&period=<?= h($period) ?>"><?= h($order['customer_name']) ?></a></td>
                                <td><?= h($order['user_phone']) ?></td>
                                <td class="details-cell"><?= nl2br(h($order['details'])) ?></td>
                                <td><strong><?= number_format((float) $order['total_price'], 2) ?> ر.س</strong></td>
                                <td>
                                    <?php if ((int) $order['reward_used'] === 1): ?>
                                        <span class="reward-movement used">استخدم 5%</span>
                                    <?php endif; ?>
                                    <?php if ((int) $order['rewards_earned'] > 0): ?>
                                        <span class="reward-movement earned">+<?= (int) $order['rewards_earned'] ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) $order['reward_used'] === 0 && (int) $order['rewards_earned'] === 0): ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
<?php
$statusText = [
    'new' => 'جديد',
    'accepted' => 'تم القبول',
    'preparing' => 'جاري التجهيز',
    'ready' => 'جاهز للاستلام',
    'completed' => 'تم الاستلام'
];

echo $statusText[$order['status']] ?? $order['status'];
?>
</td>

<td>
<form method="post" action="update_order_status.php">

    <input type="hidden"
           name="order_id"
           value="<?= (int)$order['id'] ?>">

    <select name="status">

        <option value="accepted">تم القبول</option>

        <option value="preparing">جاري التجهيز</option>

        <option value="ready">جاهز للاستلام</option>

    </select>

    <button type="submit">
        تحديث
    </button>

</form>
</td>
                                <td><?= h($order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

