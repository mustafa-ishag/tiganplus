<?php
/**
 * export-loan-settlement-pdf.php - مستند مخالصة سلفة (إثبات إرجاع المواد المستلفة)
 * يُستخدم عند مخالصة سلفة من نوع "استلاف" لإثبات إرجاع المواد للجهة المُسلِّفة
 */
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryLoan.php';

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    die("خطأ: $errstr");
});
set_exception_handler(function ($e) {
    die('خطأ: ' . $e->getMessage());
});

require __DIR__ . '/../../../../scrap/vendor/autoload.php';

$loanId = (int)($_GET['id'] ?? 0);
if ($loanId <= 0) {
    die('معرف السلفة غير صحيح');
}

$loanModel = new InventoryLoan();
$loan = $loanModel->getLoanDetails($loanId);

if (!$loan) {
    die('السلفة غير موجودة');
}

$e = function ($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

$loanNumber = $e($loan['loan_number']);
$clientName = $e($loan['client_name']);
$receiverName = $e($loan['receiver_name']);
$receiverId = $e($loan['receiver_identity'] ?? '');
$loanDate = date('Y-m-d', strtotime($loan['created_at']));
$settledDate = $loan['settled_at'] ? date('Y-m-d', strtotime($loan['settled_at'])) : date('Y-m-d');
$notes = $e($loan['notes'] ?? '');
$createdByName = $e($loan['created_by_name'] ?? '');
$totalItems = count($loan['items']);

// بناء صفوف جدول البنود
$itemsHtml = '';
$totalQty = 0;
foreach ($loan['items'] as $i => $item) {
    $num = $i + 1;
    $itemNumber = $e($item['item_number']);
    $desc = $e($item['description']);
    $unit = $e($item['unit'] ?? '');
    $qty = number_format($item['quantity'], 3);
    $totalQty += $item['quantity'];
    $bgColor = ($num % 2 === 0) ? '#f8f9fa' : '#ffffff';
    $itemsHtml .= "<tr>
        <td style=\"text-align:center;background:{$bgColor};\">{$num}</td>
        <td style=\"text-align:center;font-weight:bold;background:{$bgColor};\">{$itemNumber}</td>
        <td style=\"text-align:right;direction:ltr;background:{$bgColor};\">{$desc}</td>
        <td style=\"text-align:center;background:{$bgColor};\">{$unit}</td>
        <td style=\"text-align:center;font-weight:bold;background:{$bgColor};\">{$qty}</td>
    </tr>";
}
$totalQtyFormatted = number_format($totalQty, 3);

// الشعار
$logoData = '';
$logoFound = false;
try {
    $db = getDB();
    $settingsRow = $db->query("SELECT supplier_logo_path FROM invoice_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($settingsRow && !empty($settingsRow['supplier_logo_path'])) {
        $logoPath = realpath(__DIR__ . '/../../../' . $settingsRow['supplier_logo_path']);
        if ($logoPath && file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            $logoFound = true;
        }
    }
    if (!$logoFound) {
        $logosDir = realpath(__DIR__ . '/../../../uploads/logos');
        if ($logosDir && is_dir($logosDir)) {
            $logos = glob($logosDir . '/logo_*.*');
            if (!empty($logos)) {
                usort($logos, function($a, $b) { return filemtime($b) - filemtime($a); });
                $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logos[0]));
            }
        }
    }
} catch (Exception $ex) {}
$logoImg = $logoData ? '<img src="' . $logoData . '" width="90" />' : '';

$html = <<<HTML
<style>
    * { margin:0; padding:0; }
    body { font-family: 'dejavusans', sans-serif; font-size: 10pt; direction: rtl; }
    table { border-collapse: collapse; width:100%; }
    td, th { border:0.3pt solid #000; padding:5px 8px; font-size:9.5pt; vertical-align:middle; }
    .nb { border:none !important; }
    .section-title { background:#e8f5e9; font-weight:bold; font-size:10pt; text-align:center; padding:6px; color:#2e7d32; border:0.3pt solid #000; }
    .label { font-weight:bold; color:#333; background:#fafbfc; width:25%; }
    .value { color:#000; }
    .items-table th { background:#2e7d32; color:#fff; font-weight:bold; text-align:center; padding:7px; font-size:9pt; }
    .items-table td { padding:5px 8px; font-size:9pt; }
    .footer-row td { border:none; padding:0; }
    .sig-box { padding:10px; text-align:center; min-height:80px; }
    .sig-label { font-weight:bold; font-size:9.5pt; color:#1a395c; display:block; margin-bottom:5px; }
    .sig-sub { font-size:8pt; margin-top:6px; }
    .total-row td { background:#e8f5e9; font-weight:bold; font-size:10pt; }
    .stamp-area { min-height:60px; margin-top:8px; text-align:center; padding:15px 5px; color:#aaa; font-size:8pt; }
</style>

<!-- الرأس -->
<table style="margin-bottom:10px;">
<tr>
    <td width="18%" class="nb" style="background:#2e7d32; text-align:center; vertical-align:middle; padding:14px 8px;">{$logoImg}</td>
    <td width="54%" class="nb" style="background:#2e7d32; text-align:center; vertical-align:middle; padding:14px 8px;">
        <div style="font-size:17pt; font-weight:bold; color:#ffffff; margin-bottom:3px;">مستند مخالصة سلفة</div>
        <div style="font-size:11pt; font-weight:bold; color:#c8e6c9; letter-spacing:1px;">LOAN SETTLEMENT DOCUMENT</div>
    </td>
    <td width="28%" class="nb" style="background:#2e7d32; text-align:center; vertical-align:middle; padding:14px 8px;">
        <div style="font-size:8pt; color:#a5d6a7;">رقم المستند</div>
        <div style="font-size:14pt; font-weight:bold; color:#ffffff; margin:3px 0;">{$loanNumber}</div>
        <div style="font-size:8pt; color:#a5d6a7;">تاريخ المخالصة: {$settledDate}</div>
    </td>
</tr>
</table>

<!-- إشعار المخالصة -->
<table style="margin-bottom:10px;">
<tr>
    <td class="nb" style="background:#fff3e0; border:1.5px solid #f57c00; padding:10px; text-align:center;">
        <div style="font-size:11pt; font-weight:bold; color:#e65100;">
            إقرار بإرجاع المواد المستلفة وإتمام المخالصة
        </div>
        <div style="font-size:9pt; color:#bf360c; margin-top:4px;">
            يُقر الطرفان أدناه بأن جميع المواد المدرجة في هذا المستند قد تم إرجاعها بالكامل وبحالة سليمة
        </div>
    </td>
</tr>
</table>

<!-- معلومات السلفة -->
<table>
<tr><td colspan="4" class="section-title">معلومات السلفة الأصلية</td></tr>
<tr>
    <td class="label">رقم السلفة</td>
    <td class="value">{$loanNumber}</td>
    <td class="label">نوع السلفة</td>
    <td class="value">استلاف مواد</td>
</tr>
<tr>
    <td class="label">الجهة المُسلِّفة</td>
    <td class="value" style="font-weight:bold;color:#1a395c;">{$clientName}</td>
    <td class="label">تاريخ الاستلاف</td>
    <td class="value">{$loanDate}</td>
</tr>
<tr>
    <td class="label">المستلم (عند الاستلاف)</td>
    <td class="value">{$receiverName}</td>
    <td class="label">تاريخ المخالصة</td>
    <td class="value" style="font-weight:bold;color:#2e7d32;">{$settledDate}</td>
</tr>
HTML;

if (!empty($notes)) {
    $html .= "<tr><td class=\"label\">ملاحظات</td><td class=\"value\" colspan=\"3\">{$notes}</td></tr>";
}

$html .= <<<HTML
</table>

<!-- جدول المواد المُرجعة -->
<table style="margin-top:10px;" class="items-table">
<tr><td colspan="5" class="section-title">المواد المُرجعة ({$totalItems} بند)</td></tr>
<tr>
    <th width="8%">م</th>
    <th width="18%">رقم البند</th>
    <th width="44%">الوصف</th>
    <th width="12%">الوحدة</th>
    <th width="18%">الكمية المُرجعة</th>
</tr>
{$itemsHtml}
<tr class="total-row">
    <td colspan="4" style="text-align:center;">إجمالي الكميات المُرجعة</td>
    <td style="text-align:center;">{$totalQtyFormatted}</td>
</tr>
</table>

<!-- منطقة التواقيع -->
<table style="margin-top:20px;">
<tr class="footer-row">
    <td width="50%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">الطرف الأول - المُستلِف (شركتنا)</span>
            <div style="font-size:9pt; margin-top:8px;">أقر بإرجاع جميع المواد المدرجة أعلاه</div>
            <div style="margin-top:35px; font-size:8.5pt;">
                الاسم: {$createdByName}
            </div>
            <div style="margin-top:18px; font-size:8pt;">التوقيع:</div>
            <div style="margin-top:18px; font-size:8pt;">التاريخ: {$settledDate}</div>
        </div>
    </td>
    <td width="50%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label" style="color:#2e7d32;">الطرف الثاني - الجهة المُسلِّفة ({$clientName})</span>
            <div style="font-size:9pt; margin-top:8px;">أقر باستلام جميع المواد المدرجة أعلاه بحالة سليمة</div>
            <div style="margin-top:35px; font-size:8.5pt;">
                الاسم:
            </div>
            <div style="margin-top:18px; font-size:8pt;">التوقيع:</div>
            <div style="margin-top:18px; font-size:8pt;">التاريخ:</div>
            <div class="stamp-area" style="margin-top:15px;">مكان الختم</div>
        </div>
    </td>
</tr>
</table>

<!-- اعتماد المسؤول -->
<table style="margin-top:12px;">
<tr class="footer-row">
    <td style="padding:5px;">
        <div class="sig-box" style="min-height:50px;">
            <span class="sig-label">اعتماد المسؤول</span>
            <table style="width:100%;border:none;">
            <tr>
                <td class="nb" style="width:33%;text-align:center;">
                    <div style="font-size:8.5pt;">الاسم:</div>
                </td>
                <td class="nb" style="width:33%;text-align:center;">
                    <div style="font-size:8.5pt;">التوقيع:</div>
                </td>
                <td class="nb" style="width:33%;text-align:center;">
                    <div style="font-size:8.5pt;">التاريخ:</div>
                </td>
            </tr>
            </table>
        </div>
    </td>
</tr>
</table>

<!-- تذييل -->
<div style="margin-top:12px;text-align:center;font-size:7.5pt;color:#aaa;border-top:1px solid #eee;padding-top:5px;">
    مستند مخالصة سلفة - تم إنشاؤه آلياً من نظام إدارة المستودعات Etganplus | التاريخ: {$settledDate}
</div>
HTML;

$fileName = "مخالصة_سلفة_{$loan['loan_number']}.pdf";

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'default_font_size' => 10,
    'default_font' => 'dejavusans',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);

$mpdf->SetTitle("مخالصة سلفة - {$loan['loan_number']}");
$mpdf->SetAuthor('Etganplus');
$mpdf->WriteHTML($html);
$mpdf->Output($fileName, \Mpdf\Output\Destination::DOWNLOAD);
