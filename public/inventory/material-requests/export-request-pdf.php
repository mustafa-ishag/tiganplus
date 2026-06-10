<?php
/**
 * export-request-pdf.php - تصدير مستند طلب صرف المواد إلى PDF باستخدام mPDF
 * يتبع نفس نمط export-loan-pdf.php
 */
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    die("خطأ: $errstr");
});
set_exception_handler(function ($e) {
    die('خطأ: ' . $e->getMessage());
});

require __DIR__ . '/../../../vendor/autoload.php';

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    die('معرف الطلب غير صحيح');
}

$materialRequestModel = new MaterialRequest();

$request = $materialRequestModel->fetchOne(
    "SELECT mr.*,
            wo.work_order_number,
            wot.type_code as work_order_type_code,
            wot.description as work_order_type_description,
            b.name as branch_name,
            u1.full_name as requested_by_name
     FROM material_requests mr
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
     LEFT JOIN branches b ON wo.branch_id = b.id
     LEFT JOIN users u1 ON mr.requested_by = u1.id
     WHERE mr.id = ?",
    [$requestId]
);

if (!$request) {
    die('الطلب غير موجود');
}

$requestDetails = $materialRequestModel->fetchAll(
    "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
     FROM material_request_details mrd
     JOIN materials m ON mrd.material_id = m.id
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE mrd.request_id = ?
     ORDER BY mc.description",
    [$requestId]
);

$e = function ($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

$requestNumber = $e($request['request_number']);
$requestDate   = isset($request['request_date']) ? date('Y-m-d', strtotime($request['request_date'])) : date('Y-m-d');
$requiredDate  = !empty($request['required_date']) ? date('Y-m-d', strtotime($request['required_date'])) : '-';
$workOrderNum  = $e($request['work_order_number'] ?? '-');
$typeCode      = $e($request['work_order_type_code'] ?? '');
$typeDesc      = $e($request['work_order_type_description'] ?? '');
$workOrderInfo = trim("$typeCode - $typeDesc", ' -');
$requesterName = $e($request['requested_by_name'] ?? '-');
$branchName    = $e($request['branch_name'] ?? '-');
$notes         = $e($request['notes'] ?? '');
$totalItems    = count($requestDetails);

$statusLabels = [
    'draft'              => 'مسودة',
    'submitted'          => 'مرسل للمراجعة',
    'revision_requested' => 'طلب تعديل',
    'warehouse_approved' => 'معتمد من المستودع',
    'approved'           => 'معتمد نهائياً',
    'rejected'           => 'مرفوض',
    'cancelled'          => 'ملغى',
];
$statusText = $statusLabels[$request['status'] ?? ''] ?? ($request['status'] ?? '');

$statusColors = [
    'draft'              => '#95a5a6',
    'submitted'          => '#3498db',
    'revision_requested' => '#e67e22',
    'warehouse_approved' => '#f39c12',
    'approved'           => '#27ae60',
    'rejected'           => '#e74c3c',
    'cancelled'          => '#7f8c8d',
];
$statusColor = $statusColors[$request['status'] ?? ''] ?? '#95a5a6';

// بناء صفوف جدول البنود
$itemsHtml = '';
$totalQty = 0;
foreach ($requestDetails as $i => $item) {
    $num = $i + 1;
    $itemNumber = $e($item['item_number']);
    $desc = $e($item['description']);
    $unit = $e($item['unit'] ?? '');
    $qty = number_format((float)($item['requested_quantity'] ?? 0), 3);
    $stock = number_format((float)($item['current_stock'] ?? 0), 3);
    $totalQty += (float)($item['requested_quantity'] ?? 0);

    $isLow = (float)($item['current_stock'] ?? 0) < (float)($item['requested_quantity'] ?? 0);
    $stockColor = $isLow ? 'color:#c0392b;font-weight:bold;' : 'color:#27ae60;font-weight:bold;';
    $stockIcon = $isLow ? '⚠' : '✓';

    $rowBg = ($i % 2 === 0) ? '' : 'background:#f8f9fa;';

    $itemsHtml .= "<tr style=\"{$rowBg}\">
        <td style=\"text-align:center;\">{$num}</td>
        <td style=\"text-align:center;font-weight:bold;\">{$itemNumber}</td>
        <td style=\"text-align:right;direction:ltr;\">{$desc}</td>
        <td style=\"text-align:center;\">{$unit}</td>
        <td style=\"text-align:center;font-weight:bold;color:#2353a4;\">{$qty}</td>
        <td style=\"text-align:center;{$stockColor}\">{$stockIcon} {$stock}</td>
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
    .status-badge { display:inline-block; padding:3px 12px; border-radius:10px; color:#fff; font-weight:bold; font-size:9pt; }

</style>

<!-- الرأس بخلفية ملونة -->
<table style="margin-bottom:10px;">
<tr>
    <td width="20%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px; border-radius:6px 0 0 6px;">{$logoImg}</td>
    <td width="55%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px;">
        <div style="font-size:16pt; font-weight:bold; color:#ffffff; margin-bottom:4px;">طلب صرف مواد</div>
        <div style="font-size:11pt; font-weight:bold; color:#d0d8e8; letter-spacing:1px;">MATERIAL REQUEST</div>
    </td>
    <td width="25%" class="nb" style="background:#1a395c; text-align:center; vertical-align:middle; padding:12px 8px; border-radius:0 6px 6px 0;">
        <div style="font-size:8pt; color:#a0b0c8;">رقم الطلب</div>
        <div style="font-size:14pt; font-weight:bold; color:#ffffff; margin:3px 0;">{$requestNumber}</div>
        <div style="font-size:8pt; color:#a0b0c8;">{$requestDate}</div>
    </td>
</tr>
</table>

<!-- معلومات الطلب -->
<table>
<tr><td colspan="4" class="section-title">معلومات طلب الصرف</td></tr>
<tr>
    <td class="label">رقم الطلب</td>
    <td class="value">{$requestNumber}</td>
    <td class="label">الحالة</td>
    <td class="value"><span class="status-badge" style="background:{$statusColor};">{$statusText}</span></td>
</tr>
<tr>
    <td class="label">أمر العمل</td>
    <td class="value">{$workOrderNum}</td>
    <td class="label">نوع أمر العمل</td>
    <td class="value">{$workOrderInfo}</td>
</tr>
<tr>
    <td class="label">مقدم الطلب</td>
    <td class="value">{$requesterName}</td>
    <td class="label">الفرع</td>
    <td class="value">{$branchName}</td>
</tr>
<tr>
    <td class="label">تاريخ الطلب</td>
    <td class="value">{$requestDate}</td>
    <td class="label">تاريخ الحاجة</td>
    <td class="value">{$requiredDate}</td>
</tr>
HTML;

if (!empty(trim($notes))) {
    $html .= "<tr><td class=\"label\">ملاحظات</td><td class=\"value\" colspan=\"3\">{$notes}</td></tr>";
}

$html .= <<<HTML
</table>

<!-- جدول المواد -->
<table style="margin-top:10px;" class="items-table">
<tr><td colspan="6" class="section-title" style="border:0.3pt solid #000;">المواد المطلوبة ({$totalItems} بند)</td></tr>
<tr>
    <th width="7%">م</th>
    <th width="15%">رقم البند</th>
    <th width="38%">الوصف</th>
    <th width="10%">الوحدة</th>
    <th width="15%">الكمية المطلوبة</th>
    <th width="15%">المخزون الحالي</th>
</tr>
{$itemsHtml}
<tr class="total-row">
    <td colspan="4" style="text-align:center;">الإجمالي</td>
    <td style="text-align:center;color:#2353a4;">{$totalQtyFormatted}</td>
    <td style="text-align:center;">-</td>
</tr>
</table>
HTML;

$html .= <<<HTML

<!-- منطقة التواقيع -->
<table style="margin-top:25px;">
<tr class="footer-row">
    <td width="33%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">مقدم الطلب</span>
            <div class="sig-line">الاسم: {$requesterName}</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
    <td width="34%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">اعتماد المستودع</span>
            <div class="sig-line">الاسم:</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
    <td width="33%" style="padding:5px;">
        <div class="sig-box">
            <span class="sig-label">الاعتماد النهائي</span>
            <div class="sig-line">الاسم:</div>
            <div style="margin-top:8px;font-size:8pt;">التوقيع:</div>
        </div>
    </td>
</tr>
</table>

<!-- تذييل -->
<div style="margin-top:15px;text-align:center;font-size:7.5pt;color:#aaa;border-top:1px solid #eee;padding-top:5px;">
    تم إنشاء هذا المستند آلياً من نظام إدارة المستودعات - Etganplus | التاريخ: {$requestDate}
</div>
HTML;

$fileName = "طلب_صرف_{$request['request_number']}.pdf";

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

$mpdf->SetTitle("طلب صرف مواد - {$request['request_number']}");
$mpdf->SetAuthor('Etganplus');
$mpdf->WriteHTML($html);
$mpdf->Output($fileName, \Mpdf\Output\Destination::DOWNLOAD);
