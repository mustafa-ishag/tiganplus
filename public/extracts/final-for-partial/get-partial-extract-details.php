<?php
/**
 * جلب تفاصيل المستخلص الجزئي وأوامر العمل المرتبطة
 * Get Partial Extract Details and Related Work Orders
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_view_details')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لعرض تفاصيل المستخلصات']);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
    
    // التحقق من البيانات المطلوبة - يقبل إما partial_extract_id أو id
    $partialExtractId = null;
    if (isset($_GET['partial_extract_id']) && is_numeric($_GET['partial_extract_id'])) {
        $partialExtractId = (int) $_GET['partial_extract_id'];
    } elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $partialExtractId = (int) $_GET['id'];
    } else {
        throw new InvalidArgumentException('معرف المستخلص الجزئي مطلوب');
    }
    
    // التحقق من ما إذا كان هذا للتعديل (إذا تم تمرير معامل edit_mode)
    $isEditMode = isset($_GET['edit_mode']) && $_GET['edit_mode'] === 'true';

    // تسجيل للتشخيص
    error_log("get-partial-extract-details.php: partialExtractId=$partialExtractId, isEditMode=" . ($isEditMode ? 'true' : 'false'));

    // جلب تفاصيل المستخلص الجزئي مع التحقق من الشروط
    if ($isEditMode) {
        // في وضع التعديل، لا نحتاج للتحقق من عدم وجود مستخلص نهائي
        $extractQuery = "
            SELECT pe.*,
                   b.name as branch_name,
                   u.full_name as created_by_name,
                   COUNT(DISTINCT pewo.id) as work_orders_count,
                   COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN pewo.id END) as confirmed_certificates
            FROM partial_extracts pe
            LEFT JOIN branches b ON pe.branch_id = b.id
            LEFT JOIN users u ON pe.created_by = u.id
            LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
            LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
            LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
            WHERE pe.id = ? AND pe.approval_stage IN ('disbursed', 'taif_finance')
            GROUP BY pe.id
            HAVING work_orders_count > 0 AND confirmed_certificates = work_orders_count
        ";
    } else {
        // في وضع الإنشاء، نتحقق من عدم وجود مستخلص نهائي مسبقاً
        $extractQuery = "
            SELECT pe.*,
                   b.name as branch_name,
                   u.full_name as created_by_name,
                   COUNT(DISTINCT pewo.id) as work_orders_count,
                   COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN pewo.id END) as confirmed_certificates
            FROM partial_extracts pe
            LEFT JOIN branches b ON pe.branch_id = b.id
            LEFT JOIN users u ON pe.created_by = u.id
            LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
            LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
            LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
            LEFT JOIN final_for_partial_extracts ffpe ON pe.id = ffpe.related_partial_extract_id
            WHERE pe.id = ? AND pe.approval_stage IN ('disbursed', 'taif_finance') AND ffpe.id IS NULL
            GROUP BY pe.id
            HAVING work_orders_count > 0 AND confirmed_certificates = work_orders_count
        ";
    }
    
    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$partialExtractId]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        // تسجيل للتشخيص
        error_log("get-partial-extract-details.php: Extract not found for ID: $partialExtractId, isEditMode: " . ($isEditMode ? 'true' : 'false'));

        if ($isEditMode) {
            throw new InvalidArgumentException('المستخلص الجزئي غير موجود أو لا يستوفي الشروط المطلوبة (يجب أن يكون في مرحلة مصروف أو مالية الطائف مع تأكيد جميع شهادات الإنجاز)');
        } else {
            throw new InvalidArgumentException('المستخلص الجزئي غير موجود أو لا يستوفي الشروط المطلوبة (يجب أن يكون في مرحلة مصروف أو مالية الطائف مع تأكيد جميع شهادات الإنجاز ولم يتم إنشاء مستخلص نهائي له مسبقاً)');
        }
    }

    // تسجيل للتشخيص
    error_log("get-partial-extract-details.php: Found extract: " . $extract['extract_number']);
    
    // جلب أوامر العمل المرتبطة بالمستخلص الجزئي
    $workOrdersQuery = "
        SELECT pewo.*,
               wo.work_order_number,
               wo.actual_value,
               wo.estimated_value,
               wo.department,
               wo.branch_id,
               wot.type_code as work_order_type_code,
               wot.description as work_order_type_description,
               b.name as branch_name,
               CASE
                   WHEN wo.department = 'connections' THEN 'التوصيلات'
                   WHEN wo.department = 'projects' THEN 'المشاريع'
                   ELSE wo.department
               END as department_name,
               pewo.extract_value as partial_extract_value
        FROM partial_extract_work_orders pewo
        INNER JOIN work_orders wo ON pewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        WHERE pewo.partial_extract_id = ?
        ORDER BY wo.work_order_number
    ";
    
    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$partialExtractId]);
    $workOrders = $stmt->fetchAll();
    
    // تحضير البيانات للإرسال
    $responseData = [
        'id' => $extract['id'],
        'extract_number' => $extract['extract_number'],
        'extract_date' => $extract['extract_date'],
        'total_amount' => $extract['total_amount'],
        'tax_amount' => $extract['tax_amount'],
        'net_amount' => $extract['net_amount'],
        'approval_stage' => $extract['approval_stage'],
        'created_at' => date('Y-m-d', strtotime($extract['created_at'])),
        'branch_name' => $extract['branch_name'],
        'created_by_name' => $extract['created_by_name'],
        'work_orders_count' => $extract['work_orders_count'],
        'work_orders' => []
    ];
    
    // إضافة تفاصيل أوامر العمل
    foreach ($workOrders as $wo) {
        $responseData['work_orders'][] = [
            'work_order_id' => $wo['work_order_id'],
            'work_order_number' => $wo['work_order_number'],
            'work_order_type_code' => $wo['work_order_type_code'],
            'work_order_type_description' => $wo['work_order_type_description'],
            'actual_value' => $wo['actual_value'],
            'estimated_value' => $wo['estimated_value'],
            'partial_extract_value' => $wo['partial_extract_value'],
            'remaining_value' => ($wo['actual_value'] ?: $wo['estimated_value']) - $wo['partial_extract_value'],
            'completion_date' => $wo['completion_date'],
            'department' => $wo['department'],
            'department_name' => $wo['department_name'],
            'branch_id' => $wo['branch_id'],
            'branch_name' => $wo['branch_name']
        ];
    }
    
    // تسجيل للتشخيص
    error_log("get-partial-extract-details.php: Returning data: " . json_encode($responseData));

    echo json_encode([
        'success' => true,
        'message' => 'تم جلب تفاصيل المستخلص الجزئي بنجاح',
        'data' => $responseData
    ], JSON_UNESCAPED_UNICODE);
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
