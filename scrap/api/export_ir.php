<?php
/**
 * export_ir.php - تحويل نموذج IR إلى PDF باستخدام mPDF
 * قالب HTML مطابق لملف IR.docx الأصلي
 */
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['url' => null, 'error' => "$errstr in $errfile on line $errline"]);
    exit;
});
set_exception_handler(function ($e) {
    header('Content-Type: application/json');
    echo json_encode(['url' => null, 'error' => $e->getMessage()]);
    exit;
});

require '../vendor/autoload.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['item'])) {
    header('Content-Type: application/json');
    echo json_encode(['url' => null, 'error' => 'Missing item data']);
    exit;
}

$item = $data['item'];
$woLocation = $data['woLocation'] ?? '';
$woNumber = $data['woNumber'] ?? '';

$e = function ($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
$st = $e($item['functional_location'] ?? '');
$se = $e($item['serial_number'] ?? '');
$ye = $e(isset($item['manufacture_year']) ? (string) $item['manufacture_year'] : '');
$qu = $e($item['equipment'] ?? '');
$sec = $e($item['item_number'] ?? '');
$ca = $e($item['capacity_kva'] ?? '');
$pr = $e($item['prim_sec_volt'] ?? '');
$ma = $e($item['manufacturer'] ?? '');
$wo = $e($woNumber);
$ar = $e($woLocation);

$isReturn = (isset($item['status']) && $item['status'] === 'إرجاع');

// الشعار
$logoPath = realpath(__DIR__ . '/../SEC.png');
$logoData = '';
if ($logoPath && file_exists($logoPath)) {
    $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}
$logoImg = $logoData ? '<img src="' . $logoData . '" width="130" />' : '';

$F = '■';
$E = '□';

$html = <<<HTML
<style>
    * { margin:0; padding:0; }
    body { font-family: 'times new roman', dejavusans, serif; font-size: 10pt; direction: ltr; }
    table { border-collapse: collapse; width:100%; }
    td { border:0.3pt solid #000; padding:4px 6px; font-weight:bold; font-size:10pt; height:28px; vertical-align:middle; }
    .chk { font-size:9.5pt; font-weight:normal; }
    .sec-title { font-size:10pt; font-weight:bold; text-align:center; }
    .nb { border:none !important; }
    .nbl { border-left:none !important; }
    .nbr { border-right:none !important; }
    .sm { font-weight:normal; font-size:9pt; }
</style>

<!-- HEADER -->
<table style="margin-bottom:5px;">
<tr>
    <td width="18%" style="border:none; vertical-align:middle;">{$logoImg}</td>
    <td width="64%" style="border:none; text-align:center; vertical-align:middle;">
        <div style="font-size:14pt; font-weight:bold;">REMOVED DISTRIBUTION EQUIPMENT</div>
        <div style="font-size:14pt; font-weight:bold; margin-top:4px;">INSPECTION REPORT</div>
    </td>
    <td width="18%" style="border:none;">&nbsp;</td>
</tr>
</table>

<!-- INFO TABLE -->
<table>
<tr><td width="33%"><u>AREA:</u>{$ar}</td><td width="34%">DIV.:</td><td width="33%">S/S # &nbsp; {$st}</td></tr>
<tr><td>USER MDR #</td><td>S-</td><td>SERIAL # &nbsp; {$se}</td></tr>
<tr><td>SEC # : &nbsp; {$sec}</td><td>Equipment : &nbsp; {$qu}</td><td>Year of manufacture : &nbsp; {$ye}</td></tr>
<tr><td><u>Make</u> : &nbsp; {$ma}</td><td>Prim / <u>Sec.Volt</u> : &nbsp; {$pr}</td><td>Capacity (KVA) : &nbsp; {$ca}</td></tr>
</table>

<!-- UDS NO -->
<table style="margin-top:-1px;">
<tr><td>UDS NO. &nbsp; {$wo}</td></tr>
</table>

<!-- INSPECTION CHECKLIST -->
<table style="margin-top:-1px;">
<tr>
    <td width="33%"><span class="chk">{$E} &nbsp; Oil leaks (specify) :</span></td>
    <td width="34%" class="nbl nbr">&nbsp;</td>
    <td width="33%"><span class="chk">{$F} &nbsp; Burnt / Melted Parts</span></td>
</tr>
<tr>
    <td colspan="2"><span class="chk">{$E} &nbsp; Cracked / Chipped bushing &nbsp; HV (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) ea. &nbsp;&nbsp;&nbsp; LV (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) ea.</span></td>
    <td><span class="chk">{$E} &nbsp; Bulged / Corroded Body</span></td>
</tr>
<tr><td colspan="3"><span class="chk">{$E} &nbsp; Broken / Missing accessories( specify) :</span></td></tr>
</table>

<!-- REASONS FOR REMOVAL - 12 col grid -->
<table style="margin-top:-1px;">
<tr>
    <td colspan="4" class="nb">&nbsp;</td>
    <td colspan="4" class="sec-title" style="border:0.3pt solid #000;">REASONS FOR REMOVEAL</td>
    <td colspan="4" class="nb">&nbsp;</td>
</tr>
<tr>
    <td colspan="4"><span class="chk">{$E} &nbsp; System Reinforcement</span></td>
    <td colspan="4"><span class="chk">{$F} &nbsp; System improvement</span></td>
    <td colspan="4"><span class="chk">{$E} &nbsp;<u>Suspected &nbsp;defective</u> / Defective</span></td>
</tr>
<tr>
    <td colspan="3"><span class="chk">{$E} &nbsp; Damaged by SEC contractor</span></td>
    <td colspan="3"><span class="chk">{$E} &nbsp; <u>Damaged</u> by others</span></td>
    <td colspan="3"><span class="chk">{$E}Partially/totally damaged</span></td>
    <td colspan="3"><span class="chk">{$E}Non Standard</span></td>
</tr>
<tr>
    <td colspan="4"><span class="chk">{$E} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Others</span></td>
    <td colspan="4">Commissioning <u>Data</u> :</td>
    <td colspan="4">Removal / Failure <u>Date</u> :</td>
</tr>
</table>

<!-- USER'S RECOMMENDATION - 12 col grid -->
<table style="margin-top:-1px;">
<tr>
    <td colspan="4" class="nb">&nbsp;</td>
    <td colspan="4" class="sec-title" style="border:0.3pt solid #000;">USER'S RECOMMENDATION</td>
    <td colspan="4" class="nb">&nbsp;</td>
</tr>
<tr>
    <td colspan="3"><span class="chk">{$E} &nbsp; REQUESTED FOR FUTURE</span></td>
    <td colspan="3"><span class="chk">{$E}WARRANTY</span></td>
    <td colspan="3"><span class="chk">{$E}REPAIRABLE</span></td>
    <td colspan="3"><span class="chk">{$F} &nbsp; RECOMMENDED FOR &nbsp;SCRAP</span></td>
</tr>
<tr><td colspan="12"><span class="chk">{$E} &nbsp; <u>COMMENTS</u> :</span></td></tr>
</table>

<!-- Signatures 1 -->
<table style="margin-top:-1px;">
<tr><td width="33%"><u>Prepared by</u> :</td><td width="34%"><u>Approved by</u> :</td><td width="33%"><u>Date</u> :</td></tr>
</table>

<!-- WORKSHOP ACTION -->
<table style="margin-top:-1px;">
<tr>
    <td colspan="4" class="nb">&nbsp;</td>
    <td colspan="4" class="sec-title" style="border:0.3pt solid #000;">WORKSHOP ACTION</td>
    <td colspan="4" class="nb">&nbsp;</td>
</tr>
</table>
<table style="margin-top:-1px;">
<tr>
    <td width="16%" style="text-align:center;">WORKSHOP</td>
    <td width="28%"><span class="chk">{$E} &nbsp; Partially Damaged</span></td>
    <td width="28%"><span class="chk">{$E} &nbsp; Totally Damaged</span></td>
    <td width="28%"><span class="chk">{$E} &nbsp; Severely Rusted</span></td>
</tr>
<tr>
    <td style="text-align:center;">DIVISION</td>
    <td><span class="chk">{$E} &nbsp; Uneconomical to Repair</span></td>
    <td><span class="chk">{$E} &nbsp; REPAIRABLE</span></td>
    <td><span class="chk">{$F} &nbsp; SCRAP</span></td>
</tr>
<tr><td>&nbsp;</td><td><u>COMMENTS</u> :</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td style="text-align:center;">WAREHOUSE</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>

<!-- Signatures 2 -->
<table style="margin-top:-1px;">
<tr><td width="33%"><u>Prepared by</u> :</td><td width="34%"><u>Approved by</u> :</td><td width="33%">Date</td></tr>
</table>

<!-- BOTTOM: INVENTORY/PLANNING -->
<table style="margin-top:20px;">
<tr>
    <td width="16%">&nbsp;</td>
    <td width="21%" style="text-align:center;"><span class="chk">{$E} &nbsp; REPAIRABLE</span></td>
    <td width="21%">&nbsp;</td>
    <td width="21%">&nbsp;</td>
    <td width="21%" style="text-align:center;"><span class="chk">{$E} &nbsp; SCRAP / SALE</span></td>
</tr>
<tr>
    <td style="text-align:center;font-size:9pt;">INVENTORY</td>
    <td><span class="chk">{$E} Standard Item</span></td>
    <td><span class="chk">{$E} Substitute</span></td>
    <td><span class="chk">{$E} <u>Non standard</u></span></td>
    <td><span class="chk">{$E} Non Moving</span></td>
</tr>
<tr>
    <td style="text-align:center;font-size:9pt;">PLANNING</td>
    <td><span class="chk">{$E} Fast moving</span></td>
    <td><span class="chk">{$E} Others</span></td>
    <td><span class="chk">{$E} Over Stocking</span></td>
    <td><span class="chk">{$E} Damaged</span></td>
</tr>
</table>

<!-- DEPARTMENT -->
<table style="margin-top:-1px;">
<tr>
    <td width="16%" style="text-align:center;font-size:9pt;">DEPARTMENT</td>
    <td width="12%" style="font-size:7.5pt;font-weight:normal;">Availability:</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">01:___</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">02___</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">05: ___</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">07: ___</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">08: ___</td>
    <td width="9%" style="font-size:7.5pt;font-weight:normal;">Sub.___</td>
    <td width="18%" style="font-size:7.5pt;font-weight:normal;">Total:_________</td>
</tr>
<tr>
    <td>&nbsp;</td>
    <td style="font-size:7.5pt;font-weight:normal;">3 Year Usage :</td>
    <td colspan="3" style="font-size:7.5pt;font-weight:normal;">12 &nbsp;: __________</td>
    <td colspan="2" style="font-size:7.5pt;font-weight:normal;">13 &nbsp;: __________</td>
    <td colspan="2" style="font-size:7.5pt;font-weight:normal;">Current &nbsp;: _________</td>
</tr>
<tr>
    <td style="text-align:center;font-size:9pt;">IPD</td>
    <td colspan="8" class="sm"><u>Comments</u> :</td>
</tr>
<tr><td>&nbsp;</td><td colspan="8">&nbsp;</td></tr>
</table>

<!-- Final Signatures -->
<table style="margin-top:-1px;">
<tr><td width="33%"><u>Prepared by</u> :</td><td width="34%"><u>Approved by</u> :</td><td width="33%"><u>Date</u> :</td></tr>
</table>
HTML;

// إعداد مجلد PDF المؤقت
$tempDir = __DIR__ . '/temp/';
if (!is_dir($tempDir))
    mkdir($tempDir, 0755, true);

$filePrefix = $isReturn ? 'IR-RETURN' : 'IR';
$itemNumber = $item['item_number'] ?? 'export';
$uniqueId = time() . '_' . rand(1000, 9999);
$tempPdfName = "{$filePrefix}_{$itemNumber}_{$uniqueId}.pdf";
$tempPdf = $tempDir . $tempPdfName;

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 8,
    'margin_bottom' => 8,
    'default_font_size' => 10,
    'default_font' => 'times',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);

$mpdf->WriteHTML($html);
$mpdf->Output($tempPdf, \Mpdf\Output\Destination::FILE);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
$publicUrl = $protocol . $host . $baseDir . '/api/temp/' . $tempPdfName;

header('Content-Type: application/json');
echo json_encode(['url' => $publicUrl, 'error' => null]);
