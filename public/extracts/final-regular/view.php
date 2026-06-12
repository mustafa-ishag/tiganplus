<?php
/**
 * صفحة عرض تفاصيل المستخلص النهائي العادي
 * Final Regular Extract View Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_view_details')) {
    header('Location: index.php');
    exit();
}

// التحقق من وجود معرف المستخلص
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$extract_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
} catch (Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
    exit();
}

// جلب تفاصيل المستخلص النهائي العادي
$extract = null;
$workOrders = [];

try {
    $extractQuery = "
        SELECT fre.*,
               b.name as branch_name,
               b.code as branch_code,
               u.full_name as created_by_name,
               (SELECT con.contract_number FROM work_orders wo2 
                JOIN contracts con ON wo2.contract_id = con.id 
                JOIN final_regular_extract_work_orders frewo2 ON wo2.id = frewo2.work_order_id 
                WHERE frewo2.final_regular_extract_id = fre.id LIMIT 1) as contract_number
        FROM final_regular_extracts fre
        LEFT JOIN branches b ON fre.branch_id = b.id
        LEFT JOIN users u ON fre.created_by = u.id
        WHERE fre.id = ?
    ";

    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        header('Location: index.php');
        exit();
    }

    // جلب أوامر العمل المرتبطة بالمستخلص مع تفاصيل التخريد
    $workOrdersQuery = "
        SELECT frewo.*,
               wo.work_order_number,
               wo.estimated_value,
               wo.actual_value,
               wo.assignment_date,
               wo.receipt_date,
               wo.department,
               wot.type_code,
               wot.description as work_order_type_name,
               ce.name as current_entity_name,
               -- حالة التخريد
               COALESCE(df.status, 'not_attached') as demolition_status,
               df.file_path as demolition_file_path,
               df.uploaded_at as demolition_uploaded_at
        FROM final_regular_extract_work_orders frewo
        INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
        WHERE frewo.final_regular_extract_id = ?
        ORDER BY wo.work_order_number
    ";

    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract_id]);
    $workOrders = $stmt->fetchAll();

} catch (Exception $e) {
    echo "خطأ في جلب البيانات: " . $e->getMessage();
    exit();
}

// جلب مراحل الاعتماد
try {
    $approvalStagesFromDB = $db->query("
        SELECT stage_key, stage_name, stage_color, stage_order, is_active
        FROM approval_stages
        WHERE is_active = 1
        ORDER BY stage_order
    ")->fetchAll();

    $stageNames = ['مسودة']; // للحالة NULL
    $approvalStages = [
        ['key' => null, 'name' => 'مسودة', 'icon' => 'fas fa-edit', 'color' => 'secondary']
    ];

    $stageIcons = [
        'technical_support' => 'fas fa-tools',
        'construction' => 'fas fa-hard-hat',
        'department_manager' => 'fas fa-user-tie',
        'administration_manager' => 'fas fa-crown',
        'taif_finance' => 'fas fa-coins',
        'disbursed' => 'fas fa-check-circle'
    ];

    foreach ($approvalStagesFromDB as $stage) {
        $stageNames[$stage['stage_key']] = $stage['stage_name'];
        $approvalStages[] = [
            'key' => $stage['stage_key'],
            'name' => $stage['stage_name'],
            'icon' => $stageIcons[$stage['stage_key']] ?? 'fas fa-check',
            'color' => $stage['stage_color']
        ];
    }

} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $stageNames = [
        null => "مسودة",
        "technical_support" => "الدعم الفني",
        "construction" => "الإنشاءات",
        "department_manager" => "مدير القسم",
        "administration_manager" => "مدير الإدارة",
        "taif_finance" => "مالية الطائف",
        "disbursed" => "تم الصرف"
    ];

    $approvalStages = [
        ['key' => null, 'name' => 'مسودة', 'icon' => 'fas fa-edit', 'color' => 'secondary'],
        ['key' => 'technical_support', 'name' => 'الدعم الفني', 'icon' => 'fas fa-tools', 'color' => 'info'],
        ['key' => 'construction', 'name' => 'الإنشاءات', 'icon' => 'fas fa-hard-hat', 'color' => 'warning'],
        ['key' => 'department_manager', 'name' => 'مدير القسم', 'icon' => 'fas fa-user-tie', 'color' => 'primary'],
        ['key' => 'administration_manager', 'name' => 'مدير الإدارة', 'icon' => 'fas fa-crown', 'color' => 'dark'],
        ['key' => 'taif_finance', 'name' => 'مالية الطائف', 'icon' => 'fas fa-coins', 'color' => 'warning'],
        ['key' => 'disbursed', 'name' => 'تم الصرف', 'icon' => 'fas fa-check-circle', 'color' => 'success']
    ];
}

// دالة مساعدة لأسماء مراحل الاعتماد
function getApprovalStageName($stage) {
    global $stageNames;
    return $stageNames[$stage] ?? "غير محدد";
}

// حساب إحصائيات التخريد
$totalWorkOrders = count($workOrders);
$completedDemolition = 0;
$pendingDemolition = 0;

foreach ($workOrders as $wo) {
    if ($wo['demolition_status'] === 'attached' || $wo['demolition_status'] === 'not_applicable') {
        $completedDemolition++;
    } else {
        $pendingDemolition++;
    }
}

$pageTitle = 'تفاصيل المستخلص النهائي العادي - ' . $extract['extract_number'];
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية العادية', 'url' => 'extracts/final-regular/index.php'],
    ['title' => 'تفاصيل المستخلص', 'url' => '']
];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-success">
                <i class="fas fa-file-invoice text-success me-2"></i>
                تفاصيل المستخلص النهائي العادي
            </h1>
            <p class="text-muted mb-0">عرض تفاصيل المستخلص رقم: <?php echo htmlspecialchars($extract['extract_number']); ?></p>
        </div>
        <div>
            <a href="export-invoice.php?id=<?php echo $extract_id; ?>" class="btn btn-success me-2" target="_blank">
                <i class="fas fa-file-excel me-1"></i>
                تصدير الفاتورة الضريبية
            </a>
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للقائمة
            </a>
            <?php if ($extract['approval_stage'] === null): ?>
            <a href="create.php?id=<?php echo $extract['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i>
                تعديل
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Extract Details Card -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات المستخلص
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">رقم المستخلص:</td>
                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($extract['extract_number']); ?></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">رقم العقد:</td>
                                    <td>
                                        <?php if (!empty($extract['contract_number'])): ?>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($extract['contract_number']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">رقم PO:</td>
                                    <td>
                                        <?php if (!empty($extract['po_number'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">رقم صحيفة الإدخال:</td>
                                    <td>
                                        <?php if (!empty($extract['entry_sheet_number'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($extract['entry_sheet_number']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">رقم الفاتورة:</td>
                                    <td><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">الفرع:</td>
                                    <td><?php echo htmlspecialchars($extract['branch_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">تاريخ المستخلص:</td>
                                    <td><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">تاريخ التقديم:</td>
                                    <td>
                                        <?php if ($extract['submission_date']): ?>
                                            <?php echo date('Y-m-d', strtotime($extract['submission_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">لم يتم التقديم</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">المبلغ الإجمالي:</td>
                                    <td><?php echo number_format($extract['total_amount'], 2); ?> ريال</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">الضريبة (<?php echo $extract['tax_rate']; ?>%):</td>
                                    <td><?php echo number_format($extract['tax_amount'], 2); ?> ريال</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">إجمالي الغرامات:</td>
                                    <td class="text-danger"><?php echo number_format($extract['total_penalty_amount'], 2); ?> ريال</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">المبلغ الصافي:</td>
                                    <td class="fw-bold text-success"><?php echo number_format($extract['net_amount'], 2); ?> ريال</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">أنشأ بواسطة:</td>
                                    <td><?php echo htmlspecialchars($extract['created_by_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($extract['description']): ?>
                    <div class="mt-3">
                        <h6 class="fw-bold">الوصف:</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($extract['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Approval Stage Card -->
            <div class="card shadow mb-3">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-clipboard-check me-2"></i>
                        مرحلة الاعتماد الحالية
                    </h6>
                </div>
                <div class="card-body text-center">
                    <?php
                    $currentStage = null;
                    foreach ($approvalStages as $stage) {
                        if ($stage['key'] === $extract['approval_stage']) {
                            $currentStage = $stage;
                            break;
                        }
                    }
                    if (!$currentStage && $extract['approval_stage'] === null) {
                        $currentStage = $approvalStages[0]; // مسودة
                    }
                    ?>
                    
                    <?php if ($currentStage): ?>
                        <div class="mb-3">
                            <i class="<?php echo $currentStage['icon']; ?> fa-3x text-<?php echo $currentStage['color']; ?> mb-2"></i>
                            <h5 class="text-<?php echo $currentStage['color']; ?>"><?php echo $currentStage['name']; ?></h5>
                        </div>
                        
                        <!-- تحديث مرحلة الاعتماد -->
                        <select class="form-select approval-stage-select" 
                                data-extract-id="<?= $extract['id'] ?>"
                                onchange="updateApprovalStage(<?= $extract['id'] ?>, this.value, this)">
                            <?php foreach ($approvalStages as $stage): ?>
                                <option value="<?php echo $stage['key'] ?? ''; ?>" 
                                        <?php echo ($stage['key'] === $extract['approval_stage']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stage['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Demolition Status Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-hammer me-2"></i>
                        حالة التخريد
                    </h6>
                </div>
                <div class="card-body text-center">
                    <?php if ($totalWorkOrders > 0): ?>
                        <?php if ($pendingDemolition > 0): ?>
                            <div class="mb-3">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                                <h5 class="text-warning">غير مكتمل</h5>
                                <p class="mb-0">
                                    <span class="badge bg-success"><?php echo $completedDemolition; ?></span>
                                    /
                                    <span class="badge bg-secondary"><?php echo $totalWorkOrders; ?></span>
                                    مكتمل
                                </p>
                                <small class="text-muted"><?php echo $pendingDemolition; ?> متبقي</small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                                <h5 class="text-success">مكتمل</h5>
                                <p class="mb-0">جميع نماذج التخريد مرفقة</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mb-3">
                            <i class="fas fa-info-circle fa-3x text-muted mb-2"></i>
                            <h5 class="text-muted">لا توجد أوامر عمل</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Orders Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-list me-2"></i>
                أوامر العمل المرتبطة (<?php echo count($workOrders); ?> أوامر)
            </h6>
        </div>
        <div class="card-body">
            <?php if (count($workOrders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="workOrdersTable">
                    <thead>
                        <tr>
                            <th>رقم الأمر</th>
                            <th>كود النوع</th>
                            <th>الجهة الحالية</th>
                            <th>القسم</th>
                            <th>تاريخ الإنجاز</th>
                            <th>قيمة المستخلص</th>
                            <th>الغرامة</th>
                            <th>التخريد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workOrders as $wo): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($wo['work_order_number']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($wo['type_code'] ?? 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($wo['current_entity_name'] ?? 'غير محدد'); ?></td>
                            <td>
                                <?php
                                switch($wo['department']) {
                                    case 'connections':
                                        echo '<span class="badge bg-info">التوصيلات</span>';
                                        break;
                                    case 'projects':
                                        echo '<span class="badge bg-warning">المشاريع</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary">' . htmlspecialchars($wo['department']) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($extract['approval_stage'] === null): ?>
                                    <!-- قابل للتحرير في حالة المسودة -->
                                    <input type="date" class="form-control form-control-sm completion-date-input"
                                           data-work-order-id="<?php echo $wo['work_order_id']; ?>"
                                           value="<?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>"
                                           onchange="updateCompletionDate(<?php echo $wo['work_order_id']; ?>, this.value)">
                                    <small class="text-muted">قابل للتعديل</small>
                                <?php else: ?>
                                    <!-- للقراءة فقط بعد التقديم -->
                                    <?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>
                                    <br><small class="text-muted">مقفل بعد التقديم</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($wo['extract_value'], 2); ?> ريال</td>
                            <td>
                                <?php if ($wo['penalty_amount'] > 0): ?>
                                    <span class="text-danger"><?php echo number_format($wo['penalty_amount'], 2); ?> ريال</span>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                switch($wo['demolition_status']) {
                                    case 'attached':
                                        echo '<span class="badge bg-success"><i class="fas fa-check"></i> مرفق</span>';
                                        if ($wo['demolition_file_path']) {
                                            echo '<br><small class="text-muted">تم الرفع: ' . date('Y-m-d', strtotime($wo['demolition_uploaded_at'])) . '</small>';
                                        }
                                        break;
                                    case 'not_applicable':
                                        echo '<span class="badge bg-secondary"><i class="fas fa-minus"></i> غير قابل للتطبيق</span>';
                                        break;
                                    case 'not_attached':
                                    default:
                                        echo '<span class="badge bg-danger"><i class="fas fa-times"></i> غير مرفق</span>';
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا توجد أوامر عمل مرتبطة</h5>
                <p class="text-muted">لم يتم ربط أي أوامر عمل بهذا المستخلص بعد.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // Initialize DataTable for work orders
    if ($('#workOrdersTable').length && !$.fn.DataTable.isDataTable('#workOrdersTable')) {
        $('#workOrdersTable').DataTable({
            "language": {
                "sProcessing": "جارٍ التحميل...",
                "sLengthMenu": "أظهر _MENU_ مدخلات",
                "sZeroRecords": "لم يعثر على أية سجلات",
                "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                "sInfoPostFix": "",
                "sSearch": "ابحث:",
                "sUrl": "",
                "oPaginate": {
                    "sFirst": "الأول",
                    "sPrevious": "السابق",
                    "sNext": "التالي",
                    "sLast": "الأخير"
                }
            },
            "responsive": true,
            "order": [[ 0, "asc" ]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": -1 }
            ]
        });
    }

    // حفظ القيمة الأصلية عند تحميل الصفحة
    $('.approval-stage-select').each(function() {
        $(this).attr('data-original-value', $(this).val());
    });
});

// تحديث مرحلة الاعتماد
function updateApprovalStage(extractId, newStage, selectElement) {
    // إظهار مؤشر التحميل
    const originalHtml = selectElement.innerHTML;
    selectElement.disabled = true;

    // إرسال طلب AJAX
    fetch('update-approval-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            extract_id: extractId,
            approval_stage: newStage
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إظهار رسالة نجاح
            Swal.fire({
                icon: 'success',
                title: 'تم التحديث بنجاح',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            // تحديث الصفحة لإظهار التغييرات
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            // إظهار رسالة خطأ
            Swal.fire({
                icon: 'error',
                title: 'خطأ في التحديث',
                text: data.message || 'حدث خطأ غير متوقع'
            });

            // إعادة القيمة السابقة
            selectElement.value = selectElement.getAttribute('data-original-value') || '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'تعذر الاتصال بالخادم'
        });

        // إعادة القيمة السابقة
        selectElement.value = selectElement.getAttribute('data-original-value') || '';
    })
    .finally(() => {
        selectElement.disabled = false;
    });
}

/**
 * تحديث تاريخ الإنجاز لأمر العمل
 */
function updateCompletionDate(workOrderId, newDate) {
    if (!newDate) {
        Swal.fire({
            icon: 'warning',
            title: 'تاريخ غير صحيح',
            text: 'يرجى اختيار تاريخ صحيح'
        });
        return;
    }

    // تأكيد التحديث
    Swal.fire({
        title: 'تأكيد التحديث',
        text: `هل تريد تحديث تاريخ الإنجاز إلى ${newDate}؟`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، حدث',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            performCompletionDateUpdate(workOrderId, newDate);
        } else {
            // إعادة القيمة السابقة
            const input = document.querySelector(`input[data-work-order-id="${workOrderId}"]`);
            if (input) {
                input.value = input.getAttribute('data-original-value') || input.value;
            }
        }
    });
}

/**
 * تنفيذ تحديث تاريخ الإنجاز
 */
function performCompletionDateUpdate(workOrderId, newDate) {
    const input = document.querySelector(`input[data-work-order-id="${workOrderId}"]`);
    if (!input) return;

    // حفظ القيمة الأصلية
    if (!input.getAttribute('data-original-value')) {
        input.setAttribute('data-original-value', input.value);
    }

    // تعطيل الحقل أثناء التحديث
    input.disabled = true;

    // إعداد البيانات
    const updateData = {
        extract_id: <?php echo $extract_id; ?>,
        completion_dates: {
            [workOrderId]: newDate
        }
    };

    // إرسال الطلب
    fetch('update-completion-dates-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم التحديث بنجاح',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            // تحديث القيمة الأصلية
            input.setAttribute('data-original-value', newDate);

            // إضافة تأثير بصري للنجاح
            input.style.borderColor = '#28a745';
            setTimeout(() => {
                input.style.borderColor = '';
            }, 2000);

        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ في التحديث',
                text: data.message || 'حدث خطأ غير متوقع'
            });

            // إعادة القيمة السابقة
            input.value = input.getAttribute('data-original-value') || input.value;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'تعذر الاتصال بالخادم'
        });

        // إعادة القيمة السابقة
        input.value = input.getAttribute('data-original-value') || input.value;
    })
    .finally(() => {
        input.disabled = false;
    });
}

// تهيئة القيم الأصلية عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.completion-date-input').forEach(input => {
        input.setAttribute('data-original-value', input.value);
    });
});
</script>
