
<?php

const APP_NAME = 'شاورما برايد';
const WHATSAPP_NUMBER = '966592565989';
const REWARD_THRESHOLD = 100.0;
const REWARD_DISCOUNT_PERCENT = 5;

$menu_items = [
    'chicken-lebanese' => [
        'title' => 'لبناني دجاج',
        'desc' => 'الخلطة اللبنانية الأصلية بخبز خفيف تذوب في الفم وتعدل المزاج.',
        'category' => 'sandwiches',
        'img' => 'images/lpnan.png',
        'price' => 7.00,
        'badge' => 'الأكثر مبيعاً',
    ],
    'meat-lebanese' => [
        'title' => 'لبناني لحم',
        'desc' => 'لحم بلدي فاخر متبل على الطريقة اللبنانية مع الطحينة الفخمة.',
        'category' => 'sandwiches',
        'img' => 'images/lpnan.png',
        'price' => 9.00,
        'badge' => '',
    ],
    'chicken-saj' => [
        'title' => 'دريفت صاج دجاج',
        'desc' => 'خبز الصاج الرقيق يحتضن قطع الدجاج المتبلة بخلطتنا السرية.',
        'category' => 'sandwiches',
        'img' => 'images/dreft.png',
        'price' => 9.00,
        'badge' => '',
    ],
    'meat-saj' => [
        'title' => 'دريفت صاج لحم',
        'desc' => 'صاج اللحم مع البصل والمقدونس ودبس الرمان الفاخر.',
        'category' => 'sandwiches',
        'img' => 'images/dreft.png',
        'price' => 12.00,
        'badge' => 'مميز',
    ],
    'chicken-tortilla-box' => [
        'title' => 'شاو تورتيلا بوكس دجاج',
        'desc' => 'مزيج الشاورما وجبن الموزاريلا المخبوزة في بوكس واحد.',
        'category' => 'boxes',
        'img' => 'images/shaw.png',
        'price' => 22.00,
        'badge' => '',
    ],
    'meat-tortilla-box' => [
        'title' => 'شاو تورتيلا بوكس لحم',
        'desc' => 'تورتيلا اللحم الغنية بالجبن الذائب والمحمرة بفرن برايد.',
        'category' => 'boxes',
        'img' => 'images/shaw.png',
        'price' => 24.00,
        'badge' => '',
    ],
    'chicken-fattah' => [
        'title' => 'فتة ملوكية دجاج',
        'desc' => 'خبز مقرمش وأرز تعلوهما قطع الشاورما مع الصوص الخاص.',
        'category' => 'boxes',
        'img' => 'images/ft.png',
        'price' => 21.00,
        'badge' => 'وجبة ملكية',
    ],
    'meat-fattah' => [
        'title' => 'فتة ملوكية لحم',
        'desc' => 'مكس الفتة العربي بشرائح اللحم البلدي الطازج وصلصة برايد.',
        'category' => 'boxes',
        'img' => 'images/ft.png',
        'price' => 24.00,
        'badge' => '',
    ],
    'large-fries' => [
        'title' => 'فرايز حجم كبير',
        'desc' => 'بطاطس مقرمشة ذهبية ومبهرة ببهارات برايد الخاصة.',
        'category' => 'sides',
        'img' => 'images/pte.png',
        'price' => 5.00,
        'badge' => '',
    ],
    'shawarma-fries' => [
        'title' => 'فرايز بالجبنة والشاورما',
        'desc' => 'بطاطس مغطاة بجبن الشيدر الذائب وقطع شاورما الدجاج.',
        'category' => 'sides',
        'img' => 'images/pte.png',
        'price' => 15.00,
        'badge' => 'مطلوب جداً',
    ],
    'garlic-sauce' => [
        'title' => 'ثومية برايد السرية',
        'desc' => 'علبة صوص الثومية الغنية والمحضرة يومياً داخل مطبخنا.',
        'category' => 'sides',
        'img' => 'images/pte.png',
        'price' => 2.00,
        'badge' => '',
    ],
    'spicy-sauce' => [
        'title' => 'صوص سبايسي حار',
        'desc' => 'لمحبي النكهة الحارة والقوية.',
        'category' => 'sides',
        'img' => 'images/pte.png',
        'price' => 2.00,
        'badge' => '',
    ],
];

$offers = [
    'mix-duo' => [
        'title' => 'عرض ميكس برايد الثنائي',
        'desc' => '2 دريفت صاج + بطاطس كبير + 2 صوص ثومية.',
        'img' => 'images/dreft.png',
        'old_price' => 23.00,
        'price' => 19.00,
    ],
    'family-box' => [
        'title' => 'بوكس اللمة الملوكي',
        'desc' => '2 فتة ملوكية + 1 شاو تورتيلا بوكس بسعر العرض.',
        'img' => 'images/ft.png',
        'old_price' => 66.00,
        'price' => 55.00,
    ],
];

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (str_starts_with($digits, '966') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 3);
    }

    return $digits;
}

function valid_saudi_mobile(string $phone): bool
{
    return preg_match('/^05\d{8}$/', $phone) === 1;
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


