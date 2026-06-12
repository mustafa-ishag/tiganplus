<?php

declare(strict_types=1);

/**
 * تصدير أوامر العمل مع تفاصيل المستخلصات والنماذج المرفقة
 * Export Work Orders with Extract Details and Attachments
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_export')) {
    $_SESSION['error'] = 'ليس لديك صلاحية لتصدير أوامر العمل';
    header('Location: index.php');
    exit();
}

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    // $db و $userId تم تعريفهما بالفعل في فحص الصلاحيات

    // الحصول على المعاملات الأساسية
    $format = $_GET['format'] ?? 'xlsx';
    $status = $_GET['status'] ?? 'all';
    $department = $_GET['department'] ?? '';
    $branch_id = $_GET['branch_id'] ?? '';
    $include_extracts = $_GET['include_extracts'] ?? '1';
    $include_attachments = $_GET['include_attachments'] ?? '1';

    // الحصول على الفلاتر الإضافية
    $current_entity = $_GET['current_entity'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $quick_filter = $_GET['quick_filter'] ?? '';

    // الفلاتر المتعددة (JSON)
    $completion_certificate = !empty($_GET['completion_certificate']) ? json_decode($_GET['completion_certificate'], true) : [];
    $certificate_confirmation = !empty($_GET['certificate_confirmation']) ? json_decode($_GET['certificate_confirmation'], true) : [];
    $disbursement_status = !empty($_GET['disbursement_status']) ? json_decode($_GET['disbursement_status'], true) : [];
    $precise_drilling = !empty($_GET['precise_drilling']) ? json_decode($_GET['precise_drilling'], true) : [];
    $excavation = !empty($_GET['excavation']) ? json_decode($_GET['excavation'], true) : [];
    $demolition = !empty($_GET['demolition']) ? json_decode($_GET['demolition'], true) : [];
    $f1_form = !empty($_GET['f1_form']) ? json_decode($_GET['f1_form'], true) : [];
    $assets_receipt = !empty($_GET['assets_receipt']) ? json_decode($_GET['assets_receipt'], true) : [];

    // إذا كان التنسيق xlsx، استخدم الـ Exporter الجديد
    if ($format === 'xlsx') {
        error_log("Work Orders Export: Format is xlsx, using WorkOrderExcelExporter");

        $filters = [
            'status' => $status,
            'department' => $department,
            'branch_id' => $branch_id,
            'current_entity' => $current_entity,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'quick_filter' => $quick_filter,
            'completion_certificate' => $completion_certificate,
            'certificate_confirmation' => $certificate_confirmation,
            'disbursement_status' => $disbursement_status,
            'precise_drilling' => $precise_drilling,
            'excavation' => $excavation,
            'demolition' => $demolition,
            'f1_form' => $f1_form,
            'assets_receipt' => $assets_receipt,
            'include_extracts' => $include_extracts,
            'include_attachments' => $include_attachments
        ];

        error_log("Work Orders Export: Filters = " . json_encode($filters));

        try {
            error_log("Work Orders Export: Loading WorkOrderExcelExporter class");
            require_once __DIR__ . '/../../includes/WorkOrderExcelExporter.php';

            error_log("Work Orders Export: Creating exporter instance");
            $exporter = new WorkOrderExcelExporter($db, $userId, $filters);

            error_log("Work Orders Export: Calling export method");
            $exporter->export();

            error_log("Work Orders Export: Export completed successfully");
            exit();
        } catch (Exception $e) {
            error_log("Excel Export Error: " . $e->getMessage());
            error_log("Excel Export File: " . $e->getFile());
            error_log("Excel Export Line: " . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());

            $_SESSION['error'] = 'خطأ في تصدير البيانات: ' . $e->getMessage();
            header('Location: index.php');
            exit();
        }
    }
    
    // تسجيل بداية عملية التصدير
    $logStmt = $db->prepare("
        INSERT INTO work_order_import_export_logs 
        (operation_type, file_name, file_format, operation_status, export_filters, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $filename = 'work_orders_export_' . date('Y-m-d_H-i-s') . '.' . $format;
    $filters = json_encode([
        'status' => $status,
        'department' => $department,
        'branch_id' => $branch_id,
        'include_extracts' => $include_extracts,
        'include_attachments' => $include_attachments
    ], JSON_UNESCAPED_UNICODE);
    
    $logStmt->execute(['export', $filename, $format, 'processing', $filters, $userId]);
    $logId = $db->lastInsertId();
    
    // بناء الاستعلام الأساسي
    $query = "
        SELECT wo.*,
               wot.type_code as work_order_type_code,
               b.name as branch_name,
               b.code as branch_code,
               ce.name as current_entity_name,
               -- النماذج المرفقة
               woa_exc.status as excavation_form_status,
               woa_drill.status as precise_drilling_form_status,
               woa_demo.status as demolition_form_status,
               woa_f1.status as f1_form_status,
               woa_comp.status as completion_certificate_status,
               woa_comp.completion_certificate_confirmation,
               woa_comp.certificate_attached_date,
               woa_comp.certificate_confirmed_date,
               -- المستخلصات (قيمة أمر العمل في المستخلص)
               COALESCE(pe.extract_number, fre.extract_number, ffpe.extract_number) as extract_number,
               pewo.extract_value as partial_extract_value,
               frewo.extract_value as final_regular_extract_value,
               ffpewo.extract_value as final_for_partial_extract_value
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        -- ربط مع النماذج المرفقة
        LEFT JOIN work_order_attachments woa_exc ON wo.id = woa_exc.work_order_id AND woa_exc.form_type = 'excavation_form'
        LEFT JOIN work_order_attachments woa_drill ON wo.id = woa_drill.work_order_id AND woa_drill.form_type = 'precise_drilling_form'
        LEFT JOIN work_order_attachments woa_demo ON wo.id = woa_demo.work_order_id AND woa_demo.form_type = 'demolition_form'
        LEFT JOIN work_order_attachments woa_f1 ON wo.id = woa_f1.work_order_id AND woa_f1.form_type = 'f1_form'
        LEFT JOIN work_order_attachments woa_comp ON wo.id = woa_comp.work_order_id AND woa_comp.form_type = 'completion_certificate'
        -- ربط مع المستخلصات الجزئية
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        LEFT JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
        -- ربط مع المستخلصات النهائية العادية
        LEFT JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        LEFT JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        -- ربط مع المستخلصات النهائية للجزئية
        LEFT JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        LEFT JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    ";
    
    // إضافة شروط التصفية
    $conditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $conditions[] = "wo.status = ?";
        $params[] = $status;
    }
    
    if ($department !== 'all') {
        $conditions[] = "wo.department = ?";
        $params[] = $department;
    }
    
    if ($branch_id !== 'all') {
        $conditions[] = "wo.branch_id = ?";
        $params[] = $branch_id;
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY wo.id DESC";
    
    // تنفيذ الاستعلام
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إعداد headers للتحميل
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // إضافة BOM للدعم العربي في Excel
        echo "\xEF\xBB\xBF";
        
        // فتح output stream
        $output = fopen('php://output', 'w');
        
        // كتابة العناوين
        $headers = [
            'رقم أمر العمل',
            'كود نوع الأمر',
            'القسم',
            'الجهة الحالية',
            'الفرع',
            'كود الفرع',
            'الموقع',
            'تاريخ التكليف',
            'تاريخ الاستلام',
            'القيمة المقدرة',
            'القيمة الفعلية',
            'حالة الصرف'
        ];

        // إضافة أعمدة النماذج إذا كانت مطلوبة
        if ($include_attachments === '1') {
            $headers = array_merge($headers, [
                'نموذج الحفر الدقيق',
                'نموذج الكشط',
                'نموذج التخريد',
                'نموذج F1',
                'شهادة الإنجاز',
                'تاريخ ارفاق شهادة الإنجاز',
                'تأكيد شهادة الإنجاز',
                'تاريخ تأكيد شهادة الإنجاز'
            ]);
        }

        // إضافة الحالة والملاحظات (قبل المستخلصات لتثبيت موقعها)
        $headers = array_merge($headers, [
            'الحالة',
            'الملاحظات'
        ]);

        // إضافة أعمدة المستخلصات إذا كانت مطلوبة (في النهاية)
        if ($include_extracts === '1') {
            $headers = array_merge($headers, [
                'رقم المستخلص',
                'قيمة أمر العمل في المستخلص الجزئي',
                'قيمة أمر العمل في المستخلص النهائي العادي',
                'قيمة أمر العمل في المستخلص النهائي للجزئية'
            ]);
        }
        
        fputcsv($output, $headers);
        
        // كتابة البيانات
        foreach ($workOrders as $workOrder) {
            $row = [
                $workOrder['work_order_number'],
                $workOrder['work_order_type_code'] ?? '',
                $workOrder['department'] === 'connections' ? 'التوصيلات' : 'المشاريع',
                $workOrder['current_entity_name'] ?? '',
                $workOrder['branch_name'] ?? '',
                $workOrder['branch_code'] ?? '',
                $workOrder['location'] ?? '',
                $workOrder['assignment_date'] ?? '',
                $workOrder['receipt_date'] ?? '',
                number_format((float)($workOrder['estimated_value'] ?? 0), 2, '.', ''),
                number_format((float)($workOrder['actual_value'] ?? 0), 2, '.', ''),
                translateDisbursementStatus($workOrder['disbursement_status'] ?? 'none')
            ];
            
            // إضافة بيانات النماذج إذا كانت مطلوبة
            if ($include_attachments === '1') {
                $row = array_merge($row, [
                    translateAttachmentStatus($workOrder['precise_drilling_form_status'] ?? 'not_attached'),
                    translateAttachmentStatus($workOrder['excavation_form_status'] ?? 'not_attached'),
                    translateAttachmentStatus($workOrder['demolition_form_status'] ?? 'not_attached'),
                    translateAttachmentStatus($workOrder['f1_form_status'] ?? 'not_attached'),
                    translateAttachmentStatus($workOrder['completion_certificate_status'] ?? 'not_attached'),
                    $workOrder['certificate_attached_date'] ?? '',
                    translateConfirmationStatus($workOrder['completion_certificate_confirmation'] ?? 'empty'),
                    $workOrder['certificate_confirmed_date'] ?? ''
                ]);
            }

            // إضافة الحالة والملاحظات (قبل المستخلصات لتثبيت موقعها)
            $row = array_merge($row, [
                translateStatus($workOrder['status'] ?? 'active'),
                $workOrder['notes'] ?? ''
            ]);

            // إضافة بيانات المستخلصات إذا كانت مطلوبة (في النهاية)
            if ($include_extracts === '1') {
                $row = array_merge($row, [
                    $workOrder['extract_number'] ?? '',
                    !empty($workOrder['partial_extract_value']) ? number_format((float)$workOrder['partial_extract_value'], 2, '.', '') : '',
                    !empty($workOrder['final_regular_extract_value']) ? number_format((float)$workOrder['final_regular_extract_value'], 2, '.', '') : '',
                    !empty($workOrder['final_for_partial_extract_value']) ? number_format((float)$workOrder['final_for_partial_extract_value'], 2, '.', '') : ''
                ]);
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        // تحديث سجل العملية
        $updateLogStmt = $db->prepare("
            UPDATE work_order_import_export_logs 
            SET total_records = ?, successful_records = ?, operation_status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        $updateLogStmt->execute([count($workOrders), count($workOrders), $logId]);
        
        exit();
    }
    
} catch (Exception $e) {
    // تحديث سجل العملية في حالة الخطأ
    if (isset($logId)) {
        try {
            $updateLogStmt = $db->prepare("
                UPDATE work_order_import_export_logs 
                SET operation_status = 'failed', error_message = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $updateLogStmt->execute([$e->getMessage(), $logId]);
        } catch (Exception $logError) {
            // تجاهل أخطاء التسجيل
        }
    }
    
    // في حالة الخطأ، إعادة توجيه مع رسالة خطأ
    $_SESSION['error'] = 'خطأ في تصدير البيانات: ' . $e->getMessage();

    // فحص إذا كانت headers لم ترسل بعد
    if (!headers_sent()) {
        header('Location: index.php');
    } else {
        echo "خطأ في تصدير البيانات: " . $e->getMessage();
    }
    exit();
}

// دوال مساعدة للترجمة
function translateDisbursementStatus($status) {
    $statuses = [
        'none' => 'لا يوجد',
        'completed' => 'مكتمل',
        'disbursement' => 'صرف',
        'return' => 'إرجاع',
        'disbursement_return_completed' => 'صرف وإرجاع'
    ];
    return $statuses[$status] ?? $status;
}

function translateStatus($status) {
    $statuses = [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي'
    ];
    return $statuses[$status] ?? $status;
}
    
function translateAttachmentStatus($status) {
    $statuses = [
        'attached' => 'مرفق',
        'not_attached' => 'غير مرفق',
        'not_applicable' => 'لا ينطبق'
    ];
    return $statuses[$status] ?? $status;
}

function translateConfirmationStatus($status) {
    $statuses = [
        'empty' => 'فارغ',
        'confirmed' => 'مؤكد',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض'
    ];
    return $statuses[$status] ?? $status;
}
?>
