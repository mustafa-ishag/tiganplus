<?php
/**
 * export-excel.php - تصدير تحليل المواد إلى Excel باستخدام القالب
 * Material Analysis Excel Export using template
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$workOrderId = (int)($_GET['work_order_id'] ?? 0);
if ($workOrderId <= 0) {
    http_response_code(400);
    exit('معرف أمر العمل مطلوب');
}

try {
    $db = getDB();

    // جلب معلومات أمر العمل
    $woStmt = $db->prepare("
        SELECT wo.*, wot.type_code, wot.description as type_description
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE wo.id = ?
    ");
    $woStmt->execute([$workOrderId]);
    $woInfo = $woStmt->fetch(PDO::FETCH_ASSOC);

    if (!$woInfo) {
        http_response_code(404);
        exit('أمر العمل غير موجود');
    }

    // جلب المواد المصروفة (صافي = مصروف - مرتجع)
    $sqlMaterials = "
        SELECT
            td.material_id,
            m.item_number,
            mc.group_number,
            mc.description,
            mc.unit,
            SUM(CASE WHEN it.transaction_type = 'outgoing' THEN td.quantity ELSE 0 END) as outgoing_qty,
            SUM(CASE WHEN it.transaction_type = 'return' THEN td.quantity ELSE 0 END) as return_qty,
            SUM(CASE WHEN it.transaction_type = 'outgoing' THEN td.quantity ELSE 0 END) -
            SUM(CASE WHEN it.transaction_type = 'return' THEN td.quantity ELSE 0 END) as net_qty
        FROM transaction_details td
        JOIN inventory_transactions it ON td.transaction_id = it.id
        JOIN materials m ON td.material_id = m.id
        LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE it.work_order_id = ?
          AND it.transaction_type IN ('outgoing', 'return')
          AND it.status = 'approved'
        GROUP BY td.material_id, m.item_number, mc.group_number, mc.description, mc.unit
        HAVING net_qty > 0
        ORDER BY m.item_number
    ";
    $stmt = $db->prepare($sqlMaterials);
    $stmt->execute([$workOrderId]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب كميات المقايسة من شهادات الإنجاز
    $sqlEstimates = "
        SELECT
            ccm.material_id,
            SUM(ccm.estimated_quantity) as estimated_qty
        FROM completion_certificate_materials ccm
        JOIN completion_certificates cc ON ccm.certificate_id = cc.id
        WHERE cc.work_order_id = ?
        GROUP BY ccm.material_id
    ";
    $stmtEst = $db->prepare($sqlEstimates);
    $stmtEst->execute([$workOrderId]);
    $estimates = [];
    foreach ($stmtEst->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $estimates[$e['material_id']] = (float) $e['estimated_qty'];
    }

    // تحميل القالب
    $templatePath = __DIR__ . '/../../assets/templates/material_analysis.xlsx';
    if (!file_exists($templatePath)) {
        http_response_code(500);
        exit('قالب Excel غير موجود');
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    // كتابة رقم أمر العمل مع كود النوع في D2
    $woLabel = $woInfo['work_order_number'];
    if (!empty($woInfo['type_code'])) {
        $woLabel .= ' - ' . $woInfo['type_code'];
    }
    $sheet->setCellValue('D2', $woLabel);

    // كتابة التاريخ في F2
    $sheet->setCellValue('F2', date('Y-m-d'));

    $materialCount = count($materials);
    $dataStartRow = 4; // الصف الأول للبيانات

    // إذا كان عدد المواد أكثر من 1، أدرج صفوف إضافية قبل صف التواقيع
    if ($materialCount > 1) {
        $rowsToInsert = $materialCount - 1;
        // إدراج صفوف جديدة بعد الصف 4 (لدفع التواقيع للأسفل)
        $sheet->insertNewRowBefore($dataStartRow + 1, $rowsToInsert);

        // نسخ التنسيق والمعادلات من الصف 4 إلى الصفوف الجديدة
        for ($i = 1; $i <= $rowsToInsert; $i++) {
            $newRow = $dataStartRow + $i;
            // نسخ ارتفاع الصف
            $sheet->getRowDimension($newRow)->setRowHeight(
                $sheet->getRowDimension($dataStartRow)->getRowHeight()
            );
            // نسخ تنسيق كل خلية من الصف 4
            for ($col = 'A'; $col <= 'H'; $col++) {
                $sourceCell = $col . $dataStartRow;
                $targetCell = $col . $newRow;
                // نسخ التنسيق
                $sheet->getStyle($targetCell)->applyFromArray(
                    $sheet->getStyle($sourceCell)->exportArray()
                );
            }
        }
    }

    // كتابة بيانات المواد
    foreach ($materials as $index => $mat) {
        $row = $dataStartRow + $index;
        $seq = $index + 1;

        // العمود A: الترقيم التسلسلي
        $sheet->setCellValue('A' . $row, $seq);
        // العمود B: رقم البند (item_number)
        $sheet->setCellValue('B' . $row, $mat['item_number']);
        // العمود C: رقم المجموعة (group_number)
        $sheet->setCellValue('C' . $row, $mat['group_number'] ?? '');
        // العمود D: وصف المادة
        $sheet->setCellValue('D' . $row, $mat['description']);
        // العمود E: المقايسة (estimated_qty)
        $estimatedQty = $estimates[$mat['material_id']] ?? 0;
        $sheet->setCellValue('E' . $row, $estimatedQty);
        // العمود F: الطبيعة (net_qty = المصروف الفعلي)
        $sheet->setCellValue('F' . $row, (float)$mat['net_qty']);
        // العمود G: الصرف (الفرق إذا الطبيعة أكبر من المقايسة)
        $sheet->setCellValue('G' . $row, "=IF(F{$row}>E{$row},F{$row}-E{$row},0)");
        // العمود H: الارجاع (الفرق إذا المقايسة أكبر من الطبيعة)
        $sheet->setCellValue('H' . $row, "=IF(F{$row}<E{$row},E{$row}-F{$row},0)");
    }

    // تنظيف أي مخرجات سابقة
    if (ob_get_level()) ob_end_clean();

    // تصدير الملف
    $woNumber = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $woInfo['work_order_number']);
    $filename = "material_analysis_{$woNumber}_" . date('Y-m-d') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('[material-analysis-export] Error: ' . $e->getMessage());
    http_response_code(500);
    exit('خطأ في تصدير الملف: ' . $e->getMessage());
}
