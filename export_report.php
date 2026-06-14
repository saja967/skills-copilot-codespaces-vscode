
<?php

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login_staff.php');
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('امتداد ZIP غير مفعّل في PHP. فعّل extension=zip من php.ini ثم أعد تشغيل  Apache.');
}

$periods = [
    'daily' => ['label' => 'يومي', 'condition' => 'created_at >= CURDATE()'],
    'weekly' => ['label' => 'أسبوعي', 'condition' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'],
    'monthly' => ['label' => 'شهري', 'condition' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)'],
];

$period = isset($periods[$_GET['period'] ?? '']) ? (string) $_GET['period'] : 'daily';
$periodData = $periods[$period];

$orders = $conn->query(
    "SELECT id, customer_name, user_phone, details, subtotal, discount_amount,
            total_price, reward_used, rewards_earned, source, status, created_at
     FROM orders
     WHERE {$periodData['condition']}
     ORDER BY id DESC"
)->fetchAll();

function xml_escape(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excel_column(int $number): string
{
    $column = '';

    while ($number > 0) {
        $number--;
        $column = chr(65 + ($number % 26)) . $column;
        $number = intdiv($number, 26);
    }

    return $column;
}

function text_cell(string $reference, string $value, int $style = 2): string
{
    return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '">'
        . '<is><t>' . xml_escape($value) . '</t></is></c>';
}

function number_cell(string $reference, float|int $value, int $style = 3): string
{
    return '<c r="' . $reference . '" t="n" s="' . $style . '"><v>'
        . xml_escape($value) . '</v></c>';
}

$headers = [
    'رقم الطلب',
    'اسم العميل',
    'رقم الجوال',
    'تفاصيل الطلب',
    'قبل الخصم',
    'قيمة الخصم',
    'المبلغ النهائي',
    'استخدم مكافأة',
    'مكافآت جديدة',
    'المصدر',
    'الحالة',
    'التاريخ',
];

$sheetRows = [];
$headerCells = [];

foreach ($headers as $index => $header) {
    $headerCells[] = text_cell(excel_column($index + 1) . '1', $header, 1);
}

$sheetRows[] = '<row r="1" ht="26" customHeight="1">' . implode('', $headerCells) . '</row>';

foreach ($orders as $rowIndex => $order) {
    $excelRow = $rowIndex + 2;
    $rewardStatus = (int) $order['reward_used'] === 1 ? 'نعم - خصم 5%' : 'لا';
    $source = $order['source'] === 'cashier' ? 'الكاشير' : 'الموقع';
$details = (string) $order['details'];

    $cells = [
        number_cell('A' . $excelRow, (int) $order['id'], 2),
        text_cell('B' . $excelRow, (string) $order['customer_name']),
        text_cell('C' . $excelRow, (string) $order['user_phone']),
        text_cell('D' . $excelRow, $details),
        number_cell('E' . $excelRow, (float) $order['subtotal']),
        number_cell('F' . $excelRow, (float) $order['discount_amount']),
        number_cell('G' . $excelRow, (float) $order['total_price']),
        text_cell('H' . $excelRow, $rewardStatus),
        number_cell('I' . $excelRow, (int) $order['rewards_earned'], 2),
        text_cell('J' . $excelRow, $source),
        text_cell('K' . $excelRow, (string) $order['status']),
        text_cell('L' . $excelRow, (string) $order['created_at']),
    ];
$sheetRows[] = '<row r="' . $excelRow . '" ht="80" customHeight="1">'
        . implode('', $cells)
        . '</row>';
}

$lastRow = max(1, count($orders) + 1);
$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:L' . $lastRow . '"/>'
    . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1">'
    . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
    . '</sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="20"/>'
    . '<cols>'
    . '<col min="1" max="1" width="12" customWidth="1"/>'
    . '<col min="2" max="2" width="22" customWidth="1"/>'
    . '<col min="3" max="3" width="18" customWidth="1"/>'
    .'<col min="4" max="4" width="90" customWidth="1"/>'
    . '<col min="5" max="7" width="16" customWidth="1"/>'
    . '<col min="8" max="9" width="18" customWidth="1"/>'
    . '<col min="10" max="11" width="15" customWidth="1"/>'
    . '<col min="12" max="12" width="22" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
    . '<autoFilter ref="A1:L' . $lastRow . '"/>'
    . '</worksheet>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2">'
    . '<font><sz val="11"/><name val="Arial"/></font>'
    . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font>'
    . '</fonts>'
    . '<fills count="3">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FFFF5A1F"/><bgColor indexed="64"/></patternFill></fill>'
    . '</fills>'
    . '<borders count="2">'
    . '<border><left/><right/><top/><bottom/><diagonal/></border>'
    . '<border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right>'
    . '<top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border>'
    . '</borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="4">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1">'
. '<alignment horizontal="center" vertical="center" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1">'
. '<alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1">'
. '<alignment horizontal="center" vertical="center" readingOrder="2"/></xf>'
        . '</cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';

$files = [
    '[Content_Types].xml' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>',
    '_rels/.rels' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>',
    'xl/workbook.xml' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView rightToLeft="1"/></bookViews>'
        . '<sheets><sheet name="تقرير الطلبات" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>',
    'xl/_rels/workbook.xml.rels' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>',
    'xl/worksheets/sheet1.xml' => $sheetXml,
    'xl/styles.xml' => $stylesXml,
    'docProps/core.xml' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>تقرير طلبات شاورما برايد</dc:title><dc:creator>شاورما برايد</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
        . '</cp:coreProperties>',
    'docProps/app.xml' =>
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Microsoft Excel</Application></Properties>',
];

$temporaryFile = tempnam(sys_get_temp_dir(), 'shawarma-report-');
if ($temporaryFile === false) {
    http_response_code(500);
    exit('تعذر إنشاء ملف التقرير.');
}

$zip = new ZipArchive();
if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($temporaryFile);
    http_response_code(500);
    exit('تعذر إنشاء ملف Excel.');
}

foreach ($files as $path => $content) {
    $zip->addFromString($path, $content);
}

$zip->close();
$filename = 'shawarma-pride-' . $period . '-' . date('Y-m-d') . '.xlsx';

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($temporaryFile));
header('Cache-Control: no-store, no-cache, must-revalidate');

readfile($temporaryFile);
@unlink($temporaryFile);
exit;

