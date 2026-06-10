<?php
/**
 * Server-Side Processing لجدول أوامر العمل
 * Work Orders DataTable Server-Side Processing
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_view')) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية لعرض أوامر العمل']);
    exit();
}

try {
    $db = getDB();
    
    // معلمات DataTable
    $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 25;
    $searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
    $orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'desc';
    
    // التحقق من طلب عرض الأوامر المكتملة
    $showCompleted = isset($_GET['show_completed']) && $_GET['show_completed'] === '1';

    // الحصول على الفلاتر الفردية
    $filterDepartment = isset($_GET['filterDepartment']) ? $_GET['filterDepartment'] : '';
    $filterCurrentEntity = isset($_GET['filterCurrentEntity']) ? $_GET['filterCurrentEntity'] : '';
    $filterBranch = isset($_GET['filterBranch']) ? $_GET['filterBranch'] : '';
    $filterDateFrom = isset($_GET['filterDateFrom']) ? $_GET['filterDateFrom'] : '';
    $filterDateTo = isset($_GET['filterDateTo']) ? $_GET['filterDateTo'] : '';

    // الفلاتر المتعددة - التعامل مع المصفوفات
    $filterCompletionCertificate = [];
    if (isset($_GET['filterCompletionCertificate'])) {
        $filterCompletionCertificate = is_array($_GET['filterCompletionCertificate'])
            ? array_filter($_GET['filterCompletionCertificate'])
            : [];
    }

    $filterCertificateConfirmation = [];
    if (isset($_GET['filterCertificateConfirmation'])) {
        $filterCertificateConfirmation = is_array($_GET['filterCertificateConfirmation'])
            ? array_filter($_GET['filterCertificateConfirmation'])
            : [];
    }

    $filterDisbursementStatus = [];
    if (isset($_GET['filterDisbursementStatus'])) {
        $filterDisbursementStatus = is_array($_GET['filterDisbursementStatus'])
            ? array_filter($_GET['filterDisbursementStatus'])
            : [];
    }

    // فلاتر النماذج - التعامل مع المصفوفات
    $filterPreciseDrilling = [];
    if (isset($_GET['filterPreciseDrilling'])) {
        $filterPreciseDrilling = is_array($_GET['filterPreciseDrilling'])
            ? array_filter($_GET['filterPreciseDrilling'])
            : [];
    }

    $filterExcavation = [];
    if (isset($_GET['filterExcavation'])) {
        $filterExcavation = is_array($_GET['filterExcavation'])
            ? array_filter($_GET['filterExcavation'])
            : [];
    }

    $filterDemolition = [];
    if (isset($_GET['filterDemolition'])) {
        $filterDemolition = is_array($_GET['filterDemolition'])
            ? array_filter($_GET['filterDemolition'])
            : [];
    }

    $filterF1Form = [];
    if (isset($_GET['filterF1Form'])) {
        $filterF1Form = is_array($_GET['filterF1Form'])
            ? array_filter($_GET['filterF1Form'])
            : [];
    }

    $filterAssetsReceipt = [];
    if (isset($_GET['filterAssetsReceipt'])) {
        $filterAssetsReceipt = is_array($_GET['filterAssetsReceipt'])
            ? array_filter($_GET['filterAssetsReceipt'])
            : [];
    }

    $quickFilter = isset($_GET['quickFilter']) ? $_GET['quickFilter'] : '';

    // تعريف الأعمدة - يجب أن تتطابق مع ترتيب الأعمدة في الجدول HTML
    // 0: ★ (is_favorite) - غير قابل للترتيب
    // 1: رقم الأمر
    // 2: نوع الأمر
    // 3: القسم
    // 4: الجهة الحالية
    // 5: الفرع
    // 6: الموقع
    // 7: رقم المستخلص
    // 8: تاريخ التكليف
    // 9: القيمة الفعلية
    // 10: شهادة الإنجاز
    // 11: تاريخ ارفاق الشهادة
    // 12: تأكيد الشهادة
    // 13: تاريخ تأكيد الشهادة
    // 14: حالة الصرف
    // 15: الحالة
    // 16-20: النماذج
    // 21: الإجراءات - غير قابل للترتيب
    $columns = [
        0 => 'wo.is_favorite',
        1 => 'wo.work_order_number',
        2 => 'wot.type_code',
        3 => 'wo.department',
        4 => 'wo.current_entity_id',
        5 => 'b.name',
        6 => 'wo.location',
        7 => 'pe.extract_number',
        8 => 'wo.assignment_date',
        9 => 'wo.actual_value',
        10 => 'cc.status',
        11 => 'cc.certificate_attached_date',
        12 => 'cc.completion_certificate_confirmation',
        13 => 'cc.certificate_confirmed_date',
        14 => 'wo.disbursement_status',
        15 => 'wo.status',
        16 => 'drill.status',
        17 => 'excavation.status',
        18 => 'demolition.status',
        19 => 'f1.status',
        20 => 'assets.status'
    ];

    // عمود الترتيب
    $orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'wo.id';

    // بناء شرط WHERE
    $whereConditions = [];
    $params = [];
    
    // استبعاد الأوامر المكتملة إذا لم يتم طلب عرضها
    if (!$showCompleted) {
        $whereConditions[] = "wo.status != 'completed'";
    }

    // تطبيق الفلاتر الأساسية
    if (!empty($filterDepartment)) {
        $whereConditions[] = "wo.department = ?";
        $params[] = $filterDepartment;
    }

    if (!empty($filterCurrentEntity)) {
        $whereConditions[] = "wo.current_entity_id = ?";
        $params[] = $filterCurrentEntity;
    }

    if (!empty($filterBranch)) {
        $whereConditions[] = "wo.branch_id = ?";
        $params[] = $filterBranch;
    }

    if (!empty($filterDateFrom)) {
        $whereConditions[] = "wo.assignment_date >= ?";
        $params[] = $filterDateFrom;
    }

    if (!empty($filterDateTo)) {
        $whereConditions[] = "wo.assignment_date <= ?";
        $params[] = $filterDateTo;
    }

    // فلتر شهادة الإنجاز (متعدد)
    if (!empty($filterCompletionCertificate) && is_array($filterCompletionCertificate)) {
        $placeholders = implode(',', array_fill(0, count($filterCompletionCertificate), '?'));
        $whereConditions[] = "cc.status IN ($placeholders)";
        $params = array_merge($params, $filterCompletionCertificate);
    }

    // فلتر تأكيد الشهادة (متعدد)
    if (!empty($filterCertificateConfirmation) && is_array($filterCertificateConfirmation)) {
        $placeholders = implode(',', array_fill(0, count($filterCertificateConfirmation), '?'));
        $whereConditions[] = "cc.completion_certificate_confirmation IN ($placeholders)";
        $params = array_merge($params, $filterCertificateConfirmation);
    }

    // فلتر حالة الصرف (متعدد)
    if (!empty($filterDisbursementStatus) && is_array($filterDisbursementStatus)) {
        $placeholders = implode(',', array_fill(0, count($filterDisbursementStatus), '?'));
        $whereConditions[] = "wo.disbursement_status IN ($placeholders)";
        $params = array_merge($params, $filterDisbursementStatus);
    }

    // فلاتر النماذج الذكية
    if (!empty($filterPreciseDrilling) && is_array($filterPreciseDrilling)) {
        $placeholders = implode(',', array_fill(0, count($filterPreciseDrilling), '?'));
        $whereConditions[] = "drill.status IN ($placeholders)";
        $params = array_merge($params, $filterPreciseDrilling);
    }

    if (!empty($filterExcavation) && is_array($filterExcavation)) {
        $placeholders = implode(',', array_fill(0, count($filterExcavation), '?'));
        $whereConditions[] = "excavation.status IN ($placeholders)";
        $params = array_merge($params, $filterExcavation);
    }

    if (!empty($filterDemolition) && is_array($filterDemolition)) {
        $placeholders = implode(',', array_fill(0, count($filterDemolition), '?'));
        $whereConditions[] = "demolition.status IN ($placeholders)";
        $params = array_merge($params, $filterDemolition);
    }

    if (!empty($filterF1Form) && is_array($filterF1Form)) {
        $placeholders = implode(',', array_fill(0, count($filterF1Form), '?'));
        $whereConditions[] = "f1.status IN ($placeholders)";
        $params = array_merge($params, $filterF1Form);
    }

    if (!empty($filterAssetsReceipt) && is_array($filterAssetsReceipt)) {
        $placeholders = implode(',', array_fill(0, count($filterAssetsReceipt), '?'));
        $whereConditions[] = "assets.status IN ($placeholders)";
        $params = array_merge($params, $filterAssetsReceipt);
    }

    // تطبيق الفلاتر السريعة
    if (!empty($quickFilter)) {
        switch ($quickFilter) {
            case 'favorites':
                // المفضلة فقط
                $whereConditions[] = "wo.is_favorite = 1";
                break;

            case 'confirmed_no_extract':
                // شهادة مؤكدة ولم تدخل مستخلص
                $whereConditions[] = "cc.completion_certificate_confirmation = 'confirmed'";
                $whereConditions[] = "pewo.id IS NULL AND frewo.id IS NULL AND ffpewo.id IS NULL";
                break;

            case 'attached_cert_no_extract':
                // شهادة إنجاز مرفقة ولم تدخل مستخلص
                $whereConditions[] = "cc.status = 'attached'";
                $whereConditions[] = "pewo.id IS NULL AND frewo.id IS NULL AND ffpewo.id IS NULL";
                break;

            case 'missing_drilling_scraping':
                // نماذج حفر دقيق أو كشط غير مرفق (فقط التي حالتها not_attached وليس not_applicable)
                $whereConditions[] = "(
                    EXISTS (
                        SELECT 1 FROM work_order_attachments woa_drill
                        WHERE woa_drill.work_order_id = wo.id
                        AND woa_drill.form_type = 'precise_drilling_form'
                        AND woa_drill.status = 'not_attached'
                    )
                    OR
                    EXISTS (
                        SELECT 1 FROM work_order_attachments woa_excavation
                        WHERE woa_excavation.work_order_id = wo.id
                        AND woa_excavation.form_type = 'excavation_form'
                        AND woa_excavation.status = 'not_attached'
                    )
                )";
                break;

            case 'missing_scrap':
                // نماذج تخريد غير مرفق (فقط التي حالتها not_attached وليس not_applicable)
                $whereConditions[] = "EXISTS (
                    SELECT 1 FROM work_order_attachments woa_scrap
                    WHERE woa_scrap.work_order_id = wo.id
                    AND woa_scrap.form_type = 'demolition_form'
                    AND woa_scrap.status = 'not_attached'
                )";
                break;
        }
    }

    // شرط البحث
    if (!empty($searchValue)) {
        $searchConditions = [
            "wo.work_order_number LIKE ?",
            "wot.type_code LIKE ?",
            "b.name LIKE ?",
            "wo.location LIKE ?",
            "pe.extract_number LIKE ?",
            "fre.extract_number LIKE ?",
            "ffpe.extract_number LIKE ?"
        ];
        $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        $searchParam = "%{$searchValue}%";
        for ($i = 0; $i < count($searchConditions); $i++) {
            $params[] = $searchParam;
        }
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // استعلام العد الإجمالي (بدون فلترة)
    $totalQuery = "SELECT COUNT(DISTINCT wo.id) as total FROM work_orders wo";
    if (!$showCompleted) {
        $totalQuery .= " WHERE wo.status != 'completed'";
    }
    $totalStmt = $db->query($totalQuery);
    $totalRecords = $totalStmt->fetch()['total'];
    
    // استعلام العد بعد الفلترة
    $filteredQuery = "
        SELECT COUNT(DISTINCT wo.id) as total
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        LEFT JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
        LEFT JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        LEFT JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        LEFT JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        LEFT JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
        LEFT JOIN work_order_attachments drill ON wo.id = drill.work_order_id AND drill.form_type = 'precise_drilling_form'
        LEFT JOIN work_order_attachments excavation ON wo.id = excavation.work_order_id AND excavation.form_type = 'excavation_form'
        LEFT JOIN work_order_attachments demolition ON wo.id = demolition.work_order_id AND demolition.form_type = 'demolition_form'
        LEFT JOIN work_order_attachments f1 ON wo.id = f1.work_order_id AND f1.form_type = 'f1_form'
        LEFT JOIN work_order_attachments assets ON wo.id = assets.work_order_id AND assets.form_type = 'assets_receipt_form'
        $whereClause
    ";
    
    $filteredStmt = $db->prepare($filteredQuery);
    $filteredStmt->execute($params);
    $filteredRecords = $filteredStmt->fetch()['total'];
    
    // استعلام البيانات الرئيسي
    $dataQuery = "
        SELECT wo.*,
               wot.type_code as work_order_type_code,
               wot.description as work_order_type_description,
               b.name as branch_name,
               b.code as branch_code,
               pe.extract_number as partial_extract_number,
               pe.id as partial_extract_id,
               fre.extract_number as final_regular_extract_number,
               fre.id as final_regular_extract_id,
               ffpe.extract_number as final_for_partial_extract_number,
               ffpe.id as final_for_partial_extract_id,
               cc.id as completion_certificate_id,
               cc.status as completion_certificate_status,
               cc.completion_certificate_confirmation,
               cc.certificate_attached_date,
               cc.certificate_confirmed_date,
               cc.file_path as completion_certificate_file,
               cc.original_filename as completion_certificate_filename,
               -- النماذج المرفقة
               drill.status as precise_drilling_status,
               excavation.status as excavation_status,
               demolition.status as demolition_status,
               f1.status as f1_status,
               assets_receipt.status as assets_receipt_status,
               con.contract_number as contract_number
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        LEFT JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
        LEFT JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        LEFT JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        LEFT JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        LEFT JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
        LEFT JOIN work_order_attachments drill ON wo.id = drill.work_order_id AND drill.form_type = 'precise_drilling_form'
        LEFT JOIN work_order_attachments excavation ON wo.id = excavation.work_order_id AND excavation.form_type = 'excavation_form'
        LEFT JOIN work_order_attachments demolition ON wo.id = demolition.work_order_id AND demolition.form_type = 'demolition_form'
        LEFT JOIN work_order_attachments f1 ON wo.id = f1.work_order_id AND f1.form_type = 'f1_form'
        LEFT JOIN work_order_attachments assets_receipt ON wo.id = assets_receipt.work_order_id AND assets_receipt.form_type = 'assets_receipt_form'
        LEFT JOIN contracts con ON wo.contract_id = con.id
        $whereClause
        GROUP BY wo.id
        ORDER BY $orderColumn $orderDir
        LIMIT $start, $length
    ";
    
    $dataStmt = $db->prepare($dataQuery);
    $dataStmt->execute($params);
    $workOrders = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الجهات الحالية لاستخدامها في البيانات
    $currentEntities = [];
    try {
        $stmt = $db->query("SELECT * FROM current_entities WHERE is_active = 1 ORDER BY name");
        $currentEntities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // الجدول غير موجود، نتجاهل الخطأ
    }
    
    // تحويل البيانات إلى صيغة DataTable
    $data = [];
    foreach ($workOrders as $workOrder) {
        // البحث عن اسم الجهة الحالية
        $currentEntityName = 'غير محدد';
        if (!empty($workOrder['current_entity_id'])) {
            foreach ($currentEntities as $entity) {
                if ($entity['id'] == $workOrder['current_entity_id']) {
                    $currentEntityName = $entity['name'];
                    break;
                }
            }
        }
        
        $data[] = [
            'id' => $workOrder['id'],
            'is_favorite' => $workOrder['is_favorite'] ?? 0,
            'work_order_number' => $workOrder['work_order_number'],
            'work_order_type_code' => $workOrder['work_order_type_code'] ?? 'غير محدد',
            'department' => $workOrder['department'],
            'current_entity_id' => $workOrder['current_entity_id'],
            'current_entity_name' => $currentEntityName,
            'branch_name' => $workOrder['branch_name'] ?? 'غير محدد',
            'location' => $workOrder['location'] ?? '',
            'partial_extract_number' => $workOrder['partial_extract_number'],
            'partial_extract_id' => $workOrder['partial_extract_id'],
            'final_regular_extract_number' => $workOrder['final_regular_extract_number'],
            'final_regular_extract_id' => $workOrder['final_regular_extract_id'],
            'final_for_partial_extract_number' => $workOrder['final_for_partial_extract_number'],
            'final_for_partial_extract_id' => $workOrder['final_for_partial_extract_id'],
            'assignment_date' => $workOrder['assignment_date'] ?? '',
            'actual_value' => $workOrder['actual_value'] ?? 0,
            'completion_certificate_status' => $workOrder['completion_certificate_status'] ?? 'not_attached',
            'certificate_attached_date' => $workOrder['certificate_attached_date'] ?? '',
            'completion_certificate_confirmation' => $workOrder['completion_certificate_confirmation'] ?? 'empty',
            'certificate_confirmed_date' => $workOrder['certificate_confirmed_date'] ?? '',
            'completion_certificate_file' => $workOrder['completion_certificate_file'] ?? '',
            'completion_certificate_filename' => $workOrder['completion_certificate_filename'] ?? '',
            'disbursement_status' => $workOrder['disbursement_status'] ?? 'none',
            'status' => $workOrder['status'],
            // النماذج المرفقة
            'precise_drilling_status' => $workOrder['precise_drilling_status'] ?? 'not_attached',
            'excavation_status' => $workOrder['excavation_status'] ?? 'not_attached',
            'demolition_status' => $workOrder['demolition_status'] ?? 'not_attached',
            'f1_status' => $workOrder['f1_status'] ?? 'not_attached',
            'assets_receipt_status' => $workOrder['assets_receipt_status'] ?? 'not_applicable',
            'contract_number' => $workOrder['contract_number'] ?? ''
        ];
    }
    
    // إرجاع البيانات بصيغة JSON
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data,
        'currentEntities' => $currentEntities
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in get-work-orders-ajax.php: " . $e->getMessage());
    echo json_encode([
        'error' => 'حدث خطأ في قاعدة البيانات',
        'details' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get-work-orders-ajax.php: " . $e->getMessage());
    echo json_encode([
        'error' => 'حدث خطأ أثناء جلب البيانات',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

