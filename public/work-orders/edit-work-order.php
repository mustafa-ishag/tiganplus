<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحقق من الصلاحيات
    if (!hasPermission('work_orders_edit')) {
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل أوامر العمل'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();
    $workOrderId = (int) ($_GET['id'] ?? 0);

    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // جلب تفاصيل أمر العمل
    $stmt = $db->prepare("
        SELECT 
            wo.*,
            b.name as branch_name,
            b.code as branch_code,
            wot.type_code,
            wot.description as work_order_type_description
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE wo.id = ?
    ");
    
    $stmt->execute([$workOrderId]);
    $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$workOrder) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // جلب الفروع النشطة
    $branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب أنواع أوامر العمل النشطة
    $workOrderTypes = $db->query("SELECT * FROM work_order_types WHERE status = 'active' ORDER BY type_code")->fetchAll(PDO::FETCH_ASSOC);

    // إنشاء HTML لنموذج التعديل
    $html = '<form id="editWorkOrderForm">
        <input type="hidden" name="id" value="' . $workOrder['id'] . '">
        <div class="row">
            <!-- رقم أمر العمل -->
            <div class="col-md-6 mb-3">
                <label for="edit_work_order_number" class="form-label">
                    رقم أمر العمل <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="edit_work_order_number" name="work_order_number" 
                       value="' . htmlspecialchars($workOrder['work_order_number']) . '" required>
            </div>

            <!-- نوع أمر العمل -->
            <div class="col-md-6 mb-3">
                <label for="edit_work_order_type_id" class="form-label">
                    نوع أمر العمل <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="edit_work_order_type_id" name="work_order_type_id" required>
                    <option value="">اختر نوع أمر العمل</option>';
    
    foreach ($workOrderTypes as $type) {
        $selected = ($type['id'] == $workOrder['work_order_type_id']) ? 'selected' : '';
        $html .= '<option value="' . $type['id'] . '" ' . $selected . '>
                    ' . htmlspecialchars($type['type_code']) . ' - ' . htmlspecialchars($type['description']) . '
                  </option>';
    }
    
    $html .= '</select>
            </div>

            <!-- القسم -->
            <div class="col-md-6 mb-3">
                <label for="edit_department" class="form-label">
                    القسم <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="edit_department" name="department" required>
                    <option value="">اختر القسم</option>
                    <option value="connections" ' . ($workOrder['department'] == 'connections' ? 'selected' : '') . '>التوصيلات</option>
                    <option value="projects" ' . ($workOrder['department'] == 'projects' ? 'selected' : '') . '>المشاريع</option>
                </select>
            </div>

            <!-- الفرع -->
            <div class="col-md-6 mb-3">
                <label for="edit_branch_id" class="form-label">
                    الفرع <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="edit_branch_id" name="branch_id" required>
                    <option value="">اختر الفرع</option>';
    
    foreach ($branches as $branch) {
        $selected = ($branch['id'] == $workOrder['branch_id']) ? 'selected' : '';
        $html .= '<option value="' . $branch['id'] . '" ' . $selected . '>
                    ' . htmlspecialchars($branch['name']) . ' (' . htmlspecialchars($branch['code']) . ')
                  </option>';
    }
    
    $html .= '</select>
            </div>

            <!-- الموقع -->
            <div class="col-md-6 mb-3">
                <label for="edit_location" class="form-label">الموقع</label>
                <input type="text" class="form-control" id="edit_location" name="location"
                       value="' . htmlspecialchars($workOrder['location'] ?? '') . '"
                       placeholder="أدخل موقع تنفيذ أمر العمل" maxlength="255">
                <div class="form-text">
                    <small class="text-muted">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        موقع تنفيذ أمر العمل (اختياري)
                    </small>
                </div>
            </div>

            <!-- تاريخ التكليف -->
            <div class="col-md-6 mb-3">
                <label for="edit_assignment_date" class="form-label">تاريخ التكليف</label>
                <input type="date" class="form-control" id="edit_assignment_date" name="assignment_date" 
                       value="' . ($workOrder['assignment_date'] ?? '') . '">
            </div>

            <!-- تاريخ الاستلام -->
            <div class="col-md-6 mb-3">
                <label for="edit_receipt_date" class="form-label">تاريخ الاستلام</label>
                <input type="date" class="form-control" id="edit_receipt_date" name="receipt_date" 
                       value="' . ($workOrder['receipt_date'] ?? '') . '">
            </div>

            <!-- القيمة المقدرة -->
            <div class="col-md-6 mb-3">
                <label for="edit_estimated_value" class="form-label">القيمة المقدرة (ريال)</label>
                <input type="number" class="form-control" id="edit_estimated_value" name="estimated_value" 
                       step="0.01" min="0" value="' . $workOrder['estimated_value'] . '">
            </div>

            <!-- القيمة الفعلية -->
            <div class="col-md-6 mb-3">
                <label for="edit_actual_value" class="form-label">القيمة الفعلية (ريال)</label>
                <input type="number" class="form-control" id="edit_actual_value" name="actual_value" 
                       step="0.01" min="0" value="' . $workOrder['actual_value'] . '">
            </div>

            <!-- حالة الصرف -->
            <div class="col-md-6 mb-3">
                <label for="edit_disbursement_status" class="form-label">حالة الصرف</label>
                <select class="form-select" id="edit_disbursement_status" name="disbursement_status">
                    <option value="none" ' . ($workOrder['disbursement_status'] == 'none' ? 'selected' : '') . '>لا يوجد</option>
                    <option value="pending_disbursement" ' . ($workOrder['disbursement_status'] == 'pending_disbursement' ? 'selected' : '') . '>في انتظار الصرف</option>
                    <option value="disbursement" ' . ($workOrder['disbursement_status'] == 'disbursement' ? 'selected' : '') . '>تم الصرف</option>
                    <option value="completed" ' . ($workOrder['disbursement_status'] == 'completed' ? 'selected' : '') . '>مكتمل</option>
                    <option value="return" ' . ($workOrder['disbursement_status'] == 'return' ? 'selected' : '') . '>مرتجع</option>
                    <option value="partial_disbursement" ' . ($workOrder['disbursement_status'] == 'partial_disbursement' ? 'selected' : '') . '>صرف جزئي</option>
                    <option value="cancelled_disbursement" ' . ($workOrder['disbursement_status'] == 'cancelled_disbursement' ? 'selected' : '') . '>صرف ملغي</option>
                </select>
            </div>

            <!-- الحالة -->
            <div class="col-md-6 mb-3">
                <label for="edit_status" class="form-label">حالة أمر العمل</label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="active" ' . ($workOrder['status'] == 'active' ? 'selected' : '') . '>نشط</option>
                    <option value="inactive" ' . ($workOrder['status'] == 'inactive' ? 'selected' : '') . '>غير نشط</option>
                    <option value="completed" ' . ($workOrder['status'] == 'completed' ? 'selected' : '') . '>مكتمل</option>
                    <option value="cancelled" ' . ($workOrder['status'] == 'cancelled' ? 'selected' : '') . '>ملغي</option>
                </select>
            </div>

            <!-- الملاحظات -->
            <div class="col-12 mb-3">
                <label for="edit_notes" class="form-label">الملاحظات</label>
                <textarea class="form-control" id="edit_notes" name="notes" rows="3" 
                          placeholder="أدخل أي ملاحظات إضافية حول أمر العمل">' . htmlspecialchars($workOrder['notes'] ?? '') . '</textarea>
            </div>
        </div>
    </form>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('خطأ في تحميل نموذج تعديل أمر العمل: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى'
    ], JSON_UNESCAPED_UNICODE);
}
?>
