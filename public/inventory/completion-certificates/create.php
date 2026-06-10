<?php
/**
 * صفحة إنشاء شهادة إنجاز جديدة
 * Create New Completion Certificate Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إنشاء شهادة إنجاز جديدة';
$currentPage = 'completion-certificates';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'شهادات الإنجاز', 'url' => 'inventory/completion-certificates/index.php'],
    ['title' => 'إنشاء شهادة جديدة', 'url' => 'inventory/completion-certificates/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_certificates_create')) {
    header('Location: ' . path('inventory/completion-certificates/index.php'));
    exit();
}

$error = '';
$success = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من البيانات الأساسية
        $workOrderId = (int)($_POST['work_order_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $certificateDate = $_POST['certificate_date'] ?? '';
        $location = trim($_POST['location'] ?? '') ?: null;

        if (!$workOrderId || !$title || !$certificateDate) {
            throw new Exception('يرجى ملء جميع الحقول المطلوبة');
        }
        
        $db = getDB();
        
        // التحقق من أمر العمل
        $workOrderStmt = $db->prepare("SELECT * FROM work_orders WHERE id = ? AND status = 'active'");
        $workOrderStmt->execute([$workOrderId]);
        $workOrder = $workOrderStmt->fetch();
        
        if (!$workOrder) {
            throw new Exception('أمر العمل غير صحيح أو غير نشط');
        }
        
        // التحقق من عدم وجود شهادة بنفس التاريخ لنفس أمر العمل
        $duplicateStmt = $db->prepare("SELECT id FROM completion_certificates WHERE work_order_id = ? AND certificate_date = ?");
        $duplicateStmt->execute([$workOrderId, $certificateDate]);
        if ($duplicateStmt->fetch()) {
            throw new Exception('يوجد شهادة إنجاز لنفس أمر العمل في نفس التاريخ');
        }
        
        // معالجة المواد
        $materials = [];
        if (isset($_POST['materials']) && is_array($_POST['materials'])) {
            foreach ($_POST['materials'] as $materialData) {
                if (!empty($materialData['material_id'])) {
                    $materials[] = [
                        'material_id' => (int)$materialData['material_id'],
                        'estimated_quantity' => (float)($materialData['estimated_quantity'] ?? 0),
                        'actual_quantity' => (float)($materialData['actual_quantity'] ?? 0),
                        'dispensed_quantity' => (float)($materialData['dispensed_quantity'] ?? 0),
                        'returned_quantity' => (float)($materialData['returned_quantity'] ?? 0),

                        'notes' => trim($materialData['notes'] ?? '')
                    ];
                }
            }
        }
        
        // معالجة الأعمال
        $works = [];
        if (isset($_POST['works']) && is_array($_POST['works'])) {
            foreach ($_POST['works'] as $workData) {
                if (!empty($workData['work_item_id']) && !empty($workData['quantity'])) {
                    $works[] = [
                        'work_item_id' => (int)$workData['work_item_id'],
                        'quantity' => (float)$workData['quantity'],
                        'completion_percentage' => (float)($workData['completion_percentage'] ?? 100),
                        'notes' => trim($workData['notes'] ?? '')
                    ];
                }
            }
        }
        
        if (empty($materials) && empty($works)) {
            throw new Exception('يجب إضافة مواد أو أعمال على الأقل');
        }
        
        // بدء المعاملة
        // التحقق من وجود شهادة إنجاز موجودة لنفس أمر العمل
        $existingCertStmt = $db->prepare("SELECT id FROM completion_certificates WHERE work_order_id = ?");
        $existingCertStmt->execute([$workOrderId]);
        $existingCert = $existingCertStmt->fetch();

        if ($existingCert) {
            $error = "يوجد شهادة إنجاز موجودة بالفعل لهذا أمر العمل. لا يمكن إنشاء أكثر من شهادة إنجاز واحدة لكل أمر عمل.";
        } else {
            $db->beginTransaction();

            try {
                // إدراج الشهادة الأساسية
                $insertCertStmt = $db->prepare("
                    INSERT INTO completion_certificates (
                        work_order_id, certificate_date, title, description, created_by
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $insertCertStmt->execute([
                    $workOrderId,
                    $certificateDate,
                    $title,
                    $description,
                    $_SESSION['user_id']
                ]);

                $certificateId = $db->lastInsertId();

                // تحديث موقع أمر العمل إذا تم تعديله
                if ($location !== null) {
                    $updateLocationStmt = $db->prepare("
                        UPDATE work_orders
                        SET location = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateLocationStmt->execute([$location, $workOrderId]);
                }

            // إدراج المواد
            if (!empty($materials)) {
                $materialStmt = $db->prepare("
                    INSERT INTO completion_certificate_materials (
                        certificate_id, material_id, material_code, material_description,
                        material_group, unit, estimated_quantity, actual_quantity,
                        dispensed_quantity, returned_quantity, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($materials as $material) {
                    // جلب بيانات المادة
                    $materialInfoStmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
                    $materialInfoStmt->execute([$material['material_id']]);
                    $materialInfo = $materialInfoStmt->fetch();

                    if ($materialInfo) {
                        $materialStmt->execute([
                            $certificateId,
                            $material['material_id'],
                            $materialInfo['item_number'],
                            $materialInfo['description'],
                            $materialInfo['group_number'] ?? '',
                            $materialInfo['unit'],
                            $material['estimated_quantity'],
                            $material['actual_quantity'],
                            $material['dispensed_quantity'],
                            $material['returned_quantity'],
                            $material['notes']
                        ]);
                    }
                }
            }
            
            // إدراج الأعمال
            if (!empty($works)) {
                $workStmt = $db->prepare("
                    INSERT INTO completion_certificate_works (
                        certificate_id, work_item_id, work_item_code, work_description,
                        work_category, unit, estimated_quantity, quantity, completion_percentage, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($works as $work) {
                    // جلب بيانات العمل
                    $workInfoStmt = $db->prepare("SELECT * FROM work_items WHERE id = ?");
                    $workInfoStmt->execute([$work['work_item_id']]);
                    $workInfo = $workInfoStmt->fetch();

                    if ($workInfo) {
                        $workStmt->execute([
                            $certificateId,
                            $work['work_item_id'],
                            $workInfo['item_number'],
                            $workInfo['description'],
                            $workInfo['category'] ?? '',
                            $workInfo['unit'],
                            $work['estimated_quantity'] ?? $work['quantity'], // المقايسة
                            $work['quantity'],
                            $work['completion_percentage'],
                            $work['notes']
                        ]);
                    } else {
                        // إذا لم يتم العثور على بيانات العمل، سجل خطأ
                        error_log("Work item not found for ID: " . $work['work_item_id']);
                    }
                }
            }
            
            // لا حاجة لتحديث الإجماليات المالية
            
            $db->commit();
            
            $success = 'تم إنشاء شهادة الإنجاز بنجاح';
            
            // إعادة توجيه إلى صفحة العرض
            header('Location: view.php?id=' . $certificateId);
            exit();
            
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $db = getDB();
    
    // جلب أوامر العمل النشطة مع كود نوع أمر العمل
    $workOrders = $db->query("
        SELECT wo.*,
               b.name as branch_name,
               ce.name as current_entity_name,
               wot.type_code as work_order_type_code,
               wot.description as work_order_type_description
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE wo.status = 'active'
        ORDER BY wo.work_order_number DESC
    ")->fetchAll();
    
    // جلب المواد النشطة
    $materials = $db->query("
        SELECT * FROM materials 
        WHERE is_active = 1 
        ORDER BY item_number
    ")->fetchAll();
    
    // جلب بنود الأعمال
    $workItems = $db->query("
        SELECT * FROM work_items 
        ORDER BY item_number
    ")->fetchAll();
    
} catch (Exception $e) {
    $error = 'خطأ في جلب البيانات: ' . $e->getMessage();
    $workOrders = [];
    $materials = [];
    $workItems = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- نموذج إنشاء الشهادة -->
<form method="POST" id="createCertificateForm">
    <!-- المعلومات الأساسية -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle me-2"></i>
                المعلومات الأساسية
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="work_order_id" class="form-label">أمر العمل <span class="text-danger">*</span></label>
                    <div class="work-order-search-container position-relative">
                        <input type="text" class="form-control" id="work_order_search"
                               placeholder="ابحث عن أمر العمل..." autocomplete="off">
                        <select name="work_order_id" id="work_order_id" class="form-select d-none" required onchange="handleWorkOrderChange(this)">
                            <option value="">اختر أمر العمل</option>
                            <?php foreach ($workOrders as $wo): ?>
                            <option value="<?= $wo['id'] ?>"
                                    data-branch="<?= htmlspecialchars($wo['branch_name']) ?>"
                                    data-entity="<?= htmlspecialchars($wo['current_entity_name']) ?>"
                                    data-department="<?= $wo['department'] ?>"
                                    data-number="<?= htmlspecialchars($wo['work_order_number']) ?>"
                                    data-type-code="<?= htmlspecialchars($wo['work_order_type_code']) ?>"
                                    data-location="<?= htmlspecialchars($wo['location'] ?? '') ?>">
                                <?= htmlspecialchars($wo['work_order_number']) ?> (<?= htmlspecialchars($wo['work_order_type_code']) ?>) - <?= htmlspecialchars($wo['branch_name']) ?>
                                <?php if (!empty($wo['location'])): ?>
                                    - 📍 <?= htmlspecialchars($wo['location']) ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="work_order_suggestions" class="custom-dropdown"></div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="certificate_date" class="form-label">تاريخ الشهادة <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="certificate_date" name="certificate_date"
                           value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">عنوان الشهادة <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       placeholder="مثال: شهادة إنجاز أعمال التوصيلات" required>
            </div>

            <div class="mb-3">
                <label for="location" class="form-label">الموقع</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-map-marker-alt text-primary"></i>
                    </span>
                    <input type="text" class="form-control" id="location" name="location"
                           placeholder="أدخل موقع تنفيذ أمر العمل"
                           maxlength="255">
                </div>
                <div class="form-text">
                    <i class="fas fa-info-circle me-1"></i>
                    سيتم تحديث موقع أمر العمل المرتبط بهذه الشهادة
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">وصف الأعمال المنجزة</label>
                <textarea class="form-control" id="description" name="description" rows="3"
                          placeholder="وصف تفصيلي للأعمال التي تم إنجازها"></textarea>
            </div>
        </div>
    </div>

    <!-- قسم المواد -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">
                    <i class="fas fa-boxes me-2"></i>
                    المواد المستخدمة
                </h5>
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    ملاحظة: عمودي "الصرف" و "الإرجاع" يتم حسابهما تلقائياً بناءً على الفرق بين المقايسة والطبيعة
                </small>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-info" onclick="updateMaterialsFromWorkOrder()" id="updateMaterialsBtn" disabled title="يرجى اختيار أمر العمل أولاً">
                    <i class="fas fa-sync-alt me-1"></i>
                    تحديث من طلبات الصرف
                </button>
                <button type="button" class="btn btn-sm btn-primary" onclick="addMaterialRow()">
                    <i class="fas fa-plus me-1"></i>
                    إضافة مادة
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 materials-table">
                    <thead class="table-primary">
                        <tr>
                            <th style="width: 120px;">رقم البند</th>
                            <th style="width: 100px;">رمز المجموعة</th>
                            <th style="width: 200px;">وصف المادة</th>
                            <th style="width: 80px;">المقايسة</th>
                            <th style="width: 80px;">الطبيعة</th>
                            <th style="width: 80px;">صرف <small class="text-muted">(تلقائي)</small></th>
                            <th style="width: 80px;">إرجاع <small class="text-muted">(تلقائي)</small></th>

                            <th style="width: 80px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="materialsTableBody">
                        <tr id="noMaterialsRow">
                            <td colspan="10" class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                اضغط على "إضافة مادة" لبدء إضافة المواد المستخدمة.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- قسم الأعمال -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-tools me-2"></i>
                الأعمال المنجزة
            </h5>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-secondary" onclick="autoGenerateWorkItems()" id="autoGenerateBtn" disabled title="يرجى إضافة المواد المستخدمة أولاً">
                    <i class="fas fa-magic me-1"></i>
                    توليد تلقائي من المواد المستخدمة
                </button>
                <button type="button" class="btn btn-sm btn-success" onclick="addWorkRow()">
                    <i class="fas fa-plus me-1"></i>
                    إضافة عمل يدوي
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 works-table">
                    <thead class="table-success">
                        <tr>
                            <th style="width: 120px;">رقم البند</th>
                            <th style="width: 250px;">وصف العمل</th>
                            <th style="width: 80px;">الوحدة</th>
                            <th style="width: 80px;">المقايسة</th>
                            <th style="width: 80px;">الكمية</th>
                            <th style="width: 80px;">نسبة الإنجاز</th>
                            <th style="width: 80px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="worksTableBody">
                        <tr id="noWorksRow">
                            <td colspan="9" class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                اضغط على "إضافة عمل" لبدء إضافة الأعمال المنجزة.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- البحث السريع -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-search me-1"></i>
                        البحث السريع في المواد
                    </h6>
                </div>
                <div class="card-body">
                    <input type="text" class="form-control mb-3" id="material-search"
                           placeholder="ابحث عن مادة برقم البند أو الوصف...">

                    <div id="material-suggestions" class="list-group" style="max-height: 300px; overflow-y: auto;">
                        <!-- سيتم عرض اقتراحات المواد هنا -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-search me-1"></i>
                        البحث السريع في بنود الأعمال
                    </h6>
                </div>
                <div class="card-body">
                    <input type="text" class="form-control mb-3" id="work-search"
                           placeholder="ابحث عن بند عمل برقم البند أو الوصف...">

                    <div id="work-suggestions" class="list-group" style="max-height: 300px; overflow-y: auto;">
                        <!-- سيتم عرض اقتراحات بنود الأعمال هنا -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ملخص القيم -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-calculator me-2"></i>
                ملخص القيم
            </h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4 mb-3">
                    <div class="border rounded p-3">
                        <small class="text-muted">عدد المواد</small>
                        <div class="h5 mb-0 text-info" id="totalMaterialsValue">0 مادة</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="border rounded p-3">
                        <small class="text-muted">عدد الأعمال</small>
                        <div class="h5 mb-0 text-success" id="totalWorksValue">0 عمل</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="border rounded p-3">
                        <small class="text-muted">إجمالي العناصر</small>
                        <div class="h5 mb-0 text-primary" id="totalCertificateValue">0 عنصر</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ملخص الإجماليات -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-calculator me-2"></i>
                ملخص الإجماليات
            </h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light">
                        <i class="fas fa-boxes fa-2x text-info mb-2"></i>
                        <h6 class="text-muted">عدد المواد</h6>
                        <h4 class="text-info mb-0" id="materialsCount">0</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light">
                        <i class="fas fa-tools fa-2x text-success mb-2"></i>
                        <h6 class="text-muted">عدد الأعمال</h6>
                        <h4 class="text-success mb-0" id="worksCount">0</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <!-- مساحة فارغة -->
                </div>
            </div>
        </div>
    </div>

    <!-- أزرار الحفظ -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    العودة للقائمة
                </a>
                <div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>
                        حفظ الشهادة
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
/* تحسينات البحث السريع */
.work-order-search-container,
.material-select-container,
.work-select-container {
    position: relative !important;
}

.custom-dropdown {
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1050 !important;
    background-color: white !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    max-height: 300px !important;
    overflow-y: auto !important;
    margin-top: 2px !important;
    display: none;
}

.custom-dropdown.show {
    display: block !important;
}

.dropdown-item-custom {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s;
}

.dropdown-item-custom:hover {
    background-color: #f8f9fa;
}

.dropdown-item-custom:last-child {
    border-bottom: none;
}

.dropdown-item-custom .item-number {
    font-weight: 600;
    color: #0d6efd;
}

.dropdown-item-custom .item-description {
    font-size: 0.9em;
    color: #6c757d;
    margin-top: 2px;
}

/* إصلاح مشاكل الجدول */
.table td {
    position: relative;
    overflow: visible !important;
}

.table {
    overflow: visible !important;
}

.table-responsive {
    overflow: visible !important;
}

.materials-table td:first-child,
.works-table td:first-child {
    overflow: visible !important;
    position: relative;
}

/* تحسين مظهر حقول البحث */
.material-search-input,
.work-search-input {
    border: 2px solid #e9ecef;
    transition: border-color 0.3s;
}

.material-search-input:focus,
.work-search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.selected-item {
    background-color: #e7f3ff;
    border-color: #0d6efd;
    color: #0d6efd;
    font-weight: 600;
}
</style>

<script>
// بيانات المواد وبنود الأعمال
const materialsData = <?= json_encode($materials) ?>;
const workItemsData = <?= json_encode($workItems) ?>;
const workOrdersData = <?= json_encode($workOrders) ?>;

let materialRowCounter = 0;
let workRowCounter = 0;

// تهيئة البحث السريع لأوامر العمل
document.addEventListener('DOMContentLoaded', function() {
    initializeWorkOrderSearch();
    initializeMaterialSearch();
    initializeWorkSearch();
});

// البحث في أوامر العمل
function initializeWorkOrderSearch() {
    const searchInput = document.getElementById('work_order_search');
    const selectElement = document.getElementById('work_order_id');
    const suggestionsContainer = document.getElementById('work_order_suggestions');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        if (searchTerm.length < 1) {
            suggestionsContainer.innerHTML = '';
            suggestionsContainer.classList.remove('show');
            return;
        }

        const filteredOrders = workOrdersData.filter(order =>
            order.work_order_number.toLowerCase().includes(searchTerm) ||
            (order.branch_name && order.branch_name.toLowerCase().includes(searchTerm)) ||
            (order.work_order_type_code && order.work_order_type_code.toLowerCase().includes(searchTerm)) ||
            (order.location && order.location.toLowerCase().includes(searchTerm))
        ).slice(0, 10);

        if (filteredOrders.length > 0) {
            suggestionsContainer.innerHTML = filteredOrders.map(order => `
                <div class="dropdown-item-custom" onclick="selectWorkOrder(${order.id}, '${order.work_order_number}', '${order.branch_name || ''}', '${order.work_order_type_code || ''}', '${order.location || ''}')">
                    <div class="item-number">${order.work_order_number} (${order.work_order_type_code || 'غير محدد'})</div>
                    <div class="item-description">${order.branch_name || ''} - ${order.department || ''}${order.location ? ' - 📍 ' + order.location : ''}</div>
                </div>
            `).join('');
            suggestionsContainer.classList.add('show');
        } else {
            suggestionsContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
            suggestionsContainer.classList.add('show');
        }
    });

    // إخفاء القائمة عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.classList.remove('show');
        }
    });
}

function selectWorkOrder(id, number, branch, typeCode, location) {
    // إعادة تعيين متغير التنبيه
    alertShown = false;

    // إعادة تمكين زر الحفظ
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-save me-2"></i>حفظ شهادة الإنجاز';
        submitButton.classList.remove('btn-secondary');
        submitButton.classList.add('btn-primary');
    }

    const searchInput = document.getElementById('work_order_search');
    const selectElement = document.getElementById('work_order_id');
    const locationInput = document.getElementById('location');
    const suggestionsContainer = document.getElementById('work_order_suggestions');

    searchInput.value = `${number} (${typeCode || 'غير محدد'}) - ${branch}`;
    searchInput.classList.add('selected-item');
    selectElement.value = id;

    // تحديث حقل الموقع
    if (locationInput) {
        locationInput.value = location || '';
    }

    suggestionsContainer.classList.remove('show');

    // جلب المواد من طلبات الصرف المعتمدة
    loadWorkOrderMaterials(id);

    // تفعيل زر تحديث المواد
    const updateMaterialsBtn = document.getElementById('updateMaterialsBtn');
    if (updateMaterialsBtn) {
        updateMaterialsBtn.disabled = false;
        updateMaterialsBtn.title = 'تحديث المواد من طلبات الصرف المعتمدة';
    }

    // تشغيل حدث التغيير
    selectElement.dispatchEvent(new Event('change'));
}

// البحث السريع في المواد (الشريط الجانبي)
function initializeMaterialSearch() {
    const materialSearch = document.getElementById('material-search');
    const materialSuggestions = document.getElementById('material-suggestions');

    materialSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        if (searchTerm.length < 2) {
            materialSuggestions.innerHTML = '';
            return;
        }

        const filteredMaterials = materialsData.filter(material =>
            material.item_number.toLowerCase().includes(searchTerm) ||
            material.description.toLowerCase().includes(searchTerm)
        ).slice(0, 10);

        materialSuggestions.innerHTML = filteredMaterials.map(material => `
            <a href="#" class="list-group-item list-group-item-action"
               onclick="selectMaterialFromSidebar(${material.id}); return false;">
                <strong>${material.item_number}</strong><br>
                <small>${material.description}</small><br>
                <small class="text-muted">المجموعة: ${material.group_number || 'غير محدد'}</small>
            </a>
        `).join('');
    });
}

// البحث السريع في بنود الأعمال (الشريط الجانبي)
function initializeWorkSearch() {
    const workSearch = document.getElementById('work-search');
    const workSuggestions = document.getElementById('work-suggestions');

    workSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        if (searchTerm.length < 2) {
            workSuggestions.innerHTML = '';
            return;
        }

        const filteredWorks = workItemsData.filter(work =>
            work.item_number.toLowerCase().includes(searchTerm) ||
            work.description.toLowerCase().includes(searchTerm)
        ).slice(0, 10);

        workSuggestions.innerHTML = filteredWorks.map(work => `
            <a href="#" class="list-group-item list-group-item-action"
               onclick="selectWorkFromSidebar(${work.id}); return false;">
                <strong>${work.item_number}</strong><br>
                <small>${work.description}</small><br>

            </a>
        `).join('');
    });
}

// اختيار مادة من الشريط الجانبي
function selectMaterialFromSidebar(materialId) {
    // إضافة صف جديد إذا لم يكن هناك صفوف فارغة
    const emptyRows = document.querySelectorAll('select[name*="material_id"] option:checked[value=""]');
    if (emptyRows.length === 0) {
        addMaterialRow();
    }

    // تحديد المادة في آخر صف
    const lastRow = document.querySelector('#materialsTableBody tr:last-child');
    if (lastRow) {
        const select = lastRow.querySelector('select[name*="material_id"]');
        const searchInput = lastRow.querySelector('.material-search-input');

        if (select && searchInput) {
            select.value = materialId;
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                searchInput.value = selectedOption.dataset.code;
                searchInput.classList.add('selected-item');
            }
            select.dispatchEvent(new Event('change'));
        }
    }

    // مسح البحث
    document.getElementById('material-search').value = '';
    document.getElementById('material-suggestions').innerHTML = '';
}

// اختيار بند عمل من الشريط الجانبي
function selectWorkFromSidebar(workId) {
    // إضافة صف جديد إذا لم يكن هناك صفوف فارغة
    const emptyRows = document.querySelectorAll('select[name*="work_item_id"] option:checked[value=""]');
    if (emptyRows.length === 0) {
        addWorkRow();
    }

    // تحديد العمل في آخر صف
    const lastRow = document.querySelector('#worksTableBody tr:last-child');
    if (lastRow) {
        const select = lastRow.querySelector('select[name*="work_item_id"]');
        const searchInput = lastRow.querySelector('.work-search-input');

        if (select && searchInput) {
            select.value = workId;
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                searchInput.value = selectedOption.dataset.code;
                searchInput.classList.add('selected-item');
            }
            select.dispatchEvent(new Event('change'));
        }
    }

    // مسح البحث
    document.getElementById('work-search').value = '';
    document.getElementById('work-suggestions').innerHTML = '';
}

// تحديث المواد من طلبات الصرف مع رسالة تأكيد
function updateMaterialsFromWorkOrder() {
    const workOrderId = document.getElementById('work_order_id').value;

    if (!workOrderId) {
        Swal.fire({
            title: 'تنبيه',
            text: 'يرجى اختيار أمر العمل أولاً',
            icon: 'warning',
            confirmButtonText: 'موافق'
        });
        return;
    }

    // جلب المواد الجديدة أولاً لعرضها في رسالة التأكيد
    fetch(`get-work-order-materials-simple.php?work_order_id=${workOrderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.materials && data.materials.length > 0) {
                // إنشاء قائمة المواد للعرض
                const materialsList = data.materials.map(material =>
                    `• ${material.item_number} - ${material.description} (${material.estimated_quantity} ${material.unit})`
                ).join('\n');

                // عرض رسالة التأكيد مع قائمة المواد
                Swal.fire({
                    title: 'تحديث المواد من طلبات الصرف',
                    html: `
                        <div class="text-start">
                            <p><strong>سيتم إضافة/تحديث المواد التالية:</strong></p>
                            <div class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                                <pre style="white-space: pre-wrap; font-size: 0.9em;">${materialsList}</pre>
                            </div>
                            <p class="mt-3 text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>تنبيه:</strong> سيتم استبدال المواد الحالية بالمواد من طلبات الصرف المعتمدة.
                            </p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، حدث المواد',
                    cancelButtonText: 'إلغاء',
                    width: '600px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // تنفيذ التحديث
                        performMaterialsUpdate(data.materials, data.work_order, data.summary);
                    }
                });
            } else {
                Swal.fire({
                    title: 'لا توجد مواد',
                    text: 'لا توجد مواد معتمدة في طلبات الصرف لهذا أمر العمل',
                    icon: 'info',
                    confirmButtonText: 'موافق'
                });
            }
        })
        .catch(error => {
            console.error('خطأ في جلب المواد:', error);
            Swal.fire({
                title: 'خطأ',
                text: 'حدث خطأ أثناء جلب المواد من أمر العمل',
                icon: 'error',
                confirmButtonText: 'موافق'
            });
        });
}

// تنفيذ تحديث المواد
function performMaterialsUpdate(materials, workOrder, summary) {
    populateMaterialsFromWorkOrder(materials, workOrder, summary);
    showWorkOrderInfo(workOrder, [], summary);

    Swal.fire({
        title: 'تم التحديث بنجاح!',
        text: `تم تحديث ${materials.length} مادة من طلبات الصرف`,
        icon: 'success',
        confirmButtonText: 'موافق'
    });
}

// جلب المواد من طلبات الصرف المعتمدة لأمر العمل
function loadWorkOrderMaterials(workOrderId) {
    if (!workOrderId) return;

    // إظهار مؤشر التحميل
    showLoadingIndicator();

    fetch(`get-work-order-materials-simple.php?work_order_id=${workOrderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoadingIndicator();

            if (data.success) {
                // التحقق من وجود شهادة إنجاز موجودة
                checkExistingCertificate(data.work_order.id);

                populateMaterialsFromWorkOrder(data.materials, data.work_order, data.summary);
                showWorkOrderInfo(data.work_order, data.requests, data.summary);
            } else {
                console.error('خطأ في جلب المواد:', data.error);
                showAlert('تحذير: ' + (data.error || 'لم يتم العثور على مواد معتمدة لهذا أمر العمل'), 'warning');
            }
        })
        .catch(error => {
            hideLoadingIndicator();
            console.error('خطأ في الاتصال:', error);
            showAlert('خطأ في جلب المواد من أمر العمل', 'error');
        });
}

// تعبئة المواد من أمر العمل
function populateMaterialsFromWorkOrder(materials, workOrder, summary) {
    if (!materials || materials.length === 0) {
        showAlert('لا توجد مواد معتمدة في طلبات الصرف لهذا أمر العمل', 'info');
        return;
    }

    // مسح المواد الموجودة
    clearExistingMaterials();

    // إضافة المواد الجديدة
    materials.forEach((material, index) => {
        addMaterialRow();
        const rowIndex = materialRowCounter;

        // تحديد المادة في القائمة المنسدلة
        const select = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`);
        const searchInput = document.querySelector(`input[onkeyup*="${rowIndex}"]`);

        if (select && searchInput) {
            select.value = material.material_id;
            searchInput.value = material.item_number;
            searchInput.classList.add('selected-item');

            // تحديث معلومات المادة
            updateMaterialInfo(select, rowIndex);

            // تعبئة الكميات
            const actualQtyInput = select.closest('tr').querySelector('input[name*="actual_quantity"]');

            if (actualQtyInput) {
                actualQtyInput.value = material.estimated_quantity; // الكمية من طلب الصرف تذهب للطبيعة
            }

            // حساب الكميات والإجماليات
            calculateMaterialQuantities(rowIndex);
        }
    });

    if (!alertShown) {
        showAlert(`تم تحميل ${materials.length} مادة من طلبات الصرف المعتمدة`, 'success', 3000);
    }
}

// مسح المواد الموجودة
function clearExistingMaterials() {
    const tbody = document.getElementById('materialsTableBody');
    tbody.innerHTML = `
        <tr id="noMaterialsRow">
            <td colspan="10" class="text-center text-muted py-3">
                <i class="fas fa-info-circle me-2"></i>
                اضغط على "إضافة مادة" لبدء إضافة المواد المستخدمة.
            </td>
        </tr>
    `;
    materialRowCounter = 0;
    updateTotals();
}

// عرض معلومات أمر العمل
function showWorkOrderInfo(workOrder, requests, summary) {
    // إنشاء أو تحديث قسم معلومات أمر العمل
    let infoContainer = document.getElementById('workOrderInfo');
    if (!infoContainer) {
        infoContainer = document.createElement('div');
        infoContainer.id = 'workOrderInfo';
        infoContainer.className = 'alert alert-info mt-3';

        // إدراج بعد حقل أمر العمل
        const workOrderContainer = document.querySelector('.work-order-search-container').parentNode;
        workOrderContainer.appendChild(infoContainer);
    }

    infoContainer.innerHTML = `
        <h6 class="alert-heading">
            <i class="fas fa-info-circle me-2"></i>
            معلومات أمر العمل: ${workOrder.work_order_number} (${workOrder.type_code})
        </h6>
        <div class="row">
            <div class="col-md-4">
                <strong>الفرع:</strong> ${workOrder.branch_name}<br>
                <strong>القسم:</strong> ${workOrder.department === 'connections' ? 'التوصيلات' : 'المشاريع'}<br>
                <strong>الموقع:</strong> ${workOrder.location ? '<i class="fas fa-map-marker-alt text-primary me-1"></i>' + workOrder.location : '<span class="text-muted">غير محدد</span>'}
            </div>
            <div class="col-md-4">
                <strong>عدد طلبات الصرف المعتمدة:</strong> ${summary.total_requests}<br>
                <strong>عدد المواد:</strong> ${summary.total_materials}
            </div>
            <div class="col-md-4">
                <strong>إجمالي الكمية:</strong> ${summary.total_quantity ? summary.total_quantity.toFixed(3) : '0.000'}
            </div>
        </div>
        ${requests.length > 0 ? `
        <hr>
        <small>
            <strong>طلبات الصرف المعتمدة:</strong>
            ${requests.map(req => `${req.request_number} (${req.materials_count} مادة)`).join(', ')}
        </small>
        ` : ''}
    `;
}

// إظهار مؤشر التحميل
function showLoadingIndicator() {
    const workOrderContainer = document.querySelector('.work-order-search-container').parentNode;

    let loadingDiv = document.getElementById('loadingIndicator');
    if (!loadingDiv) {
        loadingDiv = document.createElement('div');
        loadingDiv.id = 'loadingIndicator';
        loadingDiv.className = 'text-center mt-2';
        workOrderContainer.appendChild(loadingDiv);
    }

    loadingDiv.innerHTML = `
        <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
            <span class="visually-hidden">جاري التحميل...</span>
        </div>
        <small class="text-muted">جاري تحميل المواد من طلبات الصرف...</small>
    `;
}

// إخفاء مؤشر التحميل
function hideLoadingIndicator() {
    const loadingDiv = document.getElementById('loadingIndicator');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// متغير لمنع تكرار الرسائل
let alertShown = false;

// التحقق من وجود شهادة إنجاز موجودة
function checkExistingCertificate(workOrderId) {
    fetch(`check-existing-certificate.php?work_order_id=${workOrderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists && !alertShown) {
                alertShown = true;
                showAlert(`تحذير: يوجد شهادة إنجاز موجودة بالفعل لهذا أمر العمل (الشهادة رقم: ${data.certificate_id}). لا يمكن إنشاء أكثر من شهادة إنجاز واحدة لكل أمر عمل.`, 'warning', 0); // 0 = لا تختفي تلقائياً

                // تعطيل زر الحفظ
                const submitButton = document.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-ban me-2"></i>لا يمكن الحفظ - شهادة موجودة';
                    submitButton.classList.remove('btn-primary');
                    submitButton.classList.add('btn-secondary');
                }
            }
        })
        .catch(error => {
            console.error('خطأ في التحقق من الشهادة الموجودة:', error);
        });
}

// عرض التنبيهات
function showAlert(message, type = 'info', autoHide = 5000) {
    // إزالة التنبيهات الموجودة من نفس النوع لمنع التكرار
    const existingAlerts = document.querySelectorAll(`.alert-${type}`);
    existingAlerts.forEach(alert => alert.remove());

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // إدراج في أعلى النموذج
    const form = document.getElementById('createCertificateForm');
    form.insertBefore(alertDiv, form.firstChild);

    // إزالة التنبيه تلقائياً إذا تم تحديد وقت
    if (autoHide > 0) {
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, autoHide);
    }
}

// معالجة تغيير أمر العمل من القائمة المنسدلة العادية
function handleWorkOrderChange(selectElement) {
    // إعادة تعيين متغير التنبيه
    alertShown = false;

    // إعادة تمكين زر الحفظ
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-save me-2"></i>حفظ شهادة الإنجاز';
        submitButton.classList.remove('btn-secondary');
        submitButton.classList.add('btn-primary');
    }

    const workOrderId = selectElement.value;
    const updateMaterialsBtn = document.getElementById('updateMaterialsBtn');

    if (workOrderId) {
        // جلب المواد من طلبات الصرف المعتمدة
        loadWorkOrderMaterials(workOrderId);

        // تفعيل زر تحديث المواد
        if (updateMaterialsBtn) {
            updateMaterialsBtn.disabled = false;
            updateMaterialsBtn.title = 'تحديث المواد من طلبات الصرف المعتمدة';
        }
    } else {
        // مسح المعلومات إذا لم يتم اختيار أمر عمل
        const infoContainer = document.getElementById('workOrderInfo');
        if (infoContainer) {
            infoContainer.remove();
        }

        // تعطيل زر تحديث المواد
        if (updateMaterialsBtn) {
            updateMaterialsBtn.disabled = true;
            updateMaterialsBtn.title = 'يرجى اختيار أمر العمل أولاً';
        }
    }

    // تحديث حالة زر التوليد التلقائي
    updateAutoGenerateButtonState();
}

// دالة تحديث حالة زر التوليد التلقائي
function updateAutoGenerateButtonState() {
    const autoGenerateBtn = document.getElementById('autoGenerateBtn');
    if (!autoGenerateBtn) return;

    // التحقق من وجود مواد في الشهادة
    const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');
    const hasMaterials = materialRows.length > 0;

    if (hasMaterials) {
        // تفعيل الزر
        autoGenerateBtn.disabled = false;
        autoGenerateBtn.classList.remove('btn-secondary');
        autoGenerateBtn.classList.add('btn-primary');
        autoGenerateBtn.title = 'اضغط لتوليد بنود الأعمال تلقائياً من المواد المستخدمة';
    } else {
        // تعطيل الزر
        autoGenerateBtn.disabled = true;
        autoGenerateBtn.classList.remove('btn-primary');
        autoGenerateBtn.classList.add('btn-secondary');
        autoGenerateBtn.title = 'يرجى إضافة المواد المستخدمة أولاً';
    }
}

// البحث في المواد داخل الصف
function searchMaterialInRow(input, rowIndex) {
    const searchTerm = input.value.toLowerCase();
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    if (searchTerm.length < 1) {
        dropdownContainer.innerHTML = '';
        dropdownContainer.classList.remove('show');
        input.classList.remove('selected-item');
        return;
    }

    const filteredMaterials = materialsData.filter(material =>
        material.item_number.toLowerCase().includes(searchTerm) ||
        material.description.toLowerCase().includes(searchTerm)
    ).slice(0, 10);

    if (filteredMaterials.length > 0) {
        dropdownContainer.innerHTML = filteredMaterials.map(material => `
            <div class="dropdown-item-custom" onclick="selectMaterialInRow(${material.id}, ${rowIndex}, '${material.item_number}')">
                <div class="item-number">${material.item_number}</div>
                <div class="item-description">${material.description}</div>
                <small class="text-muted">المجموعة: ${material.group_number || 'غير محدد'}</small>
            </div>
        `).join('');
        dropdownContainer.classList.add('show');
    } else {
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
        dropdownContainer.classList.add('show');
    }
}

// اختيار مادة في الصف
function selectMaterialInRow(materialId, rowIndex, itemNumber) {
    const input = document.querySelector(`input[onkeyup*="${rowIndex}"]`);
    const select = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`);
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    if (input && select) {
        input.value = itemNumber;
        input.classList.add('selected-item');
        select.value = materialId;
        dropdownContainer.classList.remove('show');
        select.dispatchEvent(new Event('change'));
    }
}

// البحث في بنود الأعمال داخل الصف
function searchWorkInRow(input, rowIndex) {
    const searchTerm = input.value.toLowerCase();
    const dropdownContainer = document.querySelector(`.work-dropdown-${rowIndex}`);

    if (searchTerm.length < 1) {
        dropdownContainer.innerHTML = '';
        dropdownContainer.classList.remove('show');
        input.classList.remove('selected-item');
        return;
    }

    const filteredWorks = workItemsData.filter(work =>
        work.item_number.toLowerCase().includes(searchTerm) ||
        work.description.toLowerCase().includes(searchTerm)
    ).slice(0, 10);

    if (filteredWorks.length > 0) {
        dropdownContainer.innerHTML = filteredWorks.map(work => `
            <div class="dropdown-item-custom" onclick="selectWorkInRow(${work.id}, ${rowIndex}, '${work.item_number}')">
                <div class="item-number">${work.item_number}</div>
                <div class="item-description">${work.description}</div>

            </div>
        `).join('');
        dropdownContainer.classList.add('show');
    } else {
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
        dropdownContainer.classList.add('show');
    }
}

// اختيار بند عمل في الصف
function selectWorkInRow(workId, rowIndex, itemNumber) {
    const input = document.querySelector(`input[onkeyup*="searchWorkInRow"][onkeyup*="${rowIndex}"]`);
    const select = document.querySelector(`select[name="works[${rowIndex}][work_item_id]"]`);
    const dropdownContainer = document.querySelector(`.work-dropdown-${rowIndex}`);

    if (input && select) {
        input.value = itemNumber;
        input.classList.add('selected-item');
        select.value = workId;
        dropdownContainer.classList.remove('show');
        select.dispatchEvent(new Event('change'));
    }
}

// إخفاء جميع القوائم المنسدلة عند النقر خارجها
document.addEventListener('click', function(e) {
    // إخفاء قوائم المواد
    document.querySelectorAll('[class*="material-dropdown-"]').forEach(dropdown => {
        if (!dropdown.contains(e.target) && !dropdown.previousElementSibling.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // إخفاء قوائم الأعمال
    document.querySelectorAll('[class*="work-dropdown-"]').forEach(dropdown => {
        if (!dropdown.contains(e.target) && !dropdown.previousElementSibling.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
});

// إضافة صف مادة جديد
function addMaterialRow() {
    const tbody = document.getElementById('materialsTableBody');
    const noMaterialsRow = document.getElementById('noMaterialsRow');

    if (noMaterialsRow) {
        noMaterialsRow.remove();
    }

    materialRowCounter++;

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <div class="material-select-container position-relative">
                <input type="text" class="form-control form-control-sm material-search-input"
                       placeholder="ابحث عن مادة..." autocomplete="off"
                       onkeyup="searchMaterialInRow(this, ${materialRowCounter})">
                <select name="materials[${materialRowCounter}][material_id]" class="form-select form-select-sm d-none" onchange="updateMaterialInfo(this, ${materialRowCounter})" required>
                    <option value="">اختر المادة</option>
                    ${materialsData.map(material =>
                        `<option value="${material.id}" data-code="${material.item_number}" data-description="${material.description}" data-unit="${material.unit}" data-group="${material.group_number || ''}">${material.item_number}</option>`
                    ).join('')}
                </select>
                <div class="material-dropdown-${materialRowCounter} custom-dropdown"></div>
            </div>
        </td>
        <td><span id="material_group_${materialRowCounter}">-</span></td>
        <td><span id="material_description_${materialRowCounter}">-</span></td>
        <td><input type="number" name="materials[${materialRowCounter}][estimated_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="0" onchange="calculateMaterialQuantities(${materialRowCounter})"></td>
        <td><input type="number" name="materials[${materialRowCounter}][actual_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="0" onchange="calculateMaterialQuantities(${materialRowCounter})"></td>
        <td><span id="material_dispensed_${materialRowCounter}" class="fw-bold text-info">0.000</span><input type="hidden" name="materials[${materialRowCounter}][dispensed_quantity]" value="0"></td>
        <td><span id="material_returned_${materialRowCounter}" class="fw-bold text-success">0.000</span><input type="hidden" name="materials[${materialRowCounter}][returned_quantity]" value="0"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMaterialRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);

    // تحديث حالة زر التوليد التلقائي
    updateAutoGenerateButtonState();
}

// إضافة صف عمل جديد
function addWorkRow() {
    const tbody = document.getElementById('worksTableBody');
    const noWorksRow = document.getElementById('noWorksRow');

    if (noWorksRow) {
        noWorksRow.remove();
    }

    workRowCounter++;

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <div class="work-select-container position-relative">
                <input type="text" class="form-control form-control-sm work-search-input"
                       placeholder="ابحث عن عمل..." autocomplete="off"
                       onkeyup="searchWorkInRow(this, ${workRowCounter})">
                <select name="works[${workRowCounter}][work_item_id]" class="form-select form-select-sm d-none" onchange="updateWorkInfo(this, ${workRowCounter})" required>
                    <option value="">اختر العمل</option>
                    ${workItemsData.map(workItem =>
                        `<option value="${workItem.id}" data-code="${workItem.item_number}" data-description="${workItemc.description}" data-unit="${workItemc.unit}" data-price="${workItem.standard_price || 0}">${workItem.item_number}</option>`
                    ).join('')}
                </select>
                <div class="work-dropdown-${workRowCounter} custom-dropdown"></div>
            </div>
        </td>
        <td><span id="work_description_${workRowCounter}">-</span></td>
        <td><span id="work_unit_${workRowCounter}">-</span></td>
        <td><input type="number" name="works[${workRowCounter}][estimated_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="0" placeholder="المقايسة"></td>
        <td><input type="number" name="works[${workRowCounter}][quantity]" class="form-control form-control-sm" step="0.001" min="0" value="0" required onchange="calculateWorkTotal(this)"></td>
        <td><input type="number" name="works[${workRowCounter}][unit_price]" class="form-control form-control-sm" step="0.01" min="0" value="0" onchange="calculateWorkTotal(this)"></td>
        <td>
            <span class="fw-bold text-success work-total">0.00</span>
            <input type="hidden" name="works[${workRowCounter}][total_value]" class="work-total-hidden" value="0">
        </td>
        <td><input type="number" name="works[${workRowCounter}][completion_percentage]" class="form-control form-control-sm" step="0.01" min="0" max="100" value="100"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeWorkRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    updateTotals();
}

// تحديث معلومات المادة
function updateMaterialInfo(select, rowIndex) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption.value) {
        document.getElementById(`material_group_${rowIndex}`).textContent = selectedOption.dataset.group || '-';
        document.getElementById(`material_description_${rowIndex}`).textContent = selectedOption.dataset.description || '-';
    } else {
        document.getElementById(`material_group_${rowIndex}`).textContent = '-';
        document.getElementById(`material_description_${rowIndex}`).textContent = '-';
    }
    // لا حاجة لحساب الإجمالي
}

// تحديث معلومات العمل
function updateWorkInfo(select, rowIndex) {
    const selectedOption = select.options[select.selectedIndex];
    const row = select.closest('tr');

    if (selectedOption.value) {
        document.getElementById(`work_description_${rowIndex}`).textContent = selectedOption.dataset.description || '-';
        document.getElementById(`work_unit_${rowIndex}`).textContent = selectedOption.dataset.unit || '-';

        // تحديث السعر
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        if (priceInput && selectedOption.dataset.price) {
            priceInput.value = selectedOption.dataset.price;
            calculateWorkTotal(priceInput);
        }
    } else {
        document.getElementById(`work_description_${rowIndex}`).textContent = '-';
        document.getElementById(`work_unit_${rowIndex}`).textContent = '-';

        // إعادة تعيين السعر
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        if (priceInput) {
            priceInput.value = 0;
            calculateWorkTotal(priceInput);
        }
    }
}

// حساب إجمالي العمل
function calculateWorkTotal(input) {
    const row = input.closest('tr');
    const quantityInput = row.querySelector('input[name*="[quantity]"]');
    const priceInput = row.querySelector('input[name*="[unit_price]"]');
    const totalSpan = row.querySelector('.work-total');
    const totalHidden = row.querySelector('.work-total-hidden');

    if (quantityInput && priceInput && totalSpan && totalHidden) {
        const quantity = parseFloat(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;

        totalSpan.textContent = total.toFixed(2);
        totalHidden.value = total.toFixed(2);

        // تحديث الإجماليات العامة
        updateTotals();
    }
}

// تحديث الإجماليات العامة
function updateTotals() {
    let totalWorkValue = 0;

    // حساب إجمالي قيمة الأعمال
    document.querySelectorAll('.work-total-hidden').forEach(input => {
        totalWorkValue += parseFloat(input.value) || 0;
    });

    // حساب عدد المواد والأعمال
    const materialsCount = document.querySelectorAll('select[name*="material_id"]').length;
    const worksCount = document.querySelectorAll('select[name*="work_item_id"]').length;

    // تحديث العرض
    const totalDisplay = document.getElementById('totalWorkValue');
    const materialsCountDisplay = document.getElementById('materialsCount');
    const worksCountDisplay = document.getElementById('worksCount');

    if (totalDisplay) {
        totalDisplay.textContent = totalWorkValue.toFixed(2) + ' ريال';
    }
    if (materialsCountDisplay) {
        materialsCountDisplay.textContent = materialsCount;
    }
    if (worksCountDisplay) {
        worksCountDisplay.textContent = worksCount;
    }
}

// حساب كميات المادة (صرف وإرجاع تلقائي)
function calculateMaterialQuantities(rowIndex) {
    const row = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`).closest('tr');
    const estimatedQty = parseFloat(row.querySelector('input[name*="estimated_quantity"]').value) || 0;
    const actualQty = parseFloat(row.querySelector('input[name*="actual_quantity"]').value) || 0;


    // حساب الصرف والإرجاع
    let dispensedQty = 0;
    let returnedQty = 0;

    if (actualQty > estimatedQty) {
        // إذا كانت الطبيعة أكبر من المقايسة = صرف إضافي
        dispensedQty = actualQty - estimatedQty;
        returnedQty = 0;
    } else if (estimatedQty > actualQty) {
        // إذا كانت المقايسة أكبر من الطبيعة = إرجاع
        dispensedQty = 0;
        returnedQty = estimatedQty - actualQty;
    } else {
        // إذا كانت متساوية = لا صرف ولا إرجاع
        dispensedQty = 0;
        returnedQty = 0;
    }

    // تحديث العرض
    document.getElementById(`material_dispensed_${rowIndex}`).textContent = dispensedQty.toFixed(3);
    document.getElementById(`material_returned_${rowIndex}`).textContent = returnedQty.toFixed(3);

    // تحديث الحقول المخفية
    row.querySelector('input[name*="dispensed_quantity"]').value = dispensedQty;
    row.querySelector('input[name*="returned_quantity"]').value = returnedQty;



    updateTotals();
}

// لا حاجة لحساب الإجماليات المالية

// حذف صف مادة
function removeMaterialRow(button) {
    button.closest('tr').remove();
    updateTotals();

    // إضافة صف "لا توجد مواد" إذا لم تعد هناك مواد
    const tbody = document.getElementById('materialsTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noMaterialsRow">
                <td colspan="10" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    اضغط على "إضافة مادة" لبدء إضافة المواد المستخدمة.
                </td>
            </tr>
        `;
    }

    // تحديث حالة زر التوليد التلقائي
    updateAutoGenerateButtonState();
}

// حذف صف عمل
function removeWorkRow(button) {
    button.closest('tr').remove();
    updateTotals();

    // إضافة صف "لا توجد أعمال" إذا لم تعد هناك أعمال
    const tbody = document.getElementById('worksTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noWorksRow">
                <td colspan="9" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    اضغط على "إضافة عمل" لبدء إضافة الأعمال المنجزة.
                </td>
            </tr>
        `;
    }
}

// دالة التوليد التلقائي لبنود الأعمال
function autoGenerateWorkItems() {
    // التحقق من وجود مواد في الشهادة
    const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

    if (materialRows.length === 0) {
        Swal.fire({
            title: 'تنبيه',
            text: 'يرجى إضافة المواد المستخدمة في الشهادة أولاً',
            icon: 'warning',
            confirmButtonText: 'موافق'
        });
        return;
    }

    // تأكيد العملية
    Swal.fire({
        title: 'توليد بنود الأعمال تلقائياً',
        text: 'سيتم توليد بنود الأعمال بناءً على المواد المستخدمة في الشهادة. هل تريد المتابعة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، ولّد تلقائياً',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            performAutoGenerationFromCertificateMaterials();
        }
    });
}

// تنفيذ التوليد التلقائي من المواد الموجودة في الشهادة
function performAutoGenerationFromCertificateMaterials() {
    // إظهار مؤشر التحميل
    const autoGenerateBtn = document.getElementById('autoGenerateBtn');
    const originalText = autoGenerateBtn.innerHTML;
    autoGenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التوليد...';
    autoGenerateBtn.disabled = true;

    try {
        // جمع بيانات المواد من الجدول
        const certificateMaterials = [];
        const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

        materialRows.forEach((row, index) => {
            // البحث عن عناصر المادة والكمية بطرق متعددة
            let materialSelect = row.querySelector('select[name*="[material_id]"]');
            if (!materialSelect) {
                materialSelect = row.querySelector('select[name*="material_id"]');
            }

            let quantityInput = row.querySelector('input[name*="[actual_quantity]"]');
            if (!quantityInput) {
                quantityInput = row.querySelector('input[name*="actual_quantity"]');
            }



            if (materialSelect && materialSelect.value && quantityInput) {
                const materialId = parseInt(materialSelect.value);
                const quantity = parseFloat(quantityInput.value) || 0;

                if (materialId && quantity > 0) {
                    certificateMaterials.push({
                        material_id: materialId,
                        quantity: quantity
                    });
                }
            }
        });



        if (certificateMaterials.length === 0) {
            // التحقق من سبب عدم وجود مواد
            const materialsWithZeroQuantity = [];
            materialRows.forEach((row, index) => {
                const materialSelect = row.querySelector('select[name*="[material_id]"]') || row.querySelector('select[name*="material_id"]');
                const quantityInput = row.querySelector('input[name*="[actual_quantity]"]') || row.querySelector('input[name*="actual_quantity"]');

                if (materialSelect && materialSelect.value && quantityInput) {
                    const quantity = parseFloat(quantityInput.value) || 0;
                    if (quantity === 0) {
                        materialsWithZeroQuantity.push(materialSelect.selectedOptions[0]?.text || `المادة ${materialSelect.value}`);
                    }
                }
            });

            let errorMessage = 'لا توجد مواد صحيحة في الشهادة.\n\n';

            if (materialsWithZeroQuantity.length > 0) {
                errorMessage += 'المواد التالية محددة لكن الكمية الفعلية = 0:\n';
                errorMessage += materialsWithZeroQuantity.join('\n');
                errorMessage += '\n\nيرجى إدخال الكميات الفعلية للمواد المستخدمة.';
            } else {
                errorMessage += 'يرجى إضافة المواد وإدخال الكميات الفعلية أولاً.';
            }

            throw new Error(errorMessage);
        }

        // إرسال طلب AJAX مع بيانات المواد
        fetch('auto-generate-from-materials.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                materials: certificateMaterials
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // إظهار رسالة نجاح
                Swal.fire({
                    title: 'تم التوليد بنجاح!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'موافق'
                });

                // إضافة بنود الأعمال المولدة إلى الجدول
                addGeneratedWorkItems(data.data.generated_work_items);

                // عرض سجل التوليد
                if (data.data.generation_log && data.data.generation_log.length > 0) {
                    console.log('سجل التوليد:', data.data.generation_log);
                }

            } else {
                Swal.fire({
                    title: 'خطأ في التوليد',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            }
        })
        .catch(error => {
            console.error('خطأ في التوليد التلقائي:', error);

            let errorMessage = 'حدث خطأ أثناء التوليد التلقائي. يرجى المحاولة مرة أخرى.';
            if (error.message) {
                errorMessage = error.message;
            }

            Swal.fire({
                title: 'خطأ في التوليد التلقائي',
                text: errorMessage,
                icon: 'error',
                confirmButtonText: 'موافق'
            });
        });

    } catch (error) {
        Swal.fire({
            title: 'خطأ',
            text: error.message,
            icon: 'error',
            confirmButtonText: 'موافق'
        });
    } finally {
        // إعادة تعيين الزر
        autoGenerateBtn.innerHTML = originalText;
        autoGenerateBtn.disabled = false;
    }
}

// إضافة بنود الأعمال المولدة إلى الجدول
function addGeneratedWorkItems(generatedWorkItems) {
    // مسح الجدول الحالي
    const tbody = document.getElementById('worksTableBody');
    tbody.innerHTML = '';

    // إضافة كل بند عمل مولد
    Object.values(generatedWorkItems).forEach(workItem => {
        addWorkRowWithData(workItem);
    });

    // تحديث الإجماليات
    updateTotals();
}

// إضافة صف عمل مع بيانات محددة مسبقاً
function addWorkRowWithData(workData) {
    workRowCounter++;

    const tbody = document.getElementById('worksTableBody');
    const noWorksRow = document.getElementById('noWorksRow');
    if (noWorksRow) {
        noWorksRow.remove();
    }

    const row = document.createElement('tr');
    row.id = `workRow${workRowCounter}`;
    row.innerHTML = `
        <td>
            <div class="position-relative">
                <input type="text" class="form-control form-control-sm work-search-input"
                       value="${workData.work_item_number} - ${workData.work_item_description}"
                       readonly>
                <select name="works[${workRowCounter}][work_item_id]" class="form-select form-select-sm d-none" required>
                    <option value="${workData.work_item_id}" selected>${workData.work_item_number}</option>
                </select>
            </div>
        </td>
        <td>
            <span class="text-muted">${workData.work_item_description}</span>
        </td>
        <td>
            <span class="text-muted">${workData.work_item_unit || '-'}</span>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm"
                   name="works[${workRowCounter}][estimated_quantity]"
                   value="${workData.total_quantity.toFixed(3)}"
                   step="0.001" min="0"
                   placeholder="المقايسة">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm"
                   name="works[${workRowCounter}][quantity]"
                   value="${workData.total_quantity.toFixed(3)}"
                   step="0.001" min="0"
                   required>
        </td>
        <td>
            <input type="number" name="works[${workRowCounter}][completion_percentage]" class="form-control form-control-sm"
                   value="100" min="0" max="100" step="0.1">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeWorkRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    updateTotals();
}

// التحقق من النموذج قبل الإرسال
document.getElementById('createCertificateForm').addEventListener('submit', function(e) {
    const materialsCount = document.querySelectorAll('select[name*="material_id"]').length;
    const worksCount = document.querySelectorAll('select[name*="work_item_id"]').length;

    if (materialsCount === 0 && worksCount === 0) {
        e.preventDefault();
        alert('يجب إضافة مواد أو أعمال على الأقل');
        return false;
    }

    return true;
});

// تحديث الإجماليات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updateTotals();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
