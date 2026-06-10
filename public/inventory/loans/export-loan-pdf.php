<?php
/**
 * export-loan-pdf.php - تصدير مستند سلفة المواد إلى PDF باستخدام mPDF
 * يتبع نفس نمط export_ir.php
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

require __DIR__ . '/../../../vendor/autoload.php';

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
$loanType = $loan['type'] === 'borrow' ? 'استلاف مواد' : 'تسليف مواد';
$loanTypeEn = $loan['type'] === 'borrow' ? 'MATERIAL BORROWING' : 'MATERIAL LENDING';
$clientName = $e($loan['client_name']);
$receiverName = $e($loan['receiver_name']);
$receiverId = $e($loan['receiver_identity'] ?? '');
$loanDate = date('Y-m-d', strtotime($loan['created_at']));
$statusText = $loan['status'] === 'active' ? 'نشطة' : 'مخالصة';
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
    $itemsHtml .= "<tr>
        <td style=\"text-align:center;\">{$num}</td>
        <td style=\"text-align:center;font-weight:bold;\">{$itemNumber}</td>
        <td style=\"text-align:right;direction:ltr;\">{$desc}</td>
        <td style=\"text-align:center;\">{$unit}</td>
        <td style=\"text-align:center;font-weight:bold;\">{$qty}</td>
    </tr>";
}
$totalQtyFormatted = number_format($totalQty, 3);

// الشعار من إعدادات المستخلصات (invoice_settings) أو مجلد الشعارات مباشرةً
$logoData = '';
$logoFound = false;
try {
    $db = getDB();
    // أولاً: محاولة جلب من الإعدادات
    $settingsRow = $db->query("SELECT supplier_logo_path FROM invoice_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($settingsRow && !empty($settingsRow['supplier_logo_path'])) {
        $logoPath = realpath(__DIR__ . '/../../../' . $settingsRow['supplier_logo_path']);
        if ($logoPath && file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            $logoFound = true;
        }
    }
    // ثانياً: البحث في مجلد الشعارات مباشرةً (خطة بديلة)
    if (!$logoFound) {
        $logosDir = realpath(__DIR__ . '/../../../uploads/logos');
        if ($logosDir && is_dir($logosDir)) {
            $logos = glob($logosDir . '/logo_*.*');
            if (!empty($logos)) {
                // استخدام أحدث شعار
                usort($logos, function($a, $b) { return filemtime($b) - filemtime($a); });
                $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logos[0]));
            }
        }
    }
} catch (Exception $ex) {
    // تجاهل خطأ الشعار
}
$logoImg = $logoData ? '<img src="' . $logoData . '" width="100" />' : '';

$html = <<<HTML
<style>
    * { margin:0; padding:0; }
    body { font-family: 'dejavusans', sans-serif; font-size: 10pt; direction: rtl; }
    table { border-collapse: collapse; width:100%; }
    td, th { border:0.3pt solid #000; padding:5px 8px; font-size:9.5pt; vertical-align:middle; }
    .nb { border:none !important; }
    .section-title { background:#f0f4ff; font-weight:bold; font-size:10pt; text-align:center; padding:6px; color:#1a395c; }
    .label { font-weight:bold; color:#333; background:#fafbfc; width:25%; }
    .value { color:#000; }
    .items-table th { background:#1a395c; color:#fff; font-weight:bold; text-align:center; padding:7px; font-size:9pt; }
    .items-table td { padding:5px 8px; font-size:9pt; }
    .items-table tr:nth-child(even) td { background:#f8f9fa; }
    .footer-row td { border:none; padding:0; }
    .sig-box { padding:8px; text-align:center; min-height:70px; }
    .sig-label { font-weight:bold; font-size:9pt; color:#333; margin-bottom:30px; display:block; }
    .sig-line { margin-top:35px; padding-top:4px; font-size:8pt; color:#666; }
    .total-row td { background:#e8f0fe; font-weight:bold; font-size:10pt; }
</style>

<!-- الرأس بخلفية ملونة -->
<table style="margin-bottom:10px;">
<tr>
    <td width="20%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px; border-radius:6px 0 0 6px;">{$logoImg}</td>
    <td width="55%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px;">
        <div style="font-size:16pt; font-weight:bold; color:#ffffff; margin-bottom:4px;">مستند سلفة مواد</div>
        <div style="font-size:11pt; font-weight:bold; color:#d0d8e8; letter-spacing:1px;">{$loanTypeEn}</div>
    </td>
    <td width="25%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px; border-radius:0 6px 6px 0;">
        <div style="font-size:8pt; color:#a0b0c8;">رقم المستند</div>
        <div style="font-size:14pt; font-weight:bold; color:#ffffff; margin:3px 0;">{$loanNumber}</div>
        <div style="font-size:8pt; color:#a0b0c8;">{$loanDate}</div>
    </td>
</tr>
</table>

<!-- معلومات السلفة -->
<table>
<tr><td colspan="4" class="section-title">معلومات السلفة</td></tr>
<tr>
    <td class="label">رقم السلفة</td>
    <td class="value">{$loanNumber}</td>
    <td class="label">نوع السلفة</td>
    <td class="value">{$loanType}</td>
</tr>
<tr>
    <td class="label">المقاول / العميل</td>
    <td class="value">{$clientName}</td>
    <td class="label">التاريخ</td>
    <td class="value">{$loanDate}</td>
</tr>
<tr>
    <td class="label">المستلم</td>
    <td class="value">{$receiverName}</td>
    <td class="label">رقم هوية المستلم</td>
    <td class="value">{$receiverId}</td>
</tr>
HTML;

if (!empty($notes)) {
    $html .= "<tr><td class=\"label\">ملاحظات</td><td class=\"value\" colspan=\"3\">{$notes}</td></tr>";
}

$html .= <<<HTML
</table>

<!-- جدول البنود -->
<table style="margin-top:10px;" class="items-table">
<tr><td colspan="5" class="section-title" style="border:0.3pt solid #000;">بنود السلفة ({$totalItems} بند)</td></tr>
<tr>
    <th width="8%">م</th>
    <th width="18%">رقم البند</th>
    <th width="44%">الوصف</th>
    <th width="12%">الوحدة</th>
    <th width="18%">الكمية</th>
</tr>
{$itemsHtml}
<tr class="total-row">
    <td colspan="4" style="text-align:center;">الإجمالي</td>
    <td style="text-align:center;">{$totalQtyFormatted}</td>
</tr>
</table>

<!-- منطقة التواقيع -->
<table style="margin-top:25px;">
<tr class="footer-row">
    <td width="33%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">المُسلِّم (أمين المستودع)</span>
            <div class="sig-line">الاسم: {$createdByName}</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
    <td width="34%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">المُستلِم</span>
            <div class="sig-line">الاسم: {$receiverName}</div>
            <div style="margin-top:4px;font-size:8pt;">الهوية: {$receiverId}</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
    <td width="33%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">المسؤول المعتمد</span>
            <div class="sig-line">الاسم:</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
</tr>
</table>

<!-- تذييل -->
<div style="margin-top:15px;text-align:center;font-size:7.5pt;color:#aaa;border-top:1px solid #eee;padding-top:5px;">
    تم إنشاء هذا المستند آلياً من نظام إدارة المستودعات - Etganplus | التاريخ: {$loanDate}
</div>
HTML;

$fileName = "سلفة_{$loan['loan_number']}.pdf";

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

$mpdf->SetTitle("سلفة مواد - {$loan['loan_number']}");
$mpdf->SetAuthor('Etganplus');
$mpdf->WriteHTML($html);
$mpdf->Output($fileName, \Mpdf\Output\Destination::DOWNLOAD);
