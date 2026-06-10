<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة عرض تفاصيل طلب الصرف
 * View Material Request Details Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';
require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_view')) {
    setAlert('ليس لديك صلاحية لعرض طلبات الصرف', 'error');
    redirect('../../dashboard.php');
}

$requestId = (int) ($_GET['id'] ?? 0);
if ($requestId <= 0) {
    setAlert('معرف طلب الصرف غير صحيح', 'error');
    redirect('index.php');
}

$materialRequestModel = new MaterialRequest();
$workOrderModel = new WorkOrder();

// الحصول على تفاصيل طلب الصرف
$request = $materialRequestModel->fetchOne(
    "SELECT mr.*,
            wo.work_order_number,
            wo.disbursement_status,
            wot.type_code as work_order_type_code, wot.description as work_order_type_description,
            b.name as branch_name, b.code as branch_code,
            u1.full_name as requested_by_name, u1.email as requested_by_email,
            u2.full_name as warehouse_approved_by_name,
            u3.full_name as project_approved_by_name,
            u5.full_name as rejected_by_name
     FROM material_requests mr
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
     LEFT JOIN branches b ON wo.branch_id = b.id
     LEFT JOIN users u1 ON mr.requested_by = u1.id
     LEFT JOIN users u2 ON mr.warehouse_approved_by = u2.id
     LEFT JOIN users u3 ON mr.project_approved_by = u3.id
     LEFT JOIN users u5 ON mr.rejected_by = u5.id
     WHERE mr.id = ?",
    [$requestId]
);

if (!$request) {
    setAlert('طلب الصرف غير موجود', 'error');
    redirect('index.php');
}

// الحصول على تفاصيل المواد
$requestDetails = $materialRequestModel->fetchAll(
    "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
     FROM material_request_details mrd
     JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE mrd.request_id = ?
     ORDER BY m.item_number",
    [$requestId]
);

// حساب الإجماليات
$totalItems = count($requestDetails);
$totalQuantity = array_sum(array_column($requestDetails, 'requested_quantity'));

$pageTitle = 'تفاصيل طلب الصرف - ' . $request['request_number'];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                تفاصيل طلب الصرف
            </h2>
            <p class="text-muted mb-0">عرض تفاصيل طلب الصرف رقم: <?= htmlspecialchars($request['request_number']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
                <?php if (hasPermission('inventory_requests_edit') && $request['status'] === 'draft'): ?>
                    <a href="edit.php?id=<?= $request['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>
                        تعديل
                    </a>
                <?php endif; ?>
                <?php if ($request['status'] === 'draft' && $request['requested_by'] == $_SESSION['user_id']): ?>
                    <button type="button" class="btn btn-primary" onclick="submitRequest()">
                        <i class="fas fa-paper-plane me-1"></i>
                        إرسال الطلب
                    </button>
                <?php endif; ?>
                <a href="export-request-pdf.php?id=<?= $request['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-file-pdf me-1"></i>
                    طباعة / PDF
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- معلومات طلب الصرف -->
        <div class="col-lg-8">
            <!-- معلومات أساسية -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">معلومات طلب الصرف</h5>
                    <?php $statusInfo = getStatusLabel($request['status']); ?>
                    <span class="badge bg-<?= $statusInfo[1] ?> fs-6"><?= $statusInfo[0] ?></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">رقم الطلب:</td>
                                    <td><?= htmlspecialchars($request['request_number']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الطلب:</td>
                                    <td><?= formatDate($request['request_date']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الحاجة:</td>
                                    <td><?= formatDate($request['required_date']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">مقدم الطلب:</td>
                                    <td>
                                        <?= htmlspecialchars($request['requested_by_name'] ?? '') ?>
                                        <br><small
                                            class="text-muted"><?= htmlspecialchars($request['requested_by_email'] ?? '') ?></small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">أمر العمل:</td>
                                    <td>
                                        <a href="../work-orders/view.php?id=<?= $request['work_order_id'] ?>"
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($request['work_order_number'] ?? '') ?>
                                        </a>
                                        <br><small
                                            class="text-muted"><?= htmlspecialchars($request['work_order_type_description'] ?? '') ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">الفرع:</td>
                                    <td><?= htmlspecialchars($request['branch_name'] ?? '') ?>
                                        (<?= htmlspecialchars($request['branch_code'] ?? '') ?>)</td>
                                </tr>

                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                    <td><?= formatDateTime($request['created_at']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($request['notes'])): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold text-muted">ملاحظات:</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- المواد المطلوبة -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">المواد المطلوبة (<?= $totalItems ?> بند)</h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportMaterials()">
                            <i class="fas fa-file-excel me-1"></i>
                            تصدير
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم البند</th>
                                    <th>الوصف</th>
                                    <th>المقايسة</th>
                                    <th>الكمية المطلوبة</th>
                                    <th>الفرق</th>
                                    <th>الوحدة</th>
                                    <?php if (in_array($request['status'], ['approved', 'project_approved', 'branch_approved'])): ?>
                                        <th>الحالة</th>
                                    <?php else: ?>
                                        <th>المخزون الحالي</th>
                                        <th>الحالة</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <?php foreach ($requestDetails as $detail): ?>
                                    <tr data-material-id="<?= $detail['material_id'] ?>">
                                        <td>
                                            <a href="../materials/view.php?id=<?= $detail['material_id'] ?>"
                                                class="text-decoration-none fw-bold" title="عرض تفاصيل المادة">
                                                <?= htmlspecialchars($detail['item_number']) ?>
                                                <i class="fas fa-external-link-alt ms-1" style="font-size: 0.7rem;"></i>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($detail['description']) ?></td>
                                        <td class="estimated-quantity">
                                            <span class="text-muted">جاري التحميل...</span>
                                        </td>
                                        <td>
                                            <strong><?= number_format($detail['requested_quantity'], 3) ?></strong>
                                        </td>
                                        <td class="difference-cell">
                                            <span class="badge bg-secondary">-</span>
                                        </td>
                                        <td><?= htmlspecialchars($detail['unit']) ?></td>
                                        <?php if (in_array($request['status'], ['approved', 'project_approved', 'branch_approved'])): ?>
                                            <td>
                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>تم
                                                    الصرف</span>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <span
                                                    class="badge bg-<?= $detail['current_stock'] >= $detail['requested_quantity'] ? 'success' : 'warning' ?>">
                                                    <?= number_format($detail['current_stock'], 3) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($detail['current_stock'] >= $detail['requested_quantity']): ?>
                                                    <span class="badge bg-success">متوفر</span>
                                                <?php elseif ($detail['current_stock'] > 0): ?>
                                                    <span class="badge bg-warning">متوفر جزئياً</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">غير متوفر</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">الإجمالي</th>
                                    <th>-</th>
                                    <th><?= number_format($totalQuantity, 3) ?></th>
                                    <th>-</th>
                                    <?php if (in_array($request['status'], ['approved', 'project_approved', 'branch_approved'])): ?>
                                        <th>-</th>
                                    <?php else: ?>
                                        <th colspan="2">-</th>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- إحصائيات سريعة -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-1"></i>
                        إحصائيات الطلب
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-2">
                                <h4 class="text-primary mb-0"><?= $totalItems ?></h4>
                                <small class="text-muted">عدد البنود</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-2">
                                <h4 class="text-success mb-0"><?= number_format($totalQuantity, 3) ?></h4>
                                <small class="text-muted">إجمالي الكمية</small>
                            </div>
                        </div>
                    </div>

                    <?php if ($request['status'] !== 'approved'): ?>
                        <?php
                        $availableItems = 0;
                        $partialItems = 0;
                        $unavailableItems = 0;
                        foreach ($requestDetails as $detail) {
                            if ($detail['current_stock'] >= $detail['requested_quantity']) $availableItems++;
                            elseif ($detail['current_stock'] > 0) $partialItems++;
                            else $unavailableItems++;
                        }
                        ?>
                        <div class="mt-3">
                            <h6 class="fw-bold">حالة توفر المواد:</h6>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= ($availableItems / max($totalItems,1)) * 100 ?>%"><?= $availableItems ?></div>
                                <div class="progress-bar bg-warning" style="width: <?= ($partialItems / max($totalItems,1)) * 100 ?>%"><?= $partialItems ?></div>
                                <div class="progress-bar bg-danger" style="width: <?= ($unavailableItems / max($totalItems,1)) * 100 ?>%"><?= $unavailableItems ?></div>
                            </div>
                            <small class="text-muted">
                                <span class="badge bg-success"><?= $availableItems ?></span> متوفر |
                                <span class="badge bg-warning"><?= $partialItems ?></span> جزئي |
                                <span class="badge bg-danger"><?= $unavailableItems ?></span> غير متوفر
                            </small>
                        </div>
                    <?php else: ?>
                        <div class="mt-3">
                            <div class="alert alert-success mb-0 py-2">
                                <i class="fas fa-check-circle me-1"></i>
                                <strong>تم صرف جميع المواد</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- سجل الموافقات -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-1"></i>
                        سجل الموافقات
                    </h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <!-- إنشاء الطلب -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">إنشاء الطلب</h6>
                                <p class="mb-1"><?= htmlspecialchars($request['requested_by_name']) ?></p>
                                <small class="text-muted"><?= formatDateTime($request['created_at']) ?></small>
                            </div>
                        </div>

                        <!-- سجل الاعتمادات الديناميكي -->
                        <?php
                        require_once __DIR__ . '/../../../models/ApprovalAssignment.php';
                        $approvalModel = new ApprovalAssignment();
                        $approvalLogs = $approvalModel->getApprovalLogs($requestId);
                        foreach ($approvalLogs as $log):
                            if ($log['action'] === 'approved') {
                                $markerColor = 'bg-success';
                                $actionLabel = $log['step_name'];
                            } elseif ($log['action'] === 'revision_requested') {
                                $markerColor = 'bg-warning';
                                $actionLabel = 'طلب تعديل - ' . $log['step_name'];
                            } else {
                                $markerColor = 'bg-danger';
                                $actionLabel = 'رفض - ' . $log['step_name'];
                            }
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?= $markerColor ?>"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1"><?= htmlspecialchars($actionLabel) ?></h6>
                                    <p class="mb-1"><?= htmlspecialchars($log['approver_name']) ?></p>
                                    <small class="text-muted"><?= formatDateTime($log['created_at']) ?></small>
                                    <?php if (!empty($log['notes'])): ?>
                                        <p class="small text-muted mt-1"><?= htmlspecialchars($log['notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- رفض الطلب -->
                        <?php if ($request['status'] === 'rejected' && empty($approvalLogs)): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">رفض الطلب</h6>
                                    <p class="mb-1"><?= htmlspecialchars($request['rejected_by_name'] ?? '') ?></p>
                                    <small class="text-muted"><?= formatDateTime($request['rejected_at']) ?></small>
                                    <?php if ($request['rejection_reason']): ?>
                                        <p class="small text-danger mt-1"><?= htmlspecialchars($request['rejection_reason']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- إجراءات الموافقة -->
            <?php
            $currentStep = $materialRequestModel->getCurrentStepForRequest($request);
            $canApproveCurrentStep = false;
            if ($currentStep) {
                $canApproveCurrentStep = canApproveRequestByStep($currentStep['id'], $request['branch_id'] ?? null, $request['work_order_id']);
            }
            ?>
            <?php if ($currentStep && $canApproveCurrentStep): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-check-circle me-1"></i>
                            إجراءات الموافقة
                        </h6>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-success w-100 mb-2"
                            onclick="approveRequest(<?= $request['id'] ?>, <?= $currentStep['id'] ?>, '<?= htmlspecialchars($currentStep['step_name']) ?>')">
                            <i class="fas fa-check me-1"></i>
                            <?= htmlspecialchars($currentStep['step_name']) ?>
                            <?php if ($currentStep['is_final']): ?>
                                <small>(نهائية - سيتم خصم المخزون)</small>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="btn btn-warning w-100 mb-2" onclick="requestRevision(<?= $request['id'] ?>)">
                            <i class="fas fa-edit me-1"></i>
                            طلب تعديل
                        </button>
                        <button type="button" class="btn btn-danger w-100" onclick="rejectRequest(<?= $request['id'] ?>)">
                            <i class="fas fa-times me-1"></i>
                            رفض الطلب
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($request['status'] === 'revision_requested' && $request['requested_by'] == $_SESSION['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0 text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            مطلوب تعديل
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">تم طلب تعديل هذا الطلب. قم بتعديله ثم أعد الإرسال.</p>
                        <a href="edit.php?id=<?= $request['id'] ?>" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-edit me-1"></i>
                            تعديل الطلب
                        </a>
                        <button type="button" class="btn btn-success w-100" onclick="submitRequest()">
                            <i class="fas fa-paper-plane me-1"></i>
                            إعادة إرسال الطلب
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        border-left: 3px solid #007bff;
    }

    /* ألوان تحذيرية للمواد */
    .table-danger {
        background-color: #f8d7da !important;
        border-color: #f5c6cb !important;
    }

    .table-warning {
        background-color: #fff3cd !important;
        border-color: #ffeaa7 !important;
    }

    .excess-request {
        border-left: 4px solid #ffc107 !important;
    }

    .table-danger.excess-request {
        background: linear-gradient(90deg, #f8d7da 0%, #fff3cd 100%) !important;
    }

    .table-warning.excess-request {
        background: linear-gradient(90deg, #fff3cd 0%, #ffe69c 100%) !important;
    }

    /* تحسين مظهر شارات الفرق */
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .badge.bg-warning {
        color: #000 !important;
    }

    /* تحسين مظهر الجدول */
    .table td {
        vertical-align: middle;
    }

    .estimated-quantity {
        font-weight: 600;
        color: #6c757d;
    }

    .difference-cell .badge {
        min-width: 50px;
        text-align: center;
    }

    /* تحسين التحذيرات */
    .alert-warning .alert-heading {
        color: #856404;
    }

    .alert-warning ul li {
        margin-bottom: 0.25rem;
    }

    @media print {

        .btn,
        .card-header .btn-group {
            display: none !important;
        }

        .table-danger,
        .table-warning {
            background-color: #f8f9fa !important;
            border: 2px solid #dee2e6 !important;
        }
    }
</style>

<script>
    // موافقة على الطلب (ديناميكي)
    function approveRequest(requestId, stepId, stepName) {
        const notes = prompt(`ملاحظات ${stepName} (اختياري):`);
        if (notes !== null) {
            updateRequestStatus(requestId, 'approve', stepId, notes);
        }
    }

    // رفض الطلب
    function rejectRequest(requestId) {
        const reason = prompt('يرجى إدخال سبب الرفض:');
        if (reason !== null && reason.trim() !== '') {
            updateRequestStatus(requestId, 'reject', null, reason);
        }
    }

    // طلب تعديل
    function requestRevision(requestId) {
        const notes = prompt('يرجى إدخال ملاحظات التعديل المطلوب:');
        if (notes !== null && notes.trim() !== '') {
            updateRequestStatus(requestId, 'request_revision', null, notes);
        }
    }

    // إرسال الطلب
    function submitRequest() {
        if (confirm('هل أنت متأكد من إعادة إرسال الطلب؟')) {
            updateRequestStatus(<?= $request['id'] ?>, 'submit', null, null);
        }
    }

    function updateRequestStatus(requestId, action, stepId = null, reason = '') {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                action: action,
                step_id: stepId,
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert('حدث خطأ: ' + data.message); }
        })
        .catch(error => { console.error('Error:', error); alert('حدث خطأ في الاتصال'); });
    }

    // تحميل معلومات المقايسة والفروقات
    async function loadEstimatedQuantities() {
        try {
            const response = await fetch(`get-estimated-quantities.php?request_id=<?= $request['id'] ?>`);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            console.log('API Response:', text);

            const data = JSON.parse(text);

            if (!data.success) {
                console.warn('تعذر تحميل معلومات المقايسة:', data.message);
                return;
            }

            // تحديث الجدول بالمعلومات الجديدة
            data.materials.forEach(material => {
                const row = document.querySelector(`tr[data-material-id="${material.material_id}"]`);
                if (row) {
                    // تحديث المقايسة
                    const estimatedCell = row.querySelector('.estimated-quantity');
                    if (estimatedCell) {
                        estimatedCell.innerHTML = material.estimated_quantity > 0 ?
                            `<strong>${material.estimated_quantity.toFixed(3)}</strong>` :
                            '<span class="text-muted">-</span>';
                    }

                    // تحديث الفرق
                    const differenceCell = row.querySelector('.difference-cell');
                    if (differenceCell) {
                        differenceCell.innerHTML = `<span class="badge ${material.difference_class}" title="${getDifferenceTitle(material.difference_type)}">${material.difference_text}</span>`;
                    }

                    // إضافة ألوان تحذيرية للصف
                    if (material.row_class) {
                        row.className = material.row_class;

                        // إضافة tooltip للصف
                        if (material.difference_type === 'excess') {
                            row.title = `تحذير: الكمية المطلوبة أكبر من المقايسة بـ ${Math.abs(material.difference).toFixed(3)} ${material.unit}`;
                        } else if (material.stock_status === 'unavailable') {
                            row.title = 'تحذير: المادة غير متوفرة في المخزون';
                        } else if (material.stock_status === 'insufficient') {
                            row.title = `تحذير: المخزون غير كافي (متوفر: ${material.current_stock.toFixed(3)})`;
                        }
                    }
                }
            });

            // إضافة تحذيرات عامة إذا وجدت
            if (data.statistics.has_warnings) {
                showWarningsSummary(data.statistics);
            }

        } catch (error) {
            console.error('خطأ في تحميل معلومات المقايسة:', error);
        }
    }

    // عرض ملخص التحذيرات
    function showWarningsSummary(stats) {
        const warningsHtml = [];

        if (stats.excess_requests > 0) {
            warningsHtml.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> ${stats.excess_requests} مادة مطلوبة بكمية أكبر من المقايسة`);
        }

        if (stats.unavailable_materials > 0) {
            warningsHtml.push(`<i class="fas fa-times-circle text-danger me-1"></i> ${stats.unavailable_materials} مادة غير متوفرة في المخزون`);
        }

        if (stats.insufficient_stock > 0) {
            warningsHtml.push(`<i class="fas fa-exclamation-circle text-warning me-1"></i> ${stats.insufficient_stock} مادة بمخزون غير كافي`);
        }

        if (warningsHtml.length > 0) {
            const alertHtml = `
            <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>تحذيرات المواد:</h6>
                <ul class="mb-0">
                    ${warningsHtml.map(warning => `<li>${warning}</li>`).join('')}
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

            const container = document.querySelector('.container-fluid');
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = alertHtml;
            container.insertBefore(tempDiv.firstElementChild, container.firstElementChild);
        }
    }

    // الحصول على عنوان الفرق
    function getDifferenceTitle(type) {
        switch (type) {
            case 'excess': return 'صرف زائد عن المقايسة';
            case 'shortage': return 'صرف أقل من المقايسة';
            case 'exact': return 'مطابق للمقايسة';
            default: return '';
        }
    }

    // تصدير المواد
    function exportMaterials() {
        window.location.href = `export-materials.php?id=<?= $request['id'] ?>`;
    }



    // تحميل المعلومات عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function () {
        loadEstimatedQuantities();
    });
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>