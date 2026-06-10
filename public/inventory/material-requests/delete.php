<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * حذف طلب الصرف
 * Delete Material Request
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_delete')) {
    setAlert('ليس لديك صلاحية لحذف طلبات الصرف', 'error');
    redirect('index.php');
}

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    setAlert('معرف طلب الصرف غير صحيح', 'error');
    redirect('/inventory/material-requests/index.php');
}

$materialRequestModel = new MaterialRequest();

// الحصول على طلب الصرف
$request = $materialRequestModel->findById($requestId);
if (!$request) {
    setAlert('طلب الصرف غير موجود', 'error');
    redirect('/inventory/material-requests/index.php');
}

// التحقق من إمكانية الحذف
if ($request['status'] !== 'draft') {
    setAlert('لا يمكن حذف طلب الصرف بعد إرساله', 'error');
    redirect('/inventory/material-requests/view.php?id=' . $requestId);
}

// التحقق من صلاحية الحذف (المنشئ أو المدير)
if ($request['requested_by'] != $_SESSION['user_id'] && !hasPermission('inventory_requests_edit')) {
    setAlert('ليس لديك صلاحية لحذف هذا الطلب', 'error');
    redirect('/inventory/material-requests/view.php?id=' . $requestId);
}

// معالجة تأكيد الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $materialRequestModel->beginTransaction();
        
        // حذف تفاصيل المواد
        $materialRequestModel->execute(
            "DELETE FROM material_request_details WHERE request_id = ?",
            [$requestId]
        );
        
        // حذف طلب الصرف
        $deleteResult = $materialRequestModel->delete($requestId);
        
        if ($deleteResult) {
            $materialRequestModel->commit();
            logActivity('delete_material_request', "تم حذف طلب الصرف: {$request['request_number']}");
            setAlert('تم حذف طلب الصرف بنجاح', 'success');
            redirect('/inventory/material-requests/index.php');
        } else {
            $materialRequestModel->rollback();
            setAlert('فشل في حذف طلب الصرف', 'error');
        }
        
    } catch (Exception $e) {
        $materialRequestModel->rollback();
        setAlert('حدث خطأ أثناء حذف طلب الصرف: ' . $e->getMessage(), 'error');
    }
}

$pageTitle = 'حذف طلب الصرف - ' . $request['request_number'];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-trash text-danger me-2"></i>
                حذف طلب الصرف
            </h2>
            <p class="text-muted mb-0">حذف طلب الصرف رقم: <?= htmlspecialchars($request['request_number']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="view.php?id=<?= $request['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i>
                    عرض التفاصيل
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
            </div>
        </div>
    </div>

    <!-- تحذير الحذف -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأكيد حذف طلب الصرف
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">
                            <i class="fas fa-warning me-2"></i>
                            تحذير مهم!
                        </h6>
                        <p class="mb-0">
                            أنت على وشك حذف طلب الصرف نهائياً. هذا الإجراء لا يمكن التراجع عنه وسيتم حذف جميع البيانات المرتبطة بالطلب.
                        </p>
                    </div>

                    <!-- معلومات طلب الصرف -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted">معلومات طلب الصرف:</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="fw-bold">رقم الطلب:</td>
                                    <td><?= htmlspecialchars($request['request_number']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">تاريخ الطلب:</td>
                                    <td><?= formatDate($request['request_date']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">تاريخ الحاجة:</td>
                                    <td><?= formatDate($request['required_date']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">الحالة:</td>
                                    <td><span class="badge bg-secondary">مسودة</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted">تفاصيل إضافية:</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="fw-bold">تاريخ الإنشاء:</td>
                                    <td><?= formatDateTime($request['created_at']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">آخر تحديث:</td>
                                    <td><?= formatDateTime($request['updated_at']) ?></td>
                                </tr>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if (!empty($request['notes'])): ?>
                                <tr>
                                    <td class="fw-bold">ملاحظات:</td>
                                    <td><?= htmlspecialchars($request['notes']) ?></td>
                                </tr>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- البيانات التي سيتم حذفها -->
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">البيانات التي سيتم حذفها:</h6>
                        <ul class="mb-0">
                            <li>طلب الصرف الأساسي</li>
                            <li>جميع تفاصيل المواد المطلوبة</li>
                            <li>أي ملاحظات أو تعليقات مرتبطة</li>
                            <li>سجل العمليات المرتبط بالطلب</li>
                        </ul>
                    </div>

                    <!-- نموذج التأكيد -->
                    <form method="POST" class="mt-4">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_understanding" required>
                                    <label class="form-check-label fw-bold text-danger" for="confirm_understanding">
                                        أفهم أن هذا الإجراء لا يمكن التراجع عنه وأريد المتابعة
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" name="confirm_delete" value="1" 
                                        class="btn btn-danger" id="delete-btn" disabled>
                                    <i class="fas fa-trash me-1"></i>
                                    تأكيد الحذف
                                </button>
                            </div>
                            <div>
                                <a href="view.php?id=<?= $request['id'] ?>" class="btn btn-secondary me-2">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    العودة إلى القائمة
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- معلومات إضافية -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        معلومات مهمة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success">متى يمكن حذف طلب الصرف؟</h6>
                            <ul class="text-muted">
                                <li>عندما يكون الطلب في حالة "مسودة" فقط</li>
                                <li>قبل إرسال الطلب للموافقة</li>
                                <li>من قبل منشئ الطلب أو المدير</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger">متى لا يمكن حذف طلب الصرف؟</h6>
                            <ul class="text-muted">
                                <li>بعد إرسال الطلب للموافقة</li>
                                <li>بعد الموافقة على الطلب</li>
                                <li>بعد رفض الطلب (للاحتفاظ بالسجل)</li>
                                <li>إذا تم ربط الطلب بمعاملات أخرى</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل زر الحذف عند تأكيد الفهم
document.getElementById('confirm_understanding').addEventListener('change', function() {
    const deleteBtn = document.getElementById('delete-btn');
    deleteBtn.disabled = !this.checked;
    
    if (this.checked) {
        deleteBtn.classList.remove('btn-secondary');
        deleteBtn.classList.add('btn-danger');
    } else {
        deleteBtn.classList.remove('btn-danger');
        deleteBtn.classList.add('btn-secondary');
    }
});

// تأكيد إضافي عند الضغط على زر الحذف
document.getElementById('delete-btn').addEventListener('click', function(e) {
    if (!confirm('هل أنت متأكد تماماً من حذف طلب الصرف؟ هذا الإجراء نهائي ولا يمكن التراجع عنه.')) {
        e.preventDefault();
    }
});
</script>

<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 include __DIR__ . '/../../includes/layout-end.php'; ?>
