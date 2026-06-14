
<?php


require_once __DIR__ . '/db_connect.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['customer_phone']);
    header('Location: index.php');
    exit;
}

$currentUser = null;
if (!empty($_SESSION['customer_phone'])) {
    $stmt = $conn->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$_SESSION['customer_phone']]);
    $currentUser = $stmt->fetch() ?: null;

    if (!$currentUser) {
        unset($_SESSION['customer_phone']);
    }
}

$appState = [
    'csrf' => csrf_token(),
    'user' => $currentUser ? [
        'name' => $currentUser['name'],
        'phone' => $currentUser['phone'],
        'total_spent' => (float) $currentUser['total_spent'],
        'reward_progress' => (float) $currentUser['reward_progress'],
        'reward_count' => (int) $currentUser['reward_count'],
    ] : null,
    'reward_threshold' => REWARD_THRESHOLD,
    'reward_discount' => REWARD_DISCOUNT_PERCENT,
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#16120f">
    <title><?= h(APP_NAME) ?> | المنيو والطلبات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <div class="splash-screen" id="splash" aria-hidden="true">
        <div class="splash-content">
            <div class="shawarma-spinner">
                <img src="images/1/7.png" alt="">
            </div>
            <h1><?= h(APP_NAME) ?></h1>
            <p>الطلب الذي يستاهل الانتظار</p>
            <div class="heat-line"><span></span></div>
        </div>
    </div>

    <header class="site-header">
        <div class="brand-lockup">
            <img src="images/1/7.png" alt="<?= h(APP_NAME) ?>">
            <div>
                <strong><?= h(APP_NAME) ?></strong>
                <span>طازج، سريع، ومكافآتك محفوظة</span>
            </div>
        </div>
        <nav class="view-switcher" aria-label="التنقل الرئيسي">
            <button class="switch-btn active" id="btn-menu" data-view="menu">المنيو</button>
            <button class="switch-btn" id="btn-offers" data-view="offers">العروض</button>
        </nav>
    </header>

    <section class="loyalty-shell" id="loyaltyShell">
        <?php if ($currentUser): ?>
            <div class="loyalty-card">
                <div class="user-summary">
                    <i class="fa-solid fa-circle-user"></i>
                    <div>
                        <span>يا هلا،</span>
                        <h2 id="loyaltyName"><?= h($currentUser['name']) ?></h2>
                        <small><?= h($currentUser['phone']) ?></small>
                    </div>
                </div>
                <div class="reward-summary">
                    <strong><span id="rewardCount"><?= (int) $currentUser['reward_count'] ?></span> مكافأة</strong>
                    <span>كل مكافأة = خصم 5%</span>
                </div>
                <div class="reward-progress">
                    <div class="progress-copy">
                        <span>تقدم المكافأة القادمة</span>
                        <b><span id="rewardProgress"><?= number_format((float) $currentUser['reward_progress'], 2) ?></span> / 100 ر.س</b>
                    </div>
                    <div class="progress-track"><span id="rewardProgressBar" style="width: <?= min(100, (float) $currentUser['reward_progress']) ?>%"></span></div>
                </div>
               <a class="text-link" href="my_orders.php">
    طلباتي
</a>

<a class="text-link" href="?logout=1">
    تسجيل الخروج
</a>
            </div>
        <?php else: ?>
            <div class="guest-loyalty" id="guestLoyalty">
                <div>
                    <i class="fa-solid fa-gift"></i>
                    <p><strong>كل 100 ر.س تمنحك خصم 5%</strong><br>سجّل اسمك ورقمك، والباقي علينا.</p>
                </div>
                <button type="button" class="secondary-btn" id="openLoginButton">تفعيل المكافآت</button>
            </div>
        <?php endif; ?>
    </section>

    <main>
        <section class="view-section active-view" id="menu-view">
            <div class="categories-nav">
                <button class="nav-item active" data-category="all">الكل</button>
                <button class="nav-item" data-category="sandwiches">ساندوتشات وصاج</button>
                <button class="nav-item" data-category="boxes">بوكسات وعائلية</button>
                <button class="nav-item" data-category="sides">إضافات</button>
            </div>

            <div class="menu-container">
                <?php foreach ($menu_items as $id => $item): ?>
                    <article class="menu-card" data-category="<?= h($item['category']) ?>">
                        <div class="card-img-wrapper">
                            <img src="<?= h($item['img']) ?>" alt="<?= h($item['title']) ?>">
                            <?php if ($item['badge'] !== ''): ?>
                                <span class="badge"><?= h($item['badge']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-details">
                            <h3><?= h($item['title']) ?></h3>
                            <p><?= h($item['desc']) ?></p>
                            <button
                                type="button"
                                class="price-btn"
                                data-add-item
                                data-id="<?= h($id) ?>"
                                data-name="<?= h($item['title']) ?>"
                                data-price="<?= h(number_format((float) $item['price'], 2, '.', '')) ?>"
                            >
                                <span>أضف للسلة</span>
                                <b><?= number_format((float) $item['price'], 0) ?> ر.س</b>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="view-section" id="offers-view">
            <div class="section-heading">
                <span>وقت التوفير</span>
                <h2>عروض برايد</h2>
                <p>نفس الطعم الذي تحبه، بسعر أخف.</p>
            </div>
            <div class="menu-container">
                <?php foreach ($offers as $id => $offer): ?>
                    <article class="menu-card offer-card">
                        <div class="card-img-wrapper">
                            <img src="<?= h($offer['img']) ?>" alt="<?= h($offer['title']) ?>">
                            <span class="badge">عرض</span>
                        </div>
                        <div class="card-details">
                            <h3><?= h($offer['title']) ?></h3>
                            <p><?= h($offer['desc']) ?></p>
                            <div class="offer-prices">
                                <del><?= number_format((float) $offer['old_price'], 0) ?> ر.س</del>
                                <strong><?= number_format((float) $offer['price'], 0) ?> ر.س</strong>
                            </div>
                            <button
                                type="button"
                                class="price-btn"
                                data-add-item
                                data-id="offer-<?= h($id) ?>"
                                data-name="<?= h($offer['title']) ?>"
                                data-price="<?= h(number_format((float) $offer['price'], 2, '.', '')) ?>"
                            >
                                <span>أضف العرض</span>
                                <b><?= number_format((float) $offer['price'], 0) ?> ر.س</b>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <aside class="cart-bar" id="cartBar" aria-live="polite">
        <button type="button" class="cart-summary" id="cartToggle">
            <span><i class="fa-solid fa-basket-shopping"></i> <b id="cartCount">0</b> عناصر</span>
            <span id="cartTotal">0.00 ر.س</span>
            <i class="fa-solid fa-chevron-up" id="cartChevron"></i>
        </button>
        <div class="cart-details" id="cartDetails"></div>
        <label class="reward-option" id="rewardOption">
            <input type="checkbox" id="useReward">
            <span>استخدم مكافأة خصم 5% في هذا الطلب</span>
        </label>
        <div class="checkout-total">
            <span>المبلغ بعد الخصم</span>
            <strong id="checkoutTotal">0.00 ر.س</strong>
        </div>
        <button type="button" class="checkout-btn" id="checkoutButton">
            <i class="fa-brands fa-whatsapp"></i>
            احفظ الطلب وأرسله عبر واتساب
        </button>
    </aside>

    <div class="modal" id="loginModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
            <button type="button" class="modal-close" id="closeLoginButton" aria-label="إغلاق">×</button>
            <div class="modal-icon"><i class="fa-solid fa-gift"></i></div>
            <h2 id="loginTitle">خل مكافأتك محفوظة</h2>
            <p>الاسم ورقم الجوال فقط. إذا كنت مسجلًا من قبل سنعرفك مباشرة.</p>
            <form id="customerForm">
                <label>
                    الاسم
                    <input type="text" name="name" minlength="2" maxlength="100" placeholder="مثال: أحمد" required>
                </label>
                <label>
                    رقم الجوال
                    <input type="tel" name="phone" inputmode="numeric" pattern="05[0-9]{8}" placeholder="05xxxxxxxx" required>
                </label>
                <button type="submit" class="checkout-btn" id="customerSubmit">تفعيل الحساب</button>
            </form>
        </div>
    </div>

    <div class="toast" id="toast" role="status"></div>

    <footer class="bottom-bar">
        <span><i class="fa-solid fa-phone"></i> 054 10 33 555</span>
        <span><i class="fa-solid fa-location-dot"></i> شاورما برايد</span>
    </footer>

    <script>
        window.appState = <?= json_encode($appState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="main.js"></script>
</body>
</html>



