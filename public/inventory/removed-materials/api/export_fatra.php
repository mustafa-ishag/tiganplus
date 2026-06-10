<?php
/**
 * export_fatra.php - تحويل نموذج FATRA إلى PDF باستخدام mPDF
 * قالب HTML مطابق لملف FATRA.docx الأصلي
 */
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

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

// تحميل مكتبة mPDF من مجلد scrap
require __DIR__ . '/../../../../scrap/vendor/autoload.php';

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
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
};
$st = $e($item['functional_location'] ?? '');
$se = $e($item['serial_number'] ?? '');
$qu = $e($item['equipment'] ?? '');
$sec = $e($item['item_number'] ?? '');
$ca = $e($item['capacity_kva'] ?? '');
$ma = $e($item['manufacturer'] ?? '');
$wo = $e($woNumber);
$ar = $e($woLocation);

$isReturn = (isset($item['status']) && $item['status'] === 'إرجاع');

$check4 = $isReturn ? '<span style="font-family: dejavusans; font-size: 18pt;">✓</span>' : '&nbsp;';
$check5 = (!$isReturn) ? '<span style="font-family: dejavusans; font-size: 18pt;">✓</span>' : '&nbsp;';

// الشعار
$logoPath = realpath(__DIR__ . '/../../../../scrap/SEC.png');
if (!$logoPath) $logoPath = realpath(__DIR__ . '/../../../assets/images/SEC.png');
$logoData = '';
if ($logoPath && file_exists($logoPath)) {
    $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}
$logoImg = $logoData ? '<img src="' . $logoData . '" width="100" />' : '';

$F = '■';
$E = '□';

$html = <<<HTML
<style>
    * { margin:0; padding:0; }
    body { font-family: 'Times New Roman', times, serif; font-size: 10pt; direction: rtl; }
    table { border-collapse: collapse; width:100%; }
    td { border:0.3pt solid #000; padding:1px 3px; font-size: 10pt; height:15px; vertical-align:middle; line-height: 1.1; }
    .title { font-size:11pt; font-weight:bold; text-align:center; }
    .nb { border:none !important; }
    .center { text-align:center; }
    .bold { font-weight:bold; }
    .sm { font-size:7pt; }
    .xs { font-size:6.5pt; }
    .bg-cyan { background-color: #cbffff; }
    .bg-blue { background-color: #b4c6e7; }
    .bg-green { background-color: #c6e0b4; }
    .bg-yellow { background-color: #fff2cc; }
    .bg-gray { background-color: #d9d9d9; }
    .bg-light { background-color:#f2f2f2; }
    .text-red { color: #c00000; }
    .bg-peach { background-color: #ffcb99; }
    .proc-table td { height: 28px; }
</style>

<div style="border: 4px solid #000; padding: 1px;">

<table style="border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr>
    <td width="8%" class="bg-peach bold center">قم الضبط</td>
    <td width="20%" class="bg-peach">&nbsp;</td>
    <td width="25%" class="bold center text-red">القيمة الدفترية الأولية :</td>
    <td width="20%" class="bold center text-red">التاريخ &nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;</td>
    <td width="12%" class="bg-peach bold center">رقم الضبط القديم</td>
    <td width="15%" class="bg-peach">&nbsp;</td>
</tr>
</table>

<table style="margin-top: 0;" class="proc-table">
<tr class="bg-cyan">
    <td colspan="2" class="bold" style="font-size:12pt; text-align:right; padding-right:10px;">لاستخدام مقدم الطلب (1) - اسم ورمز الجهة:</td>
    <td class="bg-cyan">&nbsp;</td>
    <td class="bold center">مركز<br>التكلفة</td>
    <td class="bg-cyan">&nbsp;</td>
</tr>
<tr class="bg-light">
    <td width="8%" class="center bold" style="white-space:nowrap;">إجراء رقم</td>
    <td width="40%" class="center bold">نوع الاستبعاد</td>
    <td width="8%" class="center bold" style="white-space:nowrap;">تأشير</td>
    <td colspan="2" width="44%" class="center bold" style="font-size:12pt;">بيانات المعدة (وحدة الممتلكات)(2)</td>
</tr>
<tr>
    <td class="center" rowspan="2">1</td>
    <td rowspan="2">تحويل مباشر من موقع أو إدارة لأخرى.</td>
    <td class="center" rowspan="2">&nbsp;</td>
    <td width="22%" class="bold center">رقم بطاقة المعدة القديمة</td>
    <td width="22%" class="bold center" style="font-size:11pt;">{$st}</td>
</tr>
<tr>
    <td class="bold center">رقم بطاقة المعدة الجديدة</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="center">2</td>
    <td>تحويل معدة إلى المخزون العادي</td>
    <td class="center">&nbsp;</td>
    <td class="bold bg-light">الرقم التسلسلي</td>
    <td class="bold center">{$se}</td>
</tr>
<tr>
    <td class="center">3</td>
    <td>تحويل معدة إلى المخزون المؤقت</td>
    <td class="center">&nbsp;</td>
    <td class="bold bg-light">وصف المعدة</td>
    <td class="bold center">{$qu}</td>
</tr>
<tr>
    <td class="center">4</td>
    <td>تحويل معدة إلى المخزون القابل للإصلاح</td>
    <td class="center bold">{$check4}</td>
    <td class="bold bg-light">رقم المادة</td>
    <td class="bold center">{$sec}</td>
</tr>
<tr>
    <td class="center">5</td>
    <td>تحويل معدة لإدارة المواد لغرض الاستبعاد</td>
    <td class="center bold">{$check5}</td>
    <td class="bold bg-light">رقم طلب التصرف</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="center">6</td>
    <td>استبعاد معدة في الموقع</td>
    <td class="center">&nbsp;</td>
    <td colspan="2" class="bold center" style="background-color: #d9d9d9;">تعبأ البيانات ادناه إذا كانت المعدة بدون بطاقة</td>
</tr>
<tr>
    <td class="center">7</td>
    <td>تحويل معدة إلى المخزون المؤقت</td>
    <td class="center">&nbsp;</td>
    <td class="bold bg-light">موقع المعدة</td>
    <td class="bold center">{$ar}</td>
</tr>
<tr>
    <td class="center">8</td>
    <td>استبعاد معدة إصلاحها غير اقتصادي</td>
    <td class="center">&nbsp;</td>
    <td class="bold bg-light">تاريخ التشغيل</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="center">9</td>
    <td>تحويل معدة لجهة خارج الشركة</td>
    <td class="center">&nbsp;</td>
    <td class="bold bg-light">القدرة / الجهد</td>
    <td class="bold center">{$ca}</td>
</tr>
</table>

<table style="margin-top:-1px; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr>
    <td width="8%" class="bold center">رقم الحجز</td>
    <td width="60%" colspan="3" class="bold center" style="padding:4px; font-size:9pt;">يجب على الجهة مقدمة الطلب أن تدون في هذه الخانة رقم الحجز للإرجاع من النظام الآلي</td>
    <td width="16%">&nbsp;</td>
    <td width="16%">&nbsp;</td>
</tr>
<tr>
    <td width="8%">&nbsp;</td>
    <td width="20%" class="center bold">إعداد</td>
    <td width="20%" class="center bold">اعتماد</td>
    <td width="20%" class="center bold">ملاحظات</td>
    <td width="16%" class="center bold">المصنع</td>
    <td width="16%" class="center bold">{$ma}</td>
</tr>
<tr>
    <td class="bold center">الاسم</td>
    <td class="center bold">م / ياسر علواني</td>
    <td class="center bold">م/ إبراهيم الاسمري</td>
    <td class="center bold">المقاول</td>
    <td class="center bold">رقم امر العمل/<br>المشروع</td>
    <td class="center bold">{$wo}</td>
</tr>
<tr>
    <td class="bold center">الوظيفة</td>
    <td class="center bold">مهندس المواد</td>
    <td class="center bold">مدير دائرة الانشاءات</td>
    <td class="center bold">شركة توت المصيف</td>
    <td class="center bold">رقم مركز<br>التكلفة</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="bold center">التوقيع</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td rowspan="3">&nbsp;</td>
    <td class="center bold">التكلفة في تاريخ<br>التركيب</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="bold center">الهاتف</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td class="center bold">او التكلفة تقديرية</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="bold center">العنوان البريدي</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td class="center bold">او تكلفة شراء<br>الجديد</td>
    <td>&nbsp;</td>
</tr>
</table>

<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr><td>ملاحظات:</td></tr>
</table>

<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr style="background-color:#99cbff;">
    <td colspan="9" class="bold" style="font-size:12pt; text-align:right; padding-right:10px; border-bottom: 2px solid #000;">لاستخدام مراقبة الحسابات (3)</td>
</tr>
<tr>
    <td width="12%" class="center bold">العمر<br>الافتراضي</td>
    <td width="6%">&nbsp;</td>
    <td width="10%" class="center bold">سنوات<br>الخدمة</td>
    <td width="6%">&nbsp;</td>
    <td width="38%" colspan="3" class="bold text-red" style="text-align:right; padding-right:5px; font-size:7.5pt;">
        تغيير موقع الأصل <span style="font-size:12pt;">□</span> &nbsp;&nbsp;&nbsp;
        إيقاف الاستهلاك <span style="font-size:12pt;">□</span> &nbsp;&nbsp;&nbsp;
        إعادة تصنيف الأصل إلى متاح للبيع <span style="font-size:12pt;">□</span>
    </td>
    <td width="12%" class="center bold">حالة الإجراء - مكتمل</td>
    <td width="16%">&nbsp;</td>
</tr>
<tr>
    <td class="center">تكلفة الأصل</td>
    <td colspan="3">&nbsp;</td>
    <td>&nbsp;</td>
    <td class="center bold">إعداد</td>
    <td class="center bold">اعتماد</td>
    <td style="text-align:right;">التاريخ:</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="text-red center" rowspan="2">القيمة الدفترية<br>النهائية **</td>
    <td colspan="3" rowspan="2">&nbsp;</td>
    <td class="center">الاسم</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td colspan="2" rowspan="2">&nbsp;</td>
</tr>
<tr>
    <td class="center">التوقيع</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
</table>

<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr style="background-color:#cbffcb;">
    <td colspan="5" class="bold" style="font-size:12pt; text-align:right; padding-right:10px;">لاستخدام قطاع المواد (4)</td>
</tr>
<tr>
    <td width="20%" class="center bold">رقم الإجراء الموصى به</td>
    <td width="20%" class="center bold">الاسم</td>
    <td width="20%" class="center bold">الوظيفة</td>
    <td width="20%" class="center bold">التوقيع</td>
    <td width="20%" class="center bold">التاريخ</td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr><td class="bold" style="text-align:right; padding-right:5px;">ملاحظات:</td></tr>
</table>

<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr style="background-color:#ffff99;">
    <td colspan="4" class="bold" style="font-size:12pt; text-align:right; padding-right:10px;">لاستخدام جهة الاستلام - اشعار باستلام وحدة الممتلكات (5)</td>
    <td colspan="2" class="bold center">مركز تكلفة الجهة المستلمة</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td width="12%" class="center">اسم الجهة</td>
    <td width="18%">&nbsp;</td>
    <td width="10%" class="center">المستلم</td>
    <td width="15%">&nbsp;</td>
    <td width="12%" class="center">اسم المعتمد</td>
    <td width="15%">&nbsp;</td>
    <td width="18%" rowspan="3" class="bold" style="text-align:right; padding-right:5px; vertical-align:top; padding-top:4px;">ملاحظات:</td>
</tr>
<tr>
    <td class="center">الموقع</td><td>&nbsp;</td><td class="center">الوظيفة</td><td>&nbsp;</td><td class="center">الوظيفة</td><td>&nbsp;</td>
</tr>
<tr>
    <td class="center">تاريخ<br>الاستلام</td><td>&nbsp;</td><td class="center">التوقيع</td><td>&nbsp;</td><td class="center">التوقيع</td><td>&nbsp;</td>
</tr>
</table>

<table style="margin-top: 0; border-bottom: 4px solid #000; margin-bottom: 2px;">
<tr>
    <td colspan="6" class="bold" style="background-color:#d8d8d8; font-size:12pt; text-align:right; padding-right:10px; width:45%;">لاستخدام لجنة التصرف بالمواد (6)</td>
    <td rowspan="3" class="bold" style="vertical-align:top; text-align:right; padding-top:4px; padding-right:5px; width:25%;">الختم والتاريخ</td>
    <td class="bold center" style="background-color:#ffcb99; width:30%;">الإجراء الفعلي الذي تم على الوحدة (7)</td>
</tr>
<tr>
    <td colspan="6" class="bold center">نوع التصرف الموصى به</td>
    <td rowspan="2">&nbsp;</td>
</tr>
<tr>
    <td width="9%" class="center">بيع</td><td width="6%">&nbsp;</td>
    <td width="9%" class="center">تخريد</td><td width="6%">&nbsp;</td>
    <td width="9%" class="center">أخرى</td><td width="6%">&nbsp;</td>
</tr>
</table>

<table style="margin-top: 0;">
<tr style="background-color:#cbffff;">
    <td colspan="4" class="bold" style="font-size:12pt; text-align:right; padding-right:10px;">الموافقة على التوصيات / اعتماد صاحب الصلاحية(8)</td>
</tr>
<tr>
    <td width="10%">&nbsp;</td>
    <td width="30%" class="center bold">مدير إدارة كهرباء الطائف</td>
    <td width="30%" class="center bold">رئيس القطاع المعني</td>
    <td width="30%" class="center bold">نائب النشاط المعني</td>
</tr>
<tr>
    <td class="center">الاسم</td>
    <td class="center bold">م / محمد سعد الشلوي</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
<tr>
    <td class="center">التوقيع</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
</tr>
<tr>
    <td colspan="2" class="bold" style="text-align:right; padding-right:5px;">جهة الموافقة على الاستبعاد: <span style="color:blue;">(حسب مصفوفة الصلاحيات)</span></td>
    <td colspan="2" class="bold" style="text-align:right; padding-right:5px;">رقم وتاريخ خطاب /قرار الموافقة :</td>
</tr>
</table>

</div>
HTML;

$tempDir = __DIR__ . '/temp/';
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

$filePrefix = $isReturn ? 'FATRA-RETURN' : 'FATRA';
$itemNumber = $item['item_number'] ?? 'export';
$uniqueId = time() . '_' . rand(1000, 9999);
$tempPdfName = "{$filePrefix}_{$itemNumber}_{$uniqueId}.pdf";
$tempPdf = $tempDir . $tempPdfName;

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 8,
    'margin_right' => 8,
    'margin_top' => 3,
    'margin_bottom' => 3,
    'default_font_size' => 10,
    'default_font' => 'times',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);

$mpdf->WriteHTML($html);
$mpdf->Output($tempPdf, \Mpdf\Output\Destination::FILE);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$publicUrl = $protocol . $host . $scriptDir . '/temp/' . $tempPdfName;

header('Content-Type: application/json');
echo json_encode(['url' => $publicUrl, 'error' => null]);
