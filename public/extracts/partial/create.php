<?php
/**
 * نموذج إنشاء المستخلص الجزئي
 * Partial Extract Creation Form
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إنشاء مستخلص جزئي';
$currentPage = 'extracts-partial';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص جزئي', 'url' => 'extracts/partial/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_create')) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// التحقق من وضع التعديل
$isEdit = false;
$editExtract = null;
$editWorkOrders = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $extractId = (int) $_GET['id'];

    // جلب بيانات المستخلص للتعديل (فقط المسودات)
    $stmt = $db->prepare("
        SELECT pe.*, b.name as branch_name
        FROM partial_extracts pe
        LEFT JOIN branches b ON pe.branch_id = b.id
        WHERE pe.id = ? AND (pe.approval_stage = 'draft' OR pe.approval_stage IS NULL)
    ");
    $stmt->execute([$extractId]);
    $editExtract = $stmt->fetch();

    if ($editExtract) {
        $isEdit = true;

        // جلب أوامر العمل المرتبطة بالمستخلص
        $stmt = $db->prepare("
            SELECT pewo.*, wo.work_order_number, wo.actual_value, wo.department,
                   wot.type_code as work_order_type_code,
                   b.name as branch_name,
                   ce.name as current_entity_name
            FROM partial_extract_work_orders pewo
            JOIN work_orders wo ON pewo.work_order_id = wo.id
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            LEFT JOIN branches b ON wo.branch_id = b.id
            LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
            WHERE pewo.partial_extract_id = ?
        ");
        $stmt->execute([$extractId]);
        $editWorkOrders = $stmt->fetchAll();

        // تحديث عنوان الصفحة
        $pageTitle = 'تعديل المستخلص الجزئي - ' . $editExtract['extract_number'];
        $breadcrumbs[2]['title'] = 'تعديل مستخلص جزئي';
    } else {
        // المستخلص غير موجود أو لا يمكن تعديله
        header('Location: index.php');
        exit();
    }
}

// جلب الفروع
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// جلب أوامر العمل المتاحة (التي لم تدخل في أي مستخلصات)
$workOrdersQuery = "
    SELECT wo.*,
           wot.type_code as work_order_type_code,
           wot.description as work_order_type_name,
           b.name as branch_name,
           b.id as branch_id,
           ce.name as current_entity_name,
           CASE
               WHEN wo.department = 'connections' THEN 'التوصيلات'
               WHEN wo.department = 'projects' THEN 'المشاريع'
               ELSE wo.department
           END as department_name
    FROM work_orders wo
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
    WHERE wo.status IN ('active', 'completed')
    AND wo.id NOT IN (
        -- أوامر العمل في المستخلصات الجزئية (جميع الحالات)
        SELECT DISTINCT pewo.work_order_id
        FROM partial_extract_work_orders pewo
        INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id" .
        ($isEdit ? " WHERE pe.id != " . $extractId : "") . "
        UNION
        -- أوامر العمل في المستخلصات النهائية العادية (جميع الحالات)
        SELECT DISTINCT frewo.work_order_id
        FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        UNION
        -- أوامر العمل في المستخلصات النهائية للجزئية (جميع الحالات)
        SELECT DISTINCT ffpewo.work_order_id
        FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    )
    ORDER BY wo.assignment_date DESC
";
$workOrders = $db->query($workOrdersQuery)->fetchAll();

// توليد رقم المستخلص التلقائي (فقط للإنشاء الجديد)
$suggestedExtractNumber = '';
if (!$isEdit) {
    $currentYear = date('Y');
    $lastExtractQuery = "SELECT extract_number FROM partial_extracts WHERE extract_number LIKE 'PE-$currentYear-%' ORDER BY id DESC LIMIT 1";
    $lastExtract = $db->query($lastExtractQuery)->fetch();

    if ($lastExtract) {
        $lastNumber = intval(substr($lastExtract['extract_number'], -3));
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '001';
    }
    $suggestedExtractNumber = "PE-$currentYear-$newNumber";
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0"><?php echo $isEdit ? 'تعديل المستخلص الجزئي' : 'إضافة مستخلص جزئي جديد للمشروع'; ?></p>
    </div>
    <div>
        <a href="<?= path('extracts/partial/index.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i>
            العودة للقائمة
        </a>
    </div>
</div>

            <!-- نموذج إنشاء المستخلص -->
            <form id="partialExtractForm" method="POST" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="extract_id" value="<?php echo $editExtract['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <!-- معلومات المستخلص الأساسية -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-primary me-2"></i>
                                    معلومات المستخلص الأساسية
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="extract_number" class="form-label">رقم المستخلص <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="extract_number" name="extract_number"
                                               value="<?php echo $isEdit ? htmlspecialchars($editExtract['extract_number']) : $suggestedExtractNumber; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="entry_sheet_number" class="form-label">
                                            رقم صحيفة الإدخال
                                            <small class="text-muted">(اختياري - 10 أرقام)</small>
                                        </label>
                                        <input type="text" class="form-control" id="entry_sheet_number" name="entry_sheet_number"
                                               pattern="[0-9]{10}" maxlength="10"
                                               placeholder="مثال: 1234567890"
                                               value="<?php echo $isEdit ? htmlspecialchars($editExtract['entry_sheet_number'] ?? '') : ''; ?>">
                                        <div class="form-text">يجب أن يكون مكون من 10 أرقام فقط</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                               value="<?php echo $isEdit ? htmlspecialchars($editExtract['invoice_number'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="branch_id" class="form-label">الفرع <span class="text-danger">*</span></label>
                                        <select class="form-select" id="branch_id" name="branch_id" required>
                                            <option value="">سيتم تحديده تلقائياً</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>"
                                                        <?php echo ($isEdit && $editExtract['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($branch['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            <?php echo $isEdit ? 'يمكن تغيير الفرع إذا لزم الأمر' : 'سيتم تحديد الفرع تلقائياً عند إضافة أول أمر عمل'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">القسم <span class="text-danger">*</span></label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">سيتم تحديده تلقائياً</option>
                                            <option value="connections" <?php echo ($isEdit && $editExtract['department'] == 'connections') ? 'selected' : ''; ?>>التوصيلات</option>
                                            <option value="projects" <?php echo ($isEdit && $editExtract['department'] == 'projects') ? 'selected' : ''; ?>>المشاريع</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            <?php echo $isEdit ? 'يمكن تغيير القسم إذا لزم الأمر' : 'سيتم تحديد القسم تلقائياً عند إضافة أول أمر عمل'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_date" class="form-label">تاريخ المستخلص <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="extract_date" name="extract_date"
                                               value="<?php echo $isEdit ? $editExtract['extract_date'] : date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">ملاحظات المستخلص</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"
                                                  placeholder="ملاحظات وتفاصيل إضافية للمستخلص الجزئي..."><?php echo $isEdit ? htmlspecialchars($editExtract['description'] ?? '') : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الجزء الأول: أوامر العمل المتاحة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list text-primary me-2"></i>
                                    الجزء الأول: أوامر العمل المتاحة
                                    <span class="badge bg-secondary ms-2" id="availableCount"><?php echo count($workOrders); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="availableWorkOrdersTable" class="table table-sm table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>رقم الأمر</th>
                                                <th>كود النوع</th>
                                                <th>القيمة الفعلية</th>
                                                <th>الإجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($workOrders as $wo): ?>
                                                <tr data-work-order-id="<?php echo $wo['id']; ?>">
                                                    <td><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($wo['work_order_type_code']); ?></td>
                                                    <td><?php echo number_format($wo['actual_value'] ?: $wo['estimated_value'], 2); ?> ريال</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary add-work-order"
                                                                data-id="<?php echo $wo['id']; ?>"
                                                                data-number="<?php echo htmlspecialchars($wo['work_order_number']); ?>"
                                                                data-type="<?php echo htmlspecialchars($wo['work_order_type_code']); ?>"
                                                                data-department="<?php echo htmlspecialchars($wo['department']); ?>"
                                                                data-department-name="<?php echo htmlspecialchars($wo['department_name']); ?>"
                                                                data-branch-id="<?php echo $wo['branch_id']; ?>"
                                                                data-branch-name="<?php echo htmlspecialchars($wo['branch_name']); ?>"
                                                                data-value="<?php echo $wo['actual_value'] ?: $wo['estimated_value']; ?>"
                                                                data-receipt-date="<?php echo $wo['receipt_date'] ?? ''; ?>">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- الجزء الثاني: أوامر العمل المختارة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    الجزء الثاني: أوامر العمل المختارة
                                    <span class="badge bg-success ms-2" id="selectedCount">0</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="selectedWorkOrdersContainer">
                                    <div class="text-center text-muted py-4" id="emptyMessage">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>لم يتم اختيار أي أوامر عمل بعد</p>
                                        <small>استخدم الجزء الأول لإضافة أوامر العمل</small>
                                    </div>
                                </div>
                                
                                <div id="selectedWorkOrdersTable" style="display: none;">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>رقم الأمر</th>
                                                    <th>كود النوع</th>
                                                    <th>القيمة الفعلية</th>
                                                    <th>قيمة المستخلص <span class="text-danger">*</span></th>
                                                    <th>تاريخ الإنجاز <span class="text-danger">*</span></th>
                                                    <th>الإجراء</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedWorkOrdersBody">
                                                <!-- سيتم إضافة الصفوف هنا ديناميكياً -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الشريط الجانبي -->
                    <div class="col-lg-4">
                        <!-- تنبيه مهم -->
                        <div class="alert alert-warning mb-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                تنبيه مهم
                            </h6>
                            <p class="mb-0">
                                <strong>يتم حساب صافي المستخلص من غير ضريبة.</strong><br>
                                الصافي = إجمالي المبلغ (بدون إضافة الضريبة)
                            </p>
                        </div>

                        <!-- ملخص المستخلص -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calculator text-primary me-2"></i>
                                    ملخص المستخلص
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-1">إجمالي المبلغ</h6>
                                            <h4 class="text-primary mb-0" id="totalAmount">0.00 ريال</h4>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted">الضريبة (15%)</small>
                                            <div class="fw-bold" id="taxAmount">0.00 ريال</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted">الصافي</small>
                                            <div class="fw-bold text-success" id="netAmount">0.00 ريال</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- المرفقات -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-paperclip text-primary me-2"></i>
                                    المرفقات
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple 
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                    <small class="text-muted">يمكنك رفع ملفات PDF, Word, Excel, أو صور</small>
                                </div>
                                <div id="attachmentsList"></div>
                            </div>
                        </div>

                        <!-- زر التقديم -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg" name="action" value="submit">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        إنشاء وتقديم المستخلص الجزئي
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable
    var availableTable = $('#availableWorkOrdersTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        pageLength: 10,
        order: [[0, 'desc']],
        initComplete: function() {
            // جعل حقل البحث رقمي فقط
            var searchInput = $('#availableWorkOrdersTable_filter input');
            searchInput.attr('type', 'tel');
            searchInput.attr('inputmode', 'numeric');
            searchInput.attr('pattern', '[0-9]*');
            searchInput.attr('placeholder', 'ابحث برقم أمر العمل...');
            searchInput.on('input', function() {
                var val = $(this).val().replace(/[^0-9]/g, '');
                if ($(this).val() !== val) {
                    $(this).val(val);
                    availableTable.search(val).draw();
                }
            });
        }
    });

    let selectedWorkOrders = [];

    // تحميل أوامر العمل المرتبطة في حالة التعديل
    <?php if ($isEdit && !empty($editWorkOrders)): ?>
    <?php foreach ($editWorkOrders as $ewo): ?>
    selectedWorkOrders.push({
        id: <?php echo $ewo['work_order_id']; ?>,
        number: '<?php echo htmlspecialchars($ewo['work_order_number']); ?>',
        type: '<?php echo htmlspecialchars($ewo['work_order_type_code']); ?>',
        value: <?php echo $ewo['actual_value'] ?: 0; ?>,
        extractValue: <?php echo $ewo['extract_value']; ?>,
        completionDate: '<?php echo $ewo['completion_date']; ?>',
        department: '<?php echo $ewo['department']; ?>',
        departmentName: '<?php echo $ewo['department'] == 'connections' ? 'التوصيلات' : 'المشاريع'; ?>',
        branchId: <?php echo $editExtract['branch_id']; ?>,
        branchName: '<?php echo htmlspecialchars($ewo['branch_name']); ?>'
    });
    <?php endforeach; ?>

    // تحديث العرض بعد تحميل البيانات
    setTimeout(function() {
        updateSelectedWorkOrders();
        updateSummary();
        console.log('تم تحميل أوامر العمل:', selectedWorkOrders);
    }, 100);
    <?php endif; ?>

    // إضافة أمر عمل
    $(document).on('click', '.add-work-order', function() {
        const id = $(this).data('id');
        const number = $(this).data('number');
        const type = $(this).data('type');
        const value = $(this).data('value');
        const department = $(this).data('department');
        const departmentName = $(this).data('department-name');
        const branchId = $(this).data('branch-id');
        const branchName = $(this).data('branch-name');
        const receiptDate = $(this).data('receipt-date');

        // تحديد تاريخ الإنجاز (فقط من تاريخ الاستلام)
        let completionDate = '';
        if (receiptDate) {
            completionDate = receiptDate;
        }
        // إذا لم يكن هناك تاريخ استلام، يبقى فارغاً ليدخله المستخدم

        // التحقق من عدم الإضافة المكررة
        if (selectedWorkOrders.find(wo => wo.id === id)) {
            alert('تم إضافة هذا الأمر مسبقاً');
            return;
        }

        // التحديد التلقائي للفرع والقسم عند إضافة أول أمر عمل
        if (selectedWorkOrders.length === 0) {
            // تحديد الفرع تلقائياً
            $('#branch_id').val(branchId);
            $('#branch_id').trigger('change');

            // تحديد القسم تلقائياً
            $('#department').val(department);
            $('#department').trigger('change');

            // إظهار رسالة تأكيد
            showAutoSelectionMessage(branchName, departmentName);
        } else {
            // التحقق من تطابق الفرع والقسم مع الأوامر المختارة
            const currentBranch = $('#branch_id').val();
            const currentDepartment = $('#department').val();

            if (currentBranch != branchId) {
                if (!confirm(`أمر العمل من فرع مختلف (${branchName}). هل تريد المتابعة؟`)) {
                    return;
                }
            }

            if (currentDepartment != department) {
                if (!confirm(`أمر العمل من قسم مختلف (${departmentName}). هل تريد المتابعة؟`)) {
                    return;
                }
            }
        }

        // إضافة إلى المصفوفة
        selectedWorkOrders.push({
            id: id,
            number: number,
            type: type,
            value: value,
            department: department,
            departmentName: departmentName,
            branchId: branchId,
            branchName: branchName,
            completionDate: completionDate, // فارغ إذا لم يكن موجوداً في أمر العمل
            extractValue: '' // سيتم ملؤها من قبل المستخدم
        });

        // إخفاء الصف من الجدول الأول
        $(this).closest('tr').hide();

        // تحديث العرض
        updateSelectedWorkOrders();
        updateSummary();
    });

    // حذف أمر عمل
    $(document).on('click', '.remove-work-order', function() {
        const id = parseInt($(this).data('id'));

        // إزالة من المصفوفة
        selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== id);

        // إظهار الصف في الجدول الأول
        $(`tr[data-work-order-id="${id}"]`).show();

        // إعادة تعيين الفرع والقسم إذا لم تعد هناك أوامر عمل
        if (selectedWorkOrders.length === 0) {
            $('#branch_id').val('');
            $('#department').val('');

            // إزالة أي رسائل تأكيد موجودة
            $('.alert-success').remove();
        }

        // تحديث العرض
        updateSelectedWorkOrders();
        updateSummary();
    });

    // تحديث قيم المستخلص
    $(document).on('input', '.extract-value, .completion-date', function() {
        updateSummary();
    });

    function updateSelectedWorkOrders() {
        const container = $('#selectedWorkOrdersContainer');
        const table = $('#selectedWorkOrdersTable');
        const tbody = $('#selectedWorkOrdersBody');
        const emptyMessage = $('#emptyMessage');
        
        if (selectedWorkOrders.length === 0) {
            table.hide();
            emptyMessage.show();
        } else {
            emptyMessage.hide();
            table.show();
            
            tbody.empty();
            selectedWorkOrders.forEach(wo => {
                // تحديد القيم الموجودة (للتعديل) أو القيم الافتراضية (للإنشاء الجديد)
                const extractValue = wo.extractValue || '';
                const completionDate = wo.completionDate || '';

                tbody.append(`
                    <tr>
                        <td>${wo.number}</td>
                        <td>${wo.type}</td>
                        <td>${parseFloat(wo.value).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ريال</td>
                        <td>
                            <input type="number" class="form-control form-control-sm extract-value"
                                   name="extract_values[${wo.id}]" step="0.01" min="0"
                                   value="${extractValue}" required>
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm completion-date"
                                   name="completion_dates[${wo.id}]" value="${completionDate}" required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-work-order" data-id="${wo.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
        
        $('#selectedCount').text(selectedWorkOrders.length);
    }

    function updateSummary() {
        let total = 0;
        $('.extract-value').each(function() {
            const value = parseFloat($(this).val()) || 0;
            total += value;
        });

        const tax = total * 0.15;
        const net = total; // الصافي = إجمالي المبلغ بدون ضريبة

        $('#totalAmount').text(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#taxAmount').text(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#netAmount').text(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
    }

    // دالة إظهار رسالة التحديد التلقائي
    function showAutoSelectionMessage(branchName, departmentName) {
        // إنشاء رسالة تأكيد
        const alertHtml = `
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <h6 class="alert-heading">
                    <i class="fas fa-check-circle me-2"></i>
                    تم التحديد التلقائي
                </h6>
                <p class="mb-0">
                    <strong>الفرع:</strong> ${branchName}<br>
                    <strong>القسم:</strong> ${departmentName}
                </p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // إضافة الرسالة بعد معلومات المستخلص الأساسية
        $('.card:first .card-body').append(alertHtml);

        // إزالة الرسالة تلقائياً بعد 5 ثوان
        setTimeout(function() {
            $('.alert-success').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // تقديم النموذج
    $('#partialExtractForm').on('submit', function(e) {
        e.preventDefault(); // منع الإرسال العادي

        if (selectedWorkOrders.length === 0) {
            alert('يجب إضافة أمر عمل واحد على الأقل');
            return false;
        }

        // إضافة معرفات أوامر العمل المختارة
        selectedWorkOrders.forEach(wo => {
            $(this).append(`<input type="hidden" name="work_order_ids[]" value="${wo.id}">`);
        });

        // إرسال النموذج عبر AJAX
        submitForm($(this));
    });

    // التحقق من رقم صحيفة الإدخال
    $('#entry_sheet_number').on('input', function() {
        const value = $(this).val();
        const feedback = $(this).siblings('.invalid-feedback');

        // إزالة أي أحرف غير رقمية
        const numericValue = value.replace(/[^0-9]/g, '');
        if (value !== numericValue) {
            $(this).val(numericValue);
        }

        // التحقق من الطول
        if (numericValue.length > 0 && numericValue.length !== 10) {
            $(this).addClass('is-invalid');
            if (feedback.length === 0) {
                $(this).after('<div class="invalid-feedback">يجب أن يكون الرقم مكون من 10 أرقام بالضبط</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            feedback.remove();
        }
    });

    // دالة إرسال النموذج عبر AJAX
    function submitForm($form) {
        // التحقق من رقم صحيفة الإدخال قبل الإرسال
        const entrySheetNumber = $('#entry_sheet_number').val();
        if (entrySheetNumber && entrySheetNumber.length !== 10) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ!',
                text: 'رقم صحيفة الإدخال يجب أن يكون مكون من 10 أرقام بالضبط',
                confirmButtonText: 'موافق'
            });
            return false;
        }

        const formData = new FormData($form[0]);

        // تسجيل البيانات المرسلة للتشخيص
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }

        // إظهار مؤشر التحميل
        const submitBtn = $form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...');

        $.ajax({
            url: 'create-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // إظهار رسالة نجاح
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح!',
                        text: response.message,
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        // إعادة التوجيه إلى صفحة العرض أو القائمة
                        if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else {
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    // إظهار رسالة خطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: response.message,
                        confirmButtonText: 'موافق'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                let errorMessage = 'حدث خطأ أثناء حفظ المستخلص';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: errorMessage,
                    confirmButtonText: 'موافق'
                });
            },
            complete: function() {
                // إعادة تفعيل الزر
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
