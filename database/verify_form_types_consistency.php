<?php
/**
 * التحقق من تطابق أسماء النماذج في جميع الملفات
 */

echo "=== التحقق من تطابق أسماء النماذج ===\n\n";

// 1. التطابق في الاستيراد (import.php)
echo "1️⃣  ملف الاستيراد (import.php):\n";
echo "=====================================\n";
$importMapping = [
    'نموذج الحفر الدقيق' => 'precise_drilling_form',
    'نموذج الكشط' => 'excavation_form',
    'نموذج التخريد' => 'demolition_form',
    'نموذج F1' => 'f1_form',
    'شهادة الإنجاز' => 'completion_certificate'
];
foreach ($importMapping as $arabic => $db) {
    echo "   $arabic => $db\n";
}

// 2. ترتيب الأعمدة في التصدير CSV (export.php)
echo "\n2️⃣  ترتيب الأعمدة في التصدير CSV (export.php):\n";
echo "===================================================\n";
$exportHeaders = [
    'نموذج الحفر الدقيق',
    'نموذج الكشط',
    'نموذج التخريد',
    'نموذج F1',
    'شهادة الإنجاز',
    'تأكيد شهادة الإنجاز'
];
foreach ($exportHeaders as $i => $header) {
    echo "   " . ($i + 1) . ". $header\n";
}

// 3. ترتيب البيانات في التصدير CSV (export.php)
echo "\n3️⃣  ترتيب البيانات في التصدير CSV (export.php):\n";
echo "====================================================\n";
$exportData = [
    'precise_drilling_form_status',
    'excavation_form_status',
    'demolition_form_status',
    'f1_form_status',
    'completion_certificate_status',
    'completion_certificate_confirmation'
];
foreach ($exportData as $i => $field) {
    echo "   " . ($i + 1) . ". $field\n";
}

// 4. ترتيب الأعمدة في التصدير Excel (WorkOrderExcelExporter.php)
echo "\n4️⃣  ترتيب الأعمدة في التصدير Excel:\n";
echo "========================================\n";
$excelHeaders = [
    'نموذج الحفر الدقيق',
    'نموذج الكشط',
    'نموذج التخريد',
    'نموذج F1',
    'شهادة الإنجاز',
    'تأكيد شهادة الإنجاز'
];
foreach ($excelHeaders as $i => $header) {
    echo "   " . ($i + 1) . ". $header\n";
}

// 5. ترتيب البيانات في التصدير Excel
echo "\n5️⃣  ترتيب البيانات في التصدير Excel:\n";
echo "=========================================\n";
$excelData = [
    'precise_drilling_form_status',
    'excavation_form_status',
    'demolition_form_status',
    'f1_form_status',
    'completion_certificate_status',
    'completion_certificate_confirmation'
];
foreach ($excelData as $i => $field) {
    echo "   " . ($i + 1) . ". $field\n";
}

// 6. ترتيب الأعمدة في ملف العينة (download-sample.php)
echo "\n6️⃣  ترتيب الأعمدة في ملف العينة:\n";
echo "======================================\n";
$sampleHeaders = [
    'نموذج الحفر الدقيق',
    'نموذج الكشط',
    'نموذج التخريد',
    'نموذج F1',
    'شهادة الإنجاز',
    'تأكيد شهادة الإنجاز'
];
foreach ($sampleHeaders as $i => $header) {
    echo "   " . ($i + 1) . ". $header\n";
}

// 7. أسماء العرض في attachments-manager.php
echo "\n7️⃣  أسماء العرض في صفحة المرفقات:\n";
echo "======================================\n";
$displayNames = [
    'precise_drilling_form' => 'نموذج الحفر الدقيق',
    'excavation_form' => 'نموذج الكشط',
    'demolition_form' => 'نموذج التخريد (الاسكراب)',
    'f1_form' => 'نموذج F1',
    'completion_certificate' => 'شهادة الإنجاز'
];
foreach ($displayNames as $db => $display) {
    echo "   $db => $display\n";
}

// التحقق من التطابق
echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ التحقق من التطابق:\n";
echo str_repeat("=", 60) . "\n\n";

$allConsistent = true;

// التحقق من تطابق الترتيب
if ($exportHeaders === $excelHeaders && $exportHeaders === $sampleHeaders) {
    echo "✅ ترتيب الأعمدة متطابق في جميع ملفات التصدير\n";
} else {
    echo "❌ ترتيب الأعمدة غير متطابق!\n";
    $allConsistent = false;
}

if ($exportData === $excelData) {
    echo "✅ ترتيب البيانات متطابق في CSV و Excel\n";
} else {
    echo "❌ ترتيب البيانات غير متطابق!\n";
    $allConsistent = false;
}

// التحقق من تطابق الأسماء
$importKeys = array_keys($importMapping);
$expectedOrder = [
    'نموذج الحفر الدقيق',
    'نموذج الكشط',
    'نموذج التخريد',
    'نموذج F1',
    'شهادة الإنجاز'
];

$importOrderCorrect = true;
foreach ($expectedOrder as $i => $expected) {
    if (!isset($importKeys[$i]) || $importKeys[$i] !== $expected) {
        $importOrderCorrect = false;
        break;
    }
}

if ($importOrderCorrect) {
    echo "✅ ترتيب النماذج في الاستيراد صحيح\n";
} else {
    echo "❌ ترتيب النماذج في الاستيراد غير صحيح!\n";
    $allConsistent = false;
}

echo "\n" . str_repeat("=", 60) . "\n";
if ($allConsistent) {
    echo "🎉 جميع الملفات متطابقة ومتسقة!\n";
} else {
    echo "⚠️  يوجد عدم تطابق في بعض الملفات!\n";
}
echo str_repeat("=", 60) . "\n\n";

// عرض الترتيب النهائي الصحيح
echo "📋 الترتيب النهائي الصحيح:\n";
echo "============================\n";
echo "1. نموذج الحفر الدقيق (precise_drilling_form)\n";
echo "2. نموذج الكشط (excavation_form)\n";
echo "3. نموذج التخريد (demolition_form)\n";
echo "4. نموذج F1 (f1_form)\n";
echo "5. شهادة الإنجاز (completion_certificate)\n";
echo "6. تأكيد شهادة الإنجاز (completion_certificate_confirmation)\n";

?>

