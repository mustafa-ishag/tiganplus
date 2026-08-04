<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'أوامر العمل';
$currentPage = 'work-orders';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أوامر العمل', 'url' => 'work-orders/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_view')) {   
    header('Location: ' . path('dashboard.php'));
    exit();
}

// جلب البيانات
try {
    $db = getDB();

    // التحقق من طلب عرض جميع الأوامر (بما فيها المكتملة)
    $showCompleted = isset($_GET['show_completed']) && $_GET['show_completed'] === '1';

    // جلب إحصائيات جميع الأوامر (بما فيها المكتملة) للعرض في البطاقات
    $statsQuery = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            -- أوامر نشطة (لم تدخل مستخلص أو دخلت مستخلص جزئي فقط)
            SUM(CASE WHEN status = 'active' AND (
                (SELECT COUNT(*) FROM partial_extract_work_orders WHERE work_order_id = work_orders.id) > 0
                AND (SELECT COUNT(*) FROM final_regular_extract_work_orders WHERE work_order_id = work_orders.id) = 0
                AND (SELECT COUNT(*) FROM final_for_partial_extract_work_orders WHERE work_order_id = work_orders.id) = 0
            ) OR (
                (SELECT COUNT(*) FROM partial_extract_work_orders WHERE work_order_id = work_orders.id) = 0
                AND (SELECT COUNT(*) FROM final_regular_extract_work_orders WHERE work_order_id = work_orders.id) = 0
                AND (SELECT COUNT(*) FROM final_for_partial_extract_work_orders WHERE work_order_id = work_orders.id) = 0
            ) THEN 1 ELSE 0 END) as active_no_extract,
            -- أوامر جاري صرف المستخلص (دخلت مستخلص نهائي ولم تصرف)
            SUM(CASE WHEN status = 'active' AND disbursement_status IN ('none', 'disbursement') AND (
                (SELECT COUNT(*) FROM final_regular_extract_work_orders WHERE work_order_id = work_orders.id) > 0
                OR (SELECT COUNT(*) FROM final_for_partial_extract_work_orders WHERE work_order_id = work_orders.id) > 0
            ) THEN 1 ELSE 0 END) as disbursement_pending,
            -- شهادات الإنجاز المرفقة
            SUM(CASE WHEN (SELECT status FROM work_order_attachments WHERE work_order_id = work_orders.id AND form_type = 'completion_certificate' LIMIT 1) = 'attached' THEN 1 ELSE 0 END) as completion_certificate_attached,
            -- شهادات الإنجاز الغير مرفقة
            SUM(CASE WHEN (SELECT status FROM work_order_attachments WHERE work_order_id = work_orders.id AND form_type = 'completion_certificate' LIMIT 1) = 'not_attached' THEN 1 ELSE 0 END) as completion_certificate_not_attached
        FROM work_orders
    ";
    $statsStmt = $db->query($statsQuery);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // حساب عدد السجلات المعروضة حالياً
    $displayedQuery = "SELECT COUNT(*) as displayed FROM work_orders";
    if (!$showCompleted) {
        $displayedQuery .= " WHERE status != 'completed'";
    }
    $displayedStmt = $db->query($displayedQuery);
    $displayedCount = $displayedStmt->fetch()['displayed'];
    
    // جلب الفروع
    $stmt = $db->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب أنواع أوامر العمل
    $stmt = $db->query("SELECT * FROM work_order_types WHERE status = 'active' ORDER BY type_code");
    $workOrderTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب الجهات الحالية
    $currentEntities = [];
    try {
        $stmt = $db->query("SELECT * FROM current_entities WHERE is_active = 1 ORDER BY name");
        $currentEntities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // الجدول غير موجود، نتجاهل الخطأ
    }

    // جلب الأقسام المتاحة من أوامر العمل
    $stmt = $db->query("SELECT DISTINCT department FROM work_orders WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // جلب حالات شهادة الإنجاز المتاحة
    $completionCertificateStatuses = [
        'attached' => 'مرفق',
        'not_attached' => 'غير مرفق',
        'not_applicable' => 'لا ينطبق'
    ];

    // جلب حالات تأكيد الشهادة المتاحة
    $certificateConfirmations = [
        'empty' => 'فارغ',
        'confirmed' => 'مؤكد',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض'
    ];

    // جلب حالات الصرف المتاحة (يجب أن تطابق القيم في قاعدة البيانات)
    $disbursementStatuses = [
        'none' => 'لا يوجد',
        'completed' => 'مكتمل',
        'disbursement' => 'صرف',
        'return' => 'إرجاع',
        'disbursement_return_completed' => 'صرف وإرجاع'
    ];

    // No automatic number generation needed

} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $branches = [];
    $workOrderTypes = [];
    $currentEntities = [];
    $departments = [];
    $completionCertificateStatuses = [];
    $certificateConfirmations = [];
    $disbursementStatuses = [];
    $stats = ['total' => 0, 'active' => 0, 'completed' => 0];
    $displayedCount = 0;
    $showCompleted = false;
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">أوامر العمل</h4>
        <p class="text-muted mb-0 small">إدارة ومتابعة جميع أوامر العمل في النظام</p>
    </div>
    <div>
        <?php if (hasPermission('work_orders_create')): ?>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openCreateModal()">
            <i class="fas fa-plus me-2"></i>
            إضافة أمر عمل جديد
        </button>
        <?php endif; ?>

        <!-- Import/Export Dropdown -->
        <div class="btn-group ms-2" role="group">
            <button type="button" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-success fw-bold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-exchange-alt me-2"></i>
                استيراد/تصدير
            </button>
            <ul class="dropdown-menu shadow border-0" style="border-radius: 12px;">
                <?php if (hasPermission('work_orders_export')): ?>
                <li>
                    <a class="dropdown-item py-2" href="#" onclick="openExportModal()">
                        <i class="fas fa-file-export me-2 text-success"></i>
                        تصدير البيانات
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('work_orders_import')): ?>
                <li>
                    <a class="dropdown-item py-2" href="import.php">
                        <i class="fas fa-file-import me-2 text-primary"></i>
                        استيراد البيانات
                    </a>
                </li>
                <?php endif; ?>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item py-2" href="download-sample.php">
                        <i class="fas fa-download me-2 text-muted"></i>
                        تحميل نموذج CSV
                    </a>
                </li>
            </ul>
        </div>

        <?php if (hasPermission('work_orders_print')): ?>
        <button type="button" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-info fw-bold ms-2" onclick="printWorkOrders()">
            <i class="fas fa-print me-2"></i>
            طباعة
        </button>
        <?php endif; ?>

        <!-- زر التقارير -->
        <a href="reports.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-warning fw-bold ms-2">
            <i class="fas fa-chart-line me-2"></i>
            التقارير الشاملة
        </a>
    </div>
</div>

<!-- زر عرض/إخفاء الأوامر المكتملة -->
<div class="dash-card p-3 mb-4 bg-primary-soft border-0 d-flex align-items-center justify-content-between">
    <div class="text-primary">
        <i class="fas fa-info-circle me-2 fs-5 align-middle"></i>
        <?php if ($showCompleted): ?>
            <span class="fw-bold">عرض جميع أوامر العمل</span> <span class="text-muted small">(بما فيها المكتملة)</span>
        <?php else: ?>
            <span class="fw-bold">عرض أوامر العمل النشطة فقط</span> <span class="text-muted small">(الأوامر المكتملة مخفية)</span>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($showCompleted): ?>
            <a href="index.php" class="btn btn-white rounded-pill btn-sm shadow-sm text-warning fw-bold px-3">
                <i class="fas fa-eye-slash me-1"></i>
                إخفاء المكتملة
            </a>
        <?php else: ?>
            <a href="index.php?show_completed=1" class="btn btn-white rounded-pill btn-sm shadow-sm text-primary fw-bold px-3">
                <i class="fas fa-eye me-1"></i>
                عرض الكل
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">إجمالي أوامر العمل</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['total']) ?></div>
                </div>
                <div class="icon-circle bg-primary-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-list-alt text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">أوامر نشطة قيد التنفيذ</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['active_no_extract'] ?? 0) ?></div>
                </div>
                <div class="icon-circle bg-success-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-play-circle text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">أوامر جاري صرف مستخلصها</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['disbursement_pending'] ?? 0) ?></div>
                </div>
                <div class="icon-circle bg-warning-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-hourglass-half text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">شهادات إنجاز مرفقة</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['completion_certificate_attached'] ?? 0) ?></div>
                </div>
                <div class="icon-circle bg-info-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-certificate text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">أوامر متأخرة الإنجاز</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['completion_certificate_not_attached'] ?? 0) ?></div>
                </div>
                <div class="icon-circle bg-danger-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">أوامر عمل مكتملة كلياً</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($stats['completed']) ?></div>
                </div>
                <div class="icon-circle bg-secondary-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-flag-checkered text-secondary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Section - Compact Design -->
<div class="card dash-card mb-4 border-0">
    <div class="card-header py-3 border-0" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); cursor: pointer; border-radius: 20px 20px 0 0;" id="filtersHeader" title="إظهار/إخفاء الفلاتر">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-white fw-bold">
                <i class="fas fa-filter me-2"></i>
                الفلاتر والبحث
            </h6>
            <div class="text-white opacity-75">
                <span id="toggleFiltersText" class="me-2 small fw-bold">إظهار</span>
                <i class="fas fa-chevron-down" style="transition: transform 0.3s ease; transform: rotate(180deg);"></i>
            </div>
        </div>
    </div>
    <div class="card-body py-3" id="filtersContainer">
        <!-- الفلاتر الأساسية -->
        <div class="row g-3 mb-3">
            <div class="col-lg-2 col-md-3">
                <label for="filterDepartment" class="form-label small fw-bold mb-2">
                    <i class="fas fa-building me-1"></i>القسم
                </label>
                <select class="form-select form-select-sm" id="filterDepartment">
                    <option value="">الكل</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterCurrentEntity" class="form-label small fw-bold mb-2">
                    <i class="fas fa-sitemap me-1"></i>الجهة الحالية
                </label>
                <select class="form-select form-select-sm" id="filterCurrentEntity">
                    <option value="">الكل</option>
                    <?php foreach ($currentEntities as $entity): ?>
                        <option value="<?= $entity['id'] ?>"><?= htmlspecialchars($entity['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterBranch" class="form-label small fw-bold mb-2">
                    <i class="fas fa-code-branch me-1"></i>الفرع
                </label>
                <select class="form-select form-select-sm" id="filterBranch">
                    <option value="">الكل</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterCompletionCertificate" class="form-label small fw-bold mb-2">
                    <i class="fas fa-certificate me-1"></i>شهادة الإنجاز
                </label>
                <select class="form-select form-select-sm" id="filterCompletionCertificate" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterCertificateConfirmation" class="form-label small fw-bold mb-2">
                    <i class="fas fa-check-circle me-1"></i>تأكيد الشهادة
                </label>
                <select class="form-select form-select-sm" id="filterCertificateConfirmation" multiple>
                    <option value="confirmed">مؤكد</option>
                    <option value="pending">قيد المراجعة</option>
                    <option value="rejected">مرفوض</option>
                    <option value="empty">فارغ</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterDisbursementStatus" class="form-label small fw-bold mb-2">
                    <i class="fas fa-money-bill-wave me-1"></i>حالة الصرف
                </label>
                <select class="form-select form-select-sm" id="filterDisbursementStatus" multiple>
                    <option value="not_disbursed">لم يصرف</option>
                    <option value="partially_disbursed">صرف جزئي</option>
                    <option value="fully_disbursed">صرف كامل</option>
                </select>
            </div>
        </div>

        <!-- فلاتر النماذج والتواريخ -->
        <div class="row g-3 mb-3">
            <div class="col-lg-2 col-md-3">
                <label for="filterPreciseDrilling" class="form-label small fw-bold mb-2">
                    <i class="fas fa-crosshairs me-1"></i>الحفر الدقيق
                </label>
                <select class="form-select form-select-sm" id="filterPreciseDrilling" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterExcavation" class="form-label small fw-bold mb-2">
                    <i class="fas fa-hard-hat me-1"></i>الكشط
                </label>
                <select class="form-select form-select-sm" id="filterExcavation" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterDemolition" class="form-label small fw-bold mb-2">
                    <i class="fas fa-recycle me-1"></i>التخريد
                </label>
                <select class="form-select form-select-sm" id="filterDemolition" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterF1Form" class="form-label small fw-bold mb-2">
                    <i class="fas fa-file-alt me-1"></i>F1
                </label>
                <select class="form-select form-select-sm" id="filterF1Form" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterAssetsReceipt" class="form-label small fw-bold mb-2">
                    <i class="fas fa-clipboard-check me-1"></i>استلام الأصول
                </label>
                <select class="form-select form-select-sm" id="filterAssetsReceipt" multiple>
                    <option value="attached">مرفق</option>
                    <option value="not_attached">غير مرفق</option>
                    <option value="not_applicable">لا ينطبق</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="filterDateFrom" class="form-label small fw-bold mb-2">
                    <i class="fas fa-calendar-alt me-1"></i>من تاريخ
                </label>
                <input type="date" class="form-control form-control-sm" id="filterDateFrom">
            </div>
        </div>

        <!-- صف ثالث للتواريخ والأزرار -->
        <div class="row g-3">
            <div class="col-lg-2 col-md-3">
                <label for="filterDateTo" class="form-label small fw-bold mb-2">
                    <i class="fas fa-calendar-alt me-1"></i>إلى تاريخ
                </label>
                <input type="date" class="form-control form-control-sm" id="filterDateTo">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label small fw-bold mb-2 d-block">&nbsp;</label>
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1 shadow-sm rounded-pill fw-bold" id="applyFilters" title="تطبيق الفلاتر والبحث">
                        <i class="fas fa-search me-1"></i>
                        بحث
                    </button>
                    <button type="button" class="btn btn-light btn-sm shadow-sm rounded-pill border-0 text-danger px-3" id="resetFilters" title="إعادة تعيين جميع الفلاتر">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- الفلاتر السريعة -->
        <div class="row mt-2">
            <div class="col-12 mb-2">
                <label class="form-label fw-bold text-muted small"><i class="fas fa-bolt me-1 text-warning"></i> فلاتر سريعة بنقرة واحدة:</label>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm px-3 quick-filter rounded-pill shadow-sm fw-bold" data-filter="favorites">
                        <i class="fas fa-star me-1"></i> المفضلة
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm px-3 quick-filter rounded-pill shadow-sm fw-bold" data-filter="confirmed_no_extract">
                        <i class="fas fa-certificate me-1"></i> شهادة مؤكدة ولم تدخل مستخلص
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm px-3 quick-filter rounded-pill shadow-sm fw-bold" data-filter="attached_cert_no_extract">
                        <i class="fas fa-file-check me-1"></i> شهادة مرفقة ولم تدخل مستخلص
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm px-3 quick-filter rounded-pill shadow-sm fw-bold" data-filter="missing_drilling_scraping">
                        <i class="fas fa-exclamation-triangle me-1"></i> نماذج حفر دقيق وكشط غير مرفق
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 quick-filter rounded-pill shadow-sm fw-bold" data-filter="missing_scrap">
                        <i class="fas fa-trash-alt me-1"></i> نماذج تخريد غير مرفق
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Work Orders Table -->
<div class="card dash-card shadow-sm border-0">
    <div class="card-header bg-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                قائمة أوامر العمل
                <span class="badge bg-primary ms-2" id="tableRecordCount"><?= number_format($displayedCount) ?> أمر عمل</span>
            </h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light rounded-pill btn-sm px-3 shadow-sm border-0 fw-bold text-success" id="exportCurrentTableBtn" title="تصدير الجدول الحالي إلى Excel">
                    <i class="fas fa-file-excel me-1"></i>
                    تصدير
                </button>
                <button type="button" class="btn btn-light rounded-pill btn-sm px-3 shadow-sm border-0 fw-bold text-info" id="toggleFormsViewBtn">
                    <i class="fas fa-file-alt me-1"></i>
                    وضع النماذج
                </button>
                <button type="button" class="btn btn-light rounded-pill btn-sm px-3 shadow-sm border-0 fw-bold text-primary" id="toggleFullViewBtn">
                    <i class="fas fa-th me-1"></i>
                    الوضع الكامل
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Search Box -->
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-primary text-white">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" id="workOrdersTableSearch" placeholder="ابحث في الجدول...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="workOrdersTable" width="100%" cellspacing="0" style="color: #475569;">
                <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b;">
                    <tr>
                        <th style="width: 50px;">★</th>
                        <th>رقم الأمر</th>
                        <th>نوع الأمر</th>
                        <th>القسم</th>
                        <th>الجهة الحالية</th>
                        <th>الفرع</th>
                        <th>الموقع</th>
                        <th>رقم المستخلص</th>
                        <th>تاريخ التكليف</th>
                        <th>القيمة الفعلية</th>
                        <th>شهادة الإنجاز</th>
                        <th>تاريخ ارفاق الشهادة</th>
                        <th>تأكيد الشهادة</th>
                        <th>تاريخ تأكيد الشهادة</th>
                        <th>حالة الصرف</th>
                        <th>الحالة</th>
                        <th>الإنجاز (%)</th>
                        <th class="forms-column">الحفر الدقيق</th>
                        <th class="forms-column">الكشط</th>
                        <th class="forms-column">التخريد</th>
                        <th class="forms-column">F1</th>
                        <th class="forms-column">استلام الأصول (211)</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- البيانات سيتم تحميلها عبر AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript moved to after layout inclusion -->



<!-- نافذة إضافة أمر عمل -->
<div class="modal fade" id="createWorkOrderModal" tabindex="-1" aria-labelledby="createWorkOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createWorkOrderModalLabel">
                    <i class="fas fa-plus-circle text-primary me-2"></i>
                    إضافة أمر عمل جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createWorkOrderForm">
                    <div class="row">
                        <!-- رقم أمر العمل -->
                        <div class="col-md-6 mb-3">
                            <label for="work_order_number" class="form-label">
                                رقم أمر العمل <span class="text-danger">*</span>
                                <small class="text-muted">(9 أرقام فقط)</small>
                            </label>
                            <input type="text" class="form-control" id="work_order_number" name="work_order_number"
                                   required maxlength="9" pattern="[0-9]{9}"
                                   placeholder="123456789"
                                   title="يجب إدخال 9 أرقام فقط">
                            <div class="form-text">
                                <small id="work_order_number_help" class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    أدخل 9 أرقام بالضبط
                                </small>
                            </div>
                        </div>

                        <!-- نوع أمر العمل -->
                        <div class="col-md-6 mb-3">
                            <label for="work_order_type_id" class="form-label">
                                نوع أمر العمل <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="work_order_type_id" name="work_order_type_id" required>
                                <option value="">اختر نوع أمر العمل</option>
                                <?php foreach ($workOrderTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>">
                                        <?= htmlspecialchars($type['type_code']) ?> - <?= htmlspecialchars($type['description']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- القسم -->
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">
                                القسم <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">اختر القسم</option>
                                <option value="connections">التوصيلات</option>
                                <option value="projects">المشاريع</option>
                            </select>
                        </div>

                        <!-- الفرع -->
                        <div class="col-md-6 mb-3">
                            <label for="branch_id" class="form-label">
                                الفرع <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="branch_id" name="branch_id" required>
                                <option value="">اختر الفرع</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>" data-code="<?= $branch['code'] ?>">
                                        <?= htmlspecialchars($branch['name']) ?> (<?= htmlspecialchars($branch['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- الموقع -->
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">الموقع</label>
                            <input type="text" class="form-control" id="location" name="location"
                                   placeholder="أدخل موقع تنفيذ أمر العمل"
                                   maxlength="255">
                            <div class="form-text">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    موقع تنفيذ أمر العمل (اختياري)
                                </small>
                            </div>
                        </div>

                        <!-- تاريخ التكليف -->
                        <div class="col-md-6 mb-3">
                            <label for="assignment_date" class="form-label">تاريخ التكليف</label>
                            <input type="date" class="form-control" id="assignment_date" name="assignment_date">
                        </div>

                        <!-- تاريخ الاستلام -->
                        <div class="col-md-6 mb-3">
                            <label for="receipt_date" class="form-label">تاريخ الاستلام</label>
                            <input type="date" class="form-control" id="receipt_date" name="receipt_date">
                        </div>

                        <!-- القيمة المقدرة -->
                        <div class="col-md-6 mb-3">
                            <label for="estimated_value" class="form-label">القيمة المقدرة (ريال)</label>
                            <input type="text" class="form-control estimated-value-create-input" id="estimated_value" name="estimated_value"
                                   placeholder="0.00" style="text-align: right;"
                                   onblur="formatCreateEstimatedValue(this)"
                                   onfocus="unformatCreateEstimatedValue(this)">
                        </div>

                        <!-- القيمة الفعلية -->
                        <div class="col-md-6 mb-3">
                            <label for="actual_value" class="form-label">القيمة الفعلية (ريال)</label>
                            <input type="text" class="form-control actual-value-create-input" id="actual_value" name="actual_value"
                                   placeholder="0.00" style="text-align: right;"
                                   onblur="formatCreateActualValue(this)"
                                   onfocus="unformatCreateActualValue(this)">
                        </div>

                        <!-- حالة الصرف -->
                        <div class="col-md-6 mb-3">
                            <label for="disbursement_status" class="form-label">حالة الصرف</label>
                            <select class="form-select" id="disbursement_status" name="disbursement_status">
                                <option value="none">لا يوجد</option>
                                <option value="pending_disbursement">في انتظار الصرف</option>
                                <option value="disbursement">تم الصرف</option>
                                <option value="completed">مكتمل</option>
                                <option value="return">مرتجع</option>
                                <option value="partial_disbursement">صرف جزئي</option>
                                <option value="cancelled_disbursement">صرف ملغي</option>
                            </select>
                        </div>

                        <!-- الحالة -->
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">حالة أمر العمل</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">نشط</option>
                                <option value="inactive">غير نشط</option>
                                <option value="completed">مكتمل</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>

                        <!-- الملاحظات -->
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">الملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="أدخل أي ملاحظات إضافية حول أمر العمل"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="submitCreateForm()">
                    <i class="fas fa-save me-1"></i>
                    حفظ أمر العمل
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة عرض تفاصيل أمر العمل -->
<div class="modal fade" id="viewWorkOrderModal" tabindex="-1" aria-labelledby="viewWorkOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewWorkOrderModalLabel">
                    <i class="fas fa-eye text-info me-2"></i>
                    تفاصيل أمر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewWorkOrderModalBody">
                <!-- سيتم تحميل المحتوى هنا -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة تعديل أمر العمل -->
<div class="modal fade" id="editWorkOrderModal" tabindex="-1" aria-labelledby="editWorkOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editWorkOrderModalLabel">
                    <i class="fas fa-edit text-warning me-2"></i>
                    تعديل أمر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editWorkOrderModalBody">
                <!-- سيتم تحميل المحتوى هنا -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">
                    <i class="fas fa-save me-1"></i>
                    حفظ التعديلات
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-file-export text-success me-2"></i>
                    تصدير أوامر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="row">
                        <!-- صيغة الملف -->
                        <div class="col-md-6 mb-3">
                            <label for="export_format" class="form-label">صيغة الملف</label>
                            <select class="form-select" id="export_format" name="format">
                                <option value="xlsx">Excel (تنسيق جميل)</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>

                        <!-- حالة أوامر العمل -->
                        <div class="col-md-6 mb-3">
                            <label for="export_status" class="form-label">حالة أوامر العمل</label>
                            <select class="form-select" id="export_status" name="status">
                                <option value="all">جميع الحالات</option>
                                <option value="active">نشط</option>
                                <option value="completed">مكتمل</option>
                                <option value="cancelled">ملغي</option>
                                <option value="inactive">غير نشط</option>
                            </select>
                        </div>

                        <!-- القسم -->
                        <div class="col-md-6 mb-3">
                            <label for="export_department" class="form-label">القسم</label>
                            <select class="form-select" id="export_department" name="department">
                                <option value="all">جميع الأقسام</option>
                                <option value="connections">التوصيلات</option>
                                <option value="projects">المشاريع</option>
                            </select>
                        </div>

                        <!-- الفرع -->
                        <div class="col-md-6 mb-3">
                            <label for="export_branch" class="form-label">الفرع</label>
                            <select class="form-select" id="export_branch" name="branch_id">
                                <option value="all">جميع الفروع</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>">
                                        <?= htmlspecialchars($branch['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- خيارات إضافية -->
                        <div class="col-12 mb-3">
                            <label class="form-label">خيارات إضافية</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_extracts" name="include_extracts" value="1" checked>
                                <label class="form-check-label" for="include_extracts">
                                    تضمين تفاصيل المستخلصات
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_attachments" name="include_attachments" value="1" checked>
                                <label class="form-check-label" for="include_attachments">
                                    تضمين حالة النماذج المرفقة
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-success" onclick="startExport()">
                    <i class="fas fa-download me-1"></i>
                    بدء التصدير
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript moved to after layout inclusion -->


<!-- All JavaScript moved to after layout inclusion -->
<!-- All JavaScript content removed and moved to after layout inclusion -->










<!-- All JavaScript content has been moved to after layout inclusion to fix "$ is not defined" error -->

<!-- إضافة CSS مخصص لعمود رقم المستخلص -->
<style>
.extract-number-cell {
    min-width: 120px;
    text-align: center;
}

.extract-number-cell .text-primary {
    color: #0d6efd !important;
}

.extract-number-cell .text-success {
    color: #198754 !important;
}

.extract-number-cell .text-warning {
    color: #fd7e14 !important;
}

.extract-number-cell strong {
    font-size: 0.9em;
    font-weight: 600;
}

.extract-number-cell small {
    font-size: 0.75em;
    font-style: italic;
}

/* تحسين عرض الجدول */
#workOrdersTable th:nth-child(6) {
    min-width: 120px;
    text-align: center;
}

#workOrdersTable td:nth-child(6) {
    text-align: center;
    vertical-align: middle;
}

/* تحسين الألوان */
.text-primary {
    font-weight: 500;
}

.text-success {
    font-weight: 500;
}

.text-warning {
    font-weight: 500;
}

.text-muted {
    font-size: 0.85em;
}

/* تحسين الروابط في عمود رقم المستخلص */
.extract-number-cell a {
    transition: all 0.2s ease;
}

.extract-number-cell a:hover {
    text-decoration: underline !important;
    transform: scale(1.05);
}

.extract-number-cell a.text-primary:hover {
    color: #0a58ca !important;
}

.extract-number-cell a.text-success:hover {
    color: #146c43 !important;
}

.extract-number-cell a.text-warning:hover {
    color: #e25e0e !important;
}

.extract-number-cell .fas.fa-external-link-alt {
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.extract-number-cell a:hover .fas.fa-external-link-alt {
    opacity: 1;
}

/* تحسين أعمدة شهادة الإنجاز */
.completion-certificate-status-select,
.completion-certificate-confirmation-select {
    font-size: 0.8em;
    min-width: 80px;
    width: 100%;
}

/* تحسين عناصر الأعمدة الأساسية */
.current-entity-select,
.disbursement-status-select,
.status-select {
    font-size: 0.8em;
    min-width: 75px;
    width: 100%;
}

/* تحسين حقل القيمة الفعلية */
.actual-value-input {
    font-size: 0.9em;
    min-width: 120px;
    width: 100%;
    text-align: center;
    font-weight: bold;
}

.completion-certificate-status-select option[value="not_attached"] {
    color: #dc3545;
}

.completion-certificate-status-select option[value="attached"] {
    color: #198754;
}

.completion-certificate-status-select option[value="not_applicable"] {
    color: #6c757d;
}

.completion-certificate-confirmation-select option[value="empty"] {
    color: #6c757d;
}

.completion-certificate-confirmation-select option[value="confirmed"] {
    color: #0d6efd;
}

.completion-certificate-confirmation-select option[value="accepted"] {
    color: #198754;
}

.completion-certificate-confirmation-select option[value="rejected"] {
    color: #dc3545;
}

/* تحسين عرض ملف شهادة الإنجاز */
.btn-outline-primary.btn-sm {
    font-size: 0.75em;
    padding: 0.25rem 0.5rem;
}

.btn-outline-primary.btn-sm i {
    margin-right: 0.25rem;
}

/* تحسين عرض الجدول - الأعمدة الأساسية */
#workOrdersTable th:nth-child(3), /* القسم */
#workOrdersTable th:nth-child(4), /* الجهة الحالية */
#workOrdersTable th:nth-child(11), /* حالة الصرف */
#workOrdersTable th:nth-child(12) { /* الحالة */
    min-width: 80px;
    text-align: center;
}

#workOrdersTable td:nth-child(3),
#workOrdersTable td:nth-child(4),
#workOrdersTable td:nth-child(11),
#workOrdersTable td:nth-child(12) {
    text-align: center;
    vertical-align: middle;
    min-width: 80px;
}

/* القيمة الفعلية - تبقى كما هي */
#workOrdersTable th:nth-child(8) {
    min-width: 160px;
    text-align: center;
}

#workOrdersTable td:nth-child(8) {
    text-align: center;
    vertical-align: middle;
    min-width: 160px;
}

/* تحسين عرض أعمدة شهادة الإنجاز */
#workOrdersTable th:nth-child(9),
#workOrdersTable th:nth-child(10) {
    min-width: 85px;
    text-align: center;
}

#workOrdersTable td:nth-child(9),
#workOrdersTable td:nth-child(10) {
    text-align: center;
    vertical-align: middle;
    min-width: 85px;
}

/* ألوان ديناميكية لحالة شهادة الإنجاز */
.certificate-status-not_attached {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    background-color: #f8d7da !important;
}

.certificate-status-attached {
    color: #198754 !important;
    border-color: #198754 !important;
    background-color: #d1e7dd !important;
}

.certificate-status-not_applicable {
    color: #6c757d !important;
    border-color: #6c757d !important;
    background-color: #e9ecef !important;
}

/* ألوان ديناميكية لتأكيد شهادة الإنجاز */
.confirmation-status-empty {
    color: #6c757d !important;
    border-color: #6c757d !important;
    background-color: #e9ecef !important;
}

.confirmation-status-confirmed {
    color: #0d6efd !important;
    border-color: #0d6efd !important;
    background-color: #cfe2ff !important;
}

.confirmation-status-accepted {
    color: #198754 !important;
    border-color: #198754 !important;
    background-color: #d1e7dd !important;
}

.confirmation-status-rejected {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    background-color: #f8d7da !important;
}

/* ألوان ديناميكية لحالة النماذج */
.form-status-not_attached {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    background-color: #f8d7da !important;
}

.form-status-attached {
    color: #198754 !important;
    border-color: #198754 !important;
    background-color: #d1e7dd !important;
}

.form-status-not_applicable {
    color: #6c757d !important;
    border-color: #6c757d !important;
    background-color: #e9ecef !important;
}

/* تحسينات إضافية للجدول */
#workOrdersTable {
    font-size: 0.95em;
}

#workOrdersTable th {
    font-size: 0.9em;
    font-weight: 600;
    white-space: nowrap;
    padding: 12px 8px;
}

#workOrdersTable td {
    padding: 10px 8px;
    vertical-align: middle;
}

/* تثبيت رأس الجدول - يتم عبر JavaScript */
#workOrdersTable-sticky-header {
    position: fixed;
    top: 0;
    z-index: 1020;
    display: none;
    background: #212529;
    box-shadow: 0 3px 8px rgba(0,0,0,0.3);
    overflow-x: scroll;
    overflow-y: hidden;
    pointer-events: none;
    scrollbar-width: none;
    -ms-overflow-style: none;
    transition: width 0.3s ease, left 0.3s ease;
}

#workOrdersTable-sticky-header::-webkit-scrollbar {
    display: none;
}

#workOrdersTable-sticky-header table {
    margin-bottom: 0;
}

#workOrdersTable-sticky-header th {
    font-size: 0.9em;
    font-weight: 600;
    white-space: nowrap;
    padding: 12px 8px;
    color: #fff;
    background: #212529;
    border-color: #373b3e;
}

/* شريط التمرير الأفقي المثبت في الأسفل */
#workOrdersTable-sticky-scrollbar {
    position: fixed;
    bottom: 0;
    z-index: 1020;
    display: none;
    overflow-x: auto;
    overflow-y: hidden;
    background: #f0f0f0;
    border-top: 1px solid #ccc;
    transition: width 0.3s ease, left 0.3s ease;
}

#workOrdersTable-sticky-scrollbar .scroll-inner {
    height: 1px;
}

/* تحسين عرض القوائم المنسدلة */
.form-select-sm {
    padding-top: 6px;
    padding-bottom: 6px;
    padding-right: 8px;
    font-size: 0.9em;
    border-radius: 6px;
}

/* تحسين عرض حقول الإدخال */
.form-control-sm {
    padding: 6px 8px;
    font-size: 0.9em;
    border-radius: 6px;
}

/* تحسين عرض الشارات */
.badge {
    font-size: 0.8em;
    padding: 6px 10px;
}

/* تحسين عرض الأزرار */
.btn-sm {
    padding: 4px 8px;
    font-size: 0.85em;
}

/* تنبيهات أسفل الشاشة */
.bottom-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    max-width: 500px;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    transform: translateX(100%);
    transition: all 0.3s ease-in-out;
    opacity: 0;
}

.bottom-notification.show {
    transform: translateX(0);
    opacity: 1;
}

.bottom-notification.success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border-left: 4px solid #155724;
}

.bottom-notification.error {
    background: linear-gradient(135deg, #dc3545, #e74c3c);
    color: white;
    border-left: 4px solid #721c24;
}

.bottom-notification.warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #212529;
    border-left: 4px solid #856404;
}

.bottom-notification.info {
    background: linear-gradient(135deg, #17a2b8, #007bff);
    color: white;
    border-left: 4px solid #0c5460;
}

.bottom-notification .notification-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.bottom-notification .notification-content {
    flex: 1;
}

.bottom-notification .notification-title {
    font-weight: 600;
    margin-bottom: 2px;
}

.bottom-notification .notification-message {
    font-size: 13px;
    opacity: 0.9;
}

.bottom-notification .notification-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 16px;
    cursor: pointer;
    padding: 0;
    margin-left: 10px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.bottom-notification .notification-close:hover {
    opacity: 1;
}

/* تأثير الانيميشن */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* تأثيرات تحديث الحقول */
.field-updated {
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    transition: all 0.3s ease;
}

.updating-field {
    background-color: #fff3cd !important;
    border-color: #ffc107 !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
}

/* تحسين مظهر Toast notifications */
.swal2-toast {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    font-size: 14px !important;
    min-width: 300px !important;
    max-width: 400px !important;
}

.swal2-toast .swal2-title {
    font-size: 14px !important;
    font-weight: 500 !important;
    margin: 0 !important;
    padding: 0 !important;
}

.swal2-toast .swal2-icon {
    width: 24px !important;
    height: 24px !important;
    margin: 0 8px 0 0 !important;
}

.swal2-toast .swal2-timer-progress-bar {
    background: rgba(255, 255, 255, 0.6) !important;
}

/* تحسين عرض التنبيهات للشاشات الصغيرة */
@media (max-width: 768px) {
    .bottom-notification {
        right: 10px;
        left: 10px;
        min-width: auto;
        max-width: none;
        transform: translateY(100%);
    }

    .bottom-notification.show {
        transform: translateY(0);
    }
}

/* تحسينات النوافذ المنبثقة (Modals) */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #edf2f9;
    border-radius: 16px 16px 0 0;
    padding: 1.25rem 1.5rem;
}
.modal-footer {
    border-top: 1px solid #edf2f9;
    padding: 1.25rem 1.5rem;
}

/* تحسينات عناصر DataTables */
div.dataTables_wrapper div.dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 0.375rem 0.75rem;
}
div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    outline: 0;
}
div.dataTables_wrapper div.dataTables_length select {
    border-radius: 8px;
    border: 1px solid #ced4da;
}

/* تحسين رأس الجدول */
#workOrdersTable thead th {
    background-color: #f8f9fa !important;
    color: #6c757d !important;
    font-weight: 700;
    font-size: 0.75rem;
    border-bottom: 2px solid #edf2f9 !important;
    white-space: nowrap;
}

/* أشرطة التمرير المخصصة (Custom Scrollbars) */
.table-responsive::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}
.table-responsive::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* تأثيرات التمرير الدقيقة لصفوف الجدول (Row Micro-interactions) */
#workOrdersTable tbody tr {
    transition: all 0.2s ease-in-out;
}
#workOrdersTable tbody tr:hover td {
    background-color: #f8fafc !important;
}
#workOrdersTable tbody tr:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
    transform: translateY(-1px);
    position: relative;
    z-index: 10;
}

/* الشارات الناعمة (Soft Badges) */
.badge-soft-success { background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc !important; }
.badge-soft-primary { background-color: #cfe2ff !important; color: #084298 !important; border: 1px solid #b6d4fe !important; }
.badge-soft-danger { background-color: #f8d7da !important; color: #842029 !important; border: 1px solid #f5c2c7 !important; }
.badge-soft-warning { background-color: #fff3cd !important; color: #664d03 !important; border: 1px solid #ffecb5 !important; }
.badge-soft-info { background-color: #cff4fc !important; color: #055160 !important; border: 1px solid #b6effb !important; }
.badge-soft-secondary { background-color: #e2e3e5 !important; color: #41464b !important; border: 1px solid #d3d6d8 !important; }

/* تحسينات للشاشات الصغيرة */
@media (max-width: 1200px) {
    #workOrdersTable th,
    #workOrdersTable td {
        padding: 8px 4px;
        font-size: 0.85em;
    }

    .form-select-sm {
        font-size: 0.75em;
        padding-top: 4px;
        padding-bottom: 4px;
        padding-right: 6px;
    }
    .form-control-sm {
        font-size: 0.75em;
        padding: 4px 6px;
    }

    #workOrdersTable th:nth-child(3),
    #workOrdersTable th:nth-child(4),
    #workOrdersTable th:nth-child(9),
    #workOrdersTable th:nth-child(10),
    #workOrdersTable th:nth-child(11),
    #workOrdersTable th:nth-child(12) {
        min-width: 70px;
    }

    /* القيمة الفعلية تبقى كما هي */
    #workOrdersTable th:nth-child(8) {
        min-width: 140px;
    }
}

/* تحسين عرض الجدول للشاشات الكبيرة */
@media (min-width: 1400px) {
    #workOrdersTable th:nth-child(3),
    #workOrdersTable th:nth-child(4),
    #workOrdersTable th:nth-child(9),
    #workOrdersTable th:nth-child(10),
    #workOrdersTable th:nth-child(11),
    #workOrdersTable th:nth-child(12) {
        min-width: 90px;
    }

    /* القيمة الفعلية تبقى كما هي */
    #workOrdersTable th:nth-child(8) {
        min-width: 180px;
    }
}

/* تنسيق الفلاتر */
#filtersContainer {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.quick-filter {
    transition: all 0.3s ease;
    border-width: 2px;
}

.quick-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.quick-filter.active {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
}

.quick-filter.active:hover {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
    opacity: 0.9;
}

/* زر المفضلة عندما يكون نشطاً */
.quick-filter[data-filter="favorites"].active {
    background-color: #ffc107;
    color: #000;
    border-color: #ffc107;
}

.quick-filter[data-filter="favorites"].active:hover {
    background-color: #ffc107;
    color: #000;
    border-color: #ffc107;
    opacity: 0.9;
}

.quick-filter[data-filter="favorites"].active i {
    color: #000 !important;
}

/* تحسين عرض الفلاتر */
.form-label {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

/* تنسيق منطقة الفلاتر - Compact Design */
#filtersContainer .form-label.small {
    font-size: 0.8rem;
    font-weight: 500;
    color: #495057;
}

#filtersContainer .form-select-sm,
#filtersContainer .form-control-sm {
    font-size: 0.85rem;
    padding: 0.25rem 0.5rem;
}

/* Select2 - نمط Excel محسّن */
.select2-container--bootstrap-5 .select2-selection {
    min-height: 31px !important;
    border-color: #dee2e6;
    font-size: 0.85rem;
}

.select2-container--bootstrap-5 .select2-selection--single {
    height: 31px !important;
}

.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    line-height: 29px !important;
    padding-left: 8px;
}

.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 29px !important;
}

.select2-container--bootstrap-5 .select2-selection--multiple {
    min-height: 31px !important;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
    padding: 1px 4px;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
    padding: 1px 6px;
    margin: 2px;
    border-radius: 3px;
    font-size: 0.75rem;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-left: 3px;
    font-size: 0.9rem;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ffdddd;
}

.select2-dropdown {
    border-color: #dee2e6;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    font-size: 0.85rem;
}

.select2-results__option {
    padding: 6px 12px;
}

.select2-results__option--highlighted {
    background-color: #0d6efd !important;
    color: white !important;
}

.select2-search--dropdown .select2-search__field {
    font-size: 0.85rem;
    padding: 4px 8px;
}

/* تحسين مظهر الأزرار في header الفلاتر */
#filtersContainer .btn-group .btn {
    font-size: 0.85rem;
}

#filtersContainer .form-select,
#filtersContainer .form-control {
    border-radius: 6px;
    border: 1px solid #ced4da;
}

#filtersContainer .form-select:focus,
#filtersContainer .form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* تنسيق أعمدة النماذج */
.forms-column {
    min-width: 100px;
    text-align: center;
}

#workOrdersTable td .badge {
    min-width: 80px;
    display: inline-block;
}

/* تحسين زر وضع النماذج */
#toggleFormsViewBtn {
    transition: all 0.3s ease;
}

#toggleFormsViewBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* تنسيق نجمة المفضلة */
.favorite-star {
    transition: all 0.2s ease;
}

.favorite-star:hover {
    transform: scale(1.3);
}

.favorite-star.active {
    animation: starPulse 0.5s ease;
}

@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* إخفاء صندوق البحث الافتراضي في DataTable */
.dataTables_filter {
    display: none !important;
}

/* إخفاء الفلاتر بشكل افتراضي */
#filtersContainer {
    display: none;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
// متغير عام لحفظ الجهات الحالية
var currentEntities = [];

// دالة لحماية النصوص من XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// دالة لعرض حالة النموذج كقائمة منسدلة قابلة للتعديل
function renderFormStatus(status, formName, workOrderId, formType) {
    const formStatus = status || 'not_attached';
    let html = '<select class="form-select form-select-sm form-status-select form-status-' + formStatus + '" ';
    html += 'data-work-order-id="' + workOrderId + '" ';
    html += 'data-form-type="' + formType + '" ';
    html += 'onchange="updateFormStatus(' + workOrderId + ', \'' + formType + '\', this.value, this)" ';
    html += 'title="' + formName + '">';
    html += '<option value="not_attached"' + (formStatus === 'not_attached' ? ' selected' : '') + '>غير مرفق</option>';
    html += '<option value="attached"' + (formStatus === 'attached' ? ' selected' : '') + '>مرفق</option>';
    html += '<option value="not_applicable"' + (formStatus === 'not_applicable' ? ' selected' : '') + '>لا ينطبق</option>';
    html += '</select>';
    return html;
}

// متغير عام للجدول
var table;
var formsViewActive = false;

$(document).ready(function() {
    // استرجاع إعدادات الصفحة المحفوظة قبل تهيئة الجدول
    const savedState = restorePageState();
    if (savedState && savedState.filters) {
        // تطبيق الفلاتر المحفوظة
        $('#filterDepartment').val(savedState.filters.department || '');
        $('#filterCurrentEntity').val(savedState.filters.currentEntity || '');
        $('#filterBranch').val(savedState.filters.branch || '');
        $('#filterDateFrom').val(savedState.filters.dateFrom || '');
        $('#filterDateTo').val(savedState.filters.dateTo || '');
        $('#filterCompletionCertificate').val(savedState.filters.completionCertificate || []);
        $('#filterCertificateConfirmation').val(savedState.filters.certificateConfirmation || []);
        $('#filterDisbursementStatus').val(savedState.filters.disbursementStatus || []);
        $('#filterPreciseDrilling').val(savedState.filters.preciseDrilling || []);
        $('#filterExcavation').val(savedState.filters.excavation || []);
        $('#filterDemolition').val(savedState.filters.demolition || []);
        $('#filterF1Form').val(savedState.filters.f1Form || []);
        $('#filterAssetsReceipt').val(savedState.filters.assetsReceipt || []);

        // حفظ حالة وضع النماذج
        if (savedState.formsViewActive) {
            formsViewActive = false; // سيتم تفعيله بعد تهيئة الجدول
        }

        // استرجاع الفلتر السريع
        if (savedState.quickFilter) {
            window.activeQuickFilter = savedState.quickFilter;
            $('.quick-filter[data-filter="' + savedState.quickFilter + '"]').addClass('active');
        }
    }

    // التحقق من عدم تهيئة DataTable مسبقاً
    if (!$.fn.DataTable.isDataTable('#workOrdersTable')) {
        // الحصول على معامل show_completed من URL
        const urlParams = new URLSearchParams(window.location.search);
        const showCompleted = urlParams.get('show_completed') || '0';

        // تهيئة DataTable مع Server-Side Processing
        table = $('#workOrdersTable').DataTable({
            "processing": true,
            "serverSide": true,
            "searching": true,
            "ajax": {
                "url": "get-work-orders-ajax.php",
                "type": "GET",
                "data": function(d) {
                    d.show_completed = showCompleted;
                    d.filterDepartment = $('#filterDepartment').val();
                    d.filterCurrentEntity = $('#filterCurrentEntity').val();
                    d.filterBranch = $('#filterBranch').val();
                    d.filterDateFrom = $('#filterDateFrom').val();
                    d.filterDateTo = $('#filterDateTo').val();

                    // إرسال الفلاتر المتعددة كمصفوفات
                    var completionCertVal = $('#filterCompletionCertificate').val();
                    var certConfirmationVal = $('#filterCertificateConfirmation').val();
                    var disbursementVal = $('#filterDisbursementStatus').val();
                    var preciseDrillingVal = $('#filterPreciseDrilling').val();
                    var excavationVal = $('#filterExcavation').val();
                    var demolitionVal = $('#filterDemolition').val();
                    var f1FormVal = $('#filterF1Form').val();
                    var assetsReceiptVal = $('#filterAssetsReceipt').val();

                    if (completionCertVal && completionCertVal.length > 0) {
                        d['filterCompletionCertificate[]'] = completionCertVal;
                    }
                    if (certConfirmationVal && certConfirmationVal.length > 0) {
                        d['filterCertificateConfirmation[]'] = certConfirmationVal;
                    }
                    if (disbursementVal && disbursementVal.length > 0) {
                        d['filterDisbursementStatus[]'] = disbursementVal;
                    }
                    if (preciseDrillingVal && preciseDrillingVal.length > 0) {
                        d['filterPreciseDrilling[]'] = preciseDrillingVal;
                    }
                    if (excavationVal && excavationVal.length > 0) {
                        d['filterExcavation[]'] = excavationVal;
                    }
                    if (demolitionVal && demolitionVal.length > 0) {
                        d['filterDemolition[]'] = demolitionVal;
                    }
                    if (f1FormVal && f1FormVal.length > 0) {
                        d['filterF1Form[]'] = f1FormVal;
                    }
                    if (assetsReceiptVal && assetsReceiptVal.length > 0) {
                        d['filterAssetsReceipt[]'] = assetsReceiptVal;
                    }

                    d.quickFilter = window.activeQuickFilter || '';
                },
                "error": function(xhr, error, thrown) {
                    console.error('AJAX Error:', error);
                    console.error('Response:', xhr.responseText);
                    showAlert('error', 'حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.');
                },
                "dataSrc": function(json) {
                    // حفظ الجهات الحالية
                    currentEntities = json.currentEntities || [];
                    // تحديث عداد السجلات
                    $('#tableRecordCount').text(json.recordsFiltered.toLocaleString('ar-EG') + ' أمر عمل');
                    return json.data;
                }
            },
            "columns": [
                { "data": "is_favorite" },
                { "data": "work_order_number" },
                { "data": "work_order_type_code" },
                { "data": "department" },
                { "data": "current_entity_id" },
                { "data": "branch_name" },
                { "data": "location" },
                { "data": "partial_extract_number" },
                { "data": "assignment_date" },
                { "data": "actual_value" },
                { "data": "completion_certificate_status" },
                { "data": "certificate_attached_date" },
                { "data": "completion_certificate_confirmation" },
                { "data": "certificate_confirmed_date" },
                { "data": "disbursement_status" },
                { "data": "status" },
                { "data": "overall_progress" },
                { "data": "precise_drilling_status" },
                { "data": "excavation_status" },
                { "data": "demolition_status" },
                { "data": "f1_status" },
                { "data": "assets_receipt_status" },
                { "data": "id" }
            ],
            "columnDefs": [
                {
                    "targets": 0,
                    "orderable": false,
                    "className": "text-center",
                    "render": function(data, type, row) {
                        const isFavorite = parseInt(data) === 1 || data === '1' || data === true;
                        const starClass = isFavorite ? 'fas fa-star favorite-star active' : 'far fa-star favorite-star';
                        const starColor = isFavorite ? 'color: #ffc107;' : 'color: #6c757d;';
                        return '<i class="' + starClass + '" ' +
                               'data-work-order-id="' + row.id + '" ' +
                               'data-is-favorite="' + (isFavorite ? '1' : '0') + '" ' +
                               'onclick="toggleFavorite(' + row.id + ', this)" ' +
                               'style="cursor: pointer; font-size: 1.2em; ' + starColor + '" ' +
                               'title="' + (isFavorite ? 'إزالة من المفضلة' : 'إضافة إلى المفضلة') + '"></i>';
                    }
                },
                {
                    "targets": 1,
                    "render": function(data, type, row) {
                        return '<strong class="text-primary">' + escapeHtml(data) + '</strong>';
                    }
                },
                {
                    "targets": 2,
                    "render": function(data, type, row) {
                        return '<span class="badge bg-info">' + escapeHtml(data) + '</span>';
                    }
                },
                {
                    "targets": 3,
                    "render": function(data, type, row) {
                        const badgeClass = data === 'connections' ? 'bg-primary' : 'bg-success';
                        const label = data === 'connections' ? 'التوصيلات' : 'المشاريع';
                        return '<span class="badge ' + badgeClass + '">' + label + '</span>';
                    }
                },
                {
                    "targets": 4,
                    "render": function(data, type, row) {
                        let html = '<select class="form-select form-select-sm current-entity-select" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'onchange="updateWorkOrderField(' + row.id + ', \'current_entity_id\', this.value, this)">';
                        html += '<option value="">غير محدد</option>';

                        currentEntities.forEach(function(entity) {
                            const selected = data == entity.id ? 'selected' : '';
                            html += '<option value="' + entity.id + '" ' + selected + '>' + escapeHtml(entity.name) + '</option>';
                        });

                        html += '</select>';
                        return html;
                    }
                },
                {
                    "targets": 5,
                    "render": function(data, type, row) {
                        return escapeHtml(data || 'غير محدد');
                    }
                },
                {
                    "targets": 6,
                    "render": function(data, type, row) {
                        return '<input type="text" class="form-control form-control-sm location-input" ' +
                               'data-work-order-id="' + row.id + '" ' +
                               'value="' + escapeHtml(data || '') + '" ' +
                               'placeholder="الموقع" maxlength="255" ' +
                               'onchange="updateWorkOrderField(' + row.id + ', \'location\', this.value, this)" ' +
                               'style="min-width: 120px;">';
                    }
                },
                {
                    "targets": 7,
                    "render": function(data, type, row) {
                        let extractNumber = '';
                        let extractType = '';
                        let extractClass = 'text-muted';
                        let extractLink = '';

                        if (row.final_for_partial_extract_number) {
                            extractNumber = row.final_for_partial_extract_number;
                            extractType = 'نهائي لجزئي';
                            extractClass = 'text-warning';
                            extractLink = '../extracts/final-for-partial/view.php?id=' + row.final_for_partial_extract_id;
                        } else if (row.final_regular_extract_number) {
                            extractNumber = row.final_regular_extract_number;
                            extractType = 'نهائي عادي';
                            extractClass = 'text-success';
                            extractLink = '../extracts/final-regular/view.php?id=' + row.final_regular_extract_id;
                        } else if (row.partial_extract_number) {
                            extractNumber = row.partial_extract_number;
                            extractType = 'جزئي';
                            extractClass = 'text-primary';
                            extractLink = '../extracts/partial/view.php?id=' + row.partial_extract_id;
                        }

                        if (extractNumber) {
                            let html = '<div class="' + extractClass + '">';
                            if (extractLink) {
                                html += '<a href="' + extractLink + '" class="' + extractClass + ' text-decoration-none" title="عرض تفاصيل المستخلص">';
                                html += '<strong>' + escapeHtml(extractNumber) + '</strong>';
                                html += '<i class="fas fa-external-link-alt ms-1" style="font-size: 0.7em;"></i>';
                                html += '</a>';
                            } else {
                                html += '<strong>' + escapeHtml(extractNumber) + '</strong>';
                            }
                            html += '<br><small class="text-muted">' + extractType + '</small>';
                            html += '</div>';
                            return html;
                        } else {
                            return '<span class="text-muted small">لا يوجد</span>';
                        }
                    }
                },
                {
                    "targets": 8,
                    "render": function(data, type, row) {
                        return '<input type="date" class="form-control form-control-sm assignment-date-input" ' +
                               'data-work-order-id="' + row.id + '" ' +
                               'value="' + (data || '') + '" ' +
                               'onblur="updateWorkOrderField(' + row.id + ', \'assignment_date\', this.value, this)" ' +
                               'onkeypress="if(event.keyCode===13) this.blur()">';
                    }
                },
                {
                    "targets": 9,
                    "render": function(data, type, row) {
                        const formattedValue = parseFloat(data || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        return '<input type="text" class="form-control form-control-sm actual-value-input" ' +
                               'data-work-order-id="' + row.id + '" ' +
                               'data-raw-value="' + (data || 0) + '" ' +
                               'value="' + formattedValue + '" ' +
                               'onchange="updateActualValueField(' + row.id + ', this)" ' +
                               'onfocus="unformatActualValue(this)" ' +
                               'style="color: #0d6efd; font-weight: bold; text-align: right;">';
                    }
                },
                {
                    "targets": 10,
                    "render": function(data, type, row) {
                        const certificateStatus = data || 'not_attached';
                        let html = '<select class="form-select form-select-sm completion-certificate-status-select certificate-status-' + certificateStatus + '" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'onchange="updateWorkOrderField(' + row.id + ', \'completion_certificate_status\', this.value, this)">';
                        html += '<option value="not_attached"' + (certificateStatus === 'not_attached' ? ' selected' : '') + '>غير مرفق</option>';
                        html += '<option value="attached"' + (certificateStatus === 'attached' ? ' selected' : '') + '>مرفق</option>';
                        html += '<option value="not_applicable"' + (certificateStatus === 'not_applicable' ? ' selected' : '') + '>غير قابل للتطبيق</option>';
                        html += '</select>';

                        if (row.completion_certificate_file && certificateStatus === 'attached') {
                            html += '<div class="mt-1">';
                            html += '<a href="' + escapeHtml(row.completion_certificate_file) + '" target="_blank" class="btn btn-sm btn-outline-primary" title="عرض الملف">';
                            html += '<i class="fas fa-file-alt"></i> ';
                            html += '<small>' + escapeHtml(row.completion_certificate_filename || 'ملف') + '</small>';
                            html += '</a></div>';
                        }

                        return html;
                    }
                },
                {
                    "targets": 11,
                    "render": function(data, type, row) {
                        let html = '<input type="date" class="form-control form-control-sm certificate-date-input" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'value="' + (data || '') + '" ';
                        html += 'onblur="updateCertificateDate(' + row.id + ', \'certificate_attached_date\', this.value, this)" ';
                        html += 'onkeypress="if(event.keyCode===13) this.blur()" ';
                        html += 'style="font-size: 11px; padding: 2px 4px; min-width: 120px;" ';
                        html += 'title="تاريخ إرفاق الشهادة">';
                        return html;
                    }
                },
                {
                    "targets": 12,
                    "render": function(data, type, row) {
                        const confirmationStatus = data || 'empty';
                        let html = '<select class="form-select form-select-sm completion-certificate-confirmation-select confirmation-status-' + confirmationStatus + '" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'onchange="updateWorkOrderField(' + row.id + ', \'completion_certificate_confirmation\', this.value, this)">';
                        html += '<option value="empty"' + (confirmationStatus === 'empty' ? ' selected' : '') + '>فارغ</option>';
                        html += '<option value="confirmed"' + (confirmationStatus === 'confirmed' ? ' selected' : '') + '>مؤكد</option>';
                        html += '<option value="accepted"' + (confirmationStatus === 'accepted' ? ' selected' : '') + '>مقبول</option>';
                        html += '<option value="rejected"' + (confirmationStatus === 'rejected' ? ' selected' : '') + '>مرفوض</option>';
                        html += '</select>';
                        return html;
                    }
                },
                {
                    "targets": 13,
                    "render": function(data, type, row) {
                        let html = '<input type="date" class="form-control form-control-sm certificate-date-input" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'value="' + (data || '') + '" ';
                        html += 'onblur="updateCertificateDate(' + row.id + ', \'certificate_confirmed_date\', this.value, this)" ';
                        html += 'onkeypress="if(event.keyCode===13) this.blur()" ';
                        html += 'style="font-size: 11px; padding: 2px 4px; min-width: 120px;" ';
                        html += 'title="تاريخ تأكيد الشهادة">';
                        return html;
                    }
                },
                {
                    "targets": 14,
                    "render": function(data, type, row) {
                        const disbursementStatus = data || 'none';
                        <?php if (hasPermission('work_orders_update_fields')): ?>
                        let html = '<select class="form-select form-select-sm disbursement-status-select" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'onchange="updateWorkOrderField(' + row.id + ', \'disbursement_status\', this.value, this)">';
                        html += '<option value="none"' + (disbursementStatus === 'none' ? ' selected' : '') + '>لا يوجد</option>';
                        html += '<option value="completed"' + (disbursementStatus === 'completed' ? ' selected' : '') + '>مكتمل</option>';
                        html += '<option value="disbursement"' + (disbursementStatus === 'disbursement' ? ' selected' : '') + '>صرف</option>';
                        html += '<option value="return"' + (disbursementStatus === 'return' ? ' selected' : '') + '>إرجاع</option>';
                        html += '<option value="disbursement_return_completed"' + (disbursementStatus === 'disbursement_return_completed' ? ' selected' : '') + '>صرف وإرجاع</option>';
                        html += '</select>';
                        return html;
                        <?php else: ?>
                        const statusLabels = {
                            'none': 'لا يوجد',
                            'completed': 'مكتمل',
                            'disbursement': 'صرف',
                            'return': 'إرجاع',
                            'disbursement_return_completed': 'صرف وإرجاع'
                        };
                        const disClassMap = {
                            'none': 'secondary',
                            'completed': 'success',
                            'disbursement': 'primary',
                            'return': 'warning',
                            'disbursement_return_completed': 'info'
                        };
                        const disClass = disClassMap[disbursementStatus] || 'secondary';
                        return '<span class="badge rounded-pill badge-soft-' + disClass + ' px-3 py-1 fw-bold shadow-none">' + (statusLabels[disbursementStatus] || 'غير محدد') + '</span>';
                        <?php endif; ?>
                    }
                },
                {
                    "targets": 15,
                    "render": function(data, type, row) {
                        const status = data || 'active';
                        <?php if (hasPermission('work_orders_update_fields')): ?>
                        let html = '<select class="form-select form-select-sm status-select" ';
                        html += 'data-work-order-id="' + row.id + '" ';
                        html += 'onchange="updateWorkOrderField(' + row.id + ', \'status\', this.value, this)">';
                        html += '<option value="active"' + (status === 'active' ? ' selected' : '') + '>نشط</option>';
                        html += '<option value="completed"' + (status === 'completed' ? ' selected' : '') + '>مكتمل</option>';
                        html += '<option value="cancelled"' + (status === 'cancelled' ? ' selected' : '') + '>ملغي</option>';
                        html += '<option value="inactive"' + (status === 'inactive' ? ' selected' : '') + '>غير نشط</option>';
                        html += '</select>';
                        return html;
                        <?php else: ?>
                        const statusMap = {
                            'active': { class: 'success', label: 'نشط' },
                            'completed': { class: 'primary', label: 'مكتمل' },
                            'cancelled': { class: 'danger', label: 'ملغي' },
                            'inactive': { class: 'secondary', label: 'غير نشط' }
                        };
                        const statusInfo = statusMap[status] || { class: 'secondary', label: 'غير محدد' };
                        return '<span class="badge rounded-pill badge-soft-' + statusInfo.class + ' px-3 py-1 fw-bold shadow-none">' + statusInfo.label + '</span>';
                        <?php endif; ?>
                    }
                },
                {
                    "targets": 16,
                    "render": function(data, type, row) {
                        const progress = data ? parseFloat(data) : 0;
                        let colorClass = 'secondary';
                        if (progress >= 100) colorClass = 'success';
                        else if (progress > 50) colorClass = 'primary';
                        else if (progress > 0) colorClass = 'warning';
                        
                        let html = '<div class="d-flex flex-column align-items-center">';
                        html += '<div class="progress w-100 mb-1" style="height: 6px;">';
                        html += '<div class="progress-bar bg-' + colorClass + '" role="progressbar" style="width: ' + progress + '%" aria-valuenow="' + progress + '" aria-valuemin="0" aria-valuemax="100"></div>';
                        html += '</div>';
                        html += '<div class="d-flex justify-content-between w-100 align-items-center">';
                        html += '<span class="small fw-bold text-' + colorClass + '">' + progress + '%</span>';
                        html += '<a href="' + escapeHtml('../productivity/work-items/index.php?work_order_id=' + row.id) + '" class="btn btn-xs btn-outline-primary py-0 px-1" title="إضافة إنجاز يومي"><i class="fas fa-plus"></i></a>';
                        html += '</div></div>';
                        return html;
                    }
                },
                {
                    "targets": 17,
                    "render": function(data, type, row) {
                        return renderFormStatus(data, 'الحفر الدقيق', row.id, 'precise_drilling_form');
                    }
                },
                {
                    "targets": 18,
                    "render": function(data, type, row) {
                        return renderFormStatus(data, 'الكشط', row.id, 'excavation_form');
                    }
                },
                {
                    "targets": 19,
                    "render": function(data, type, row) {
                        return renderFormStatus(data, 'التخريد', row.id, 'demolition_form');
                    }
                },
                {
                    "targets": 20,
                    "render": function(data, type, row) {
                        return renderFormStatus(data, 'F1', row.id, 'f1_form');
                    }
                },
                {
                    "targets": 21,
                    "render": function(data, type, row) {
                        return renderFormStatus(data, 'استلام الأصول (211)', row.id, 'assets_receipt_form');
                    }
                },
                {
                    "targets": 22,
                    "render": function(data, type, row) {
                        let html = '<div class="d-flex gap-1 justify-content-center">';

                        <?php if (hasPermission('work_orders_view_details')): ?>
                        html += '<button type="button" class="btn btn-sm btn-light rounded-circle text-info shadow-sm border-0" ';
                        html += 'onclick="viewWorkOrder(' + row.id + ')" title="عرض">';
                        html += '<i class="fas fa-eye"></i></button>';
                        <?php endif; ?>

                        <?php if (hasPermission('work_orders_edit')): ?>
                        html += '<button type="button" class="btn btn-sm btn-light rounded-circle text-primary shadow-sm border-0" ';
                        html += 'onclick="editWorkOrder(' + row.id + ')" title="تعديل">';
                        html += '<i class="fas fa-edit"></i></button>';
                        <?php endif; ?>

                        <?php if (hasPermission('work_orders_attachments')): ?>
                        html += '<button type="button" class="btn btn-sm btn-light rounded-circle text-success shadow-sm border-0" ';
                        html += 'onclick="manageAttachments(' + row.id + ')" title="إدارة النماذج المرفقة">';
                        html += '<i class="fas fa-paperclip"></i></button>';
                        <?php endif; ?>

                        <?php if (hasPermission('work_orders_delete')): ?>
                        html += '<button type="button" class="btn btn-sm btn-light rounded-circle text-danger shadow-sm border-0" ';
                        html += 'onclick="deleteWorkOrder(' + row.id + ', \'' + escapeHtml(row.work_order_number) + '\')" title="حذف">';
                        html += '<i class="fas fa-trash"></i></button>';
                        <?php endif; ?>

                        html += '</div>';
                        return html;
                    }
                },
                { "orderable": false, "targets": -1 }
            ],
            "language": {
                "sProcessing": '<div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div> جارٍ التحميل...',
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
            "pageLength": 25,
            "order": [[0, 'desc']],
            "deferRender": true,
            "searchDelay": 500,
            "drawCallback": function(settings) {
                // تطبيق الألوان بعد رسم الجدول
                applyInitialColors();
                // تطبيق تنسيق القيم الفعلية
                updateActualValueDisplay();
            }
        });
    } else {
        console.log('DataTable already initialized');
        table = $('#workOrdersTable').DataTable();
    }

    // تطبيق وضع النماذج إذا كان محفوظاً
    if (savedState && savedState.formsViewActive) {
        setTimeout(function() {
            $('#toggleFormsViewBtn').click();
        }, 500);
    }

    // معالجة إظهار/إخفاء الفلاتر
    $('#filtersHeader').on('click', function() {
        const filtersContainer = $('#filtersContainer');
        const toggleText = $('#toggleFiltersText');
        const toggleIcon = $('#filtersHeader i.fa-chevron-up');

        if (filtersContainer.is(':visible')) {
            // إخفاء الفلاتر
            filtersContainer.slideUp(300);
            toggleIcon.css('transform', 'rotate(180deg)');
            toggleText.text('إظهار');
        } else {
            // إظهار الفلاتر
            filtersContainer.slideDown(300);
            toggleIcon.css('transform', 'rotate(0deg)');
            toggleText.text('إخفاء');
        }
    });

    // معالجة صندوق البحث في الجدول
    $('#workOrdersTableSearch').on('keyup', function() {
        // تأخير البحث قليلاً لتجنب الطلبات المتكررة
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function() {
            table.search($('#workOrdersTableSearch').val()).draw();
        }, 300);
    });

    // معالجة الفلاتر
    // تطبيق الفلاتر
    $('#applyFilters').on('click', function() {
        window.activeQuickFilter = ''; // إلغاء الفلتر السريع
        $('.quick-filter').removeClass('active');
        table.ajax.reload();
        savePageState();
    });

    // إعادة تعيين الفلاتر
    $('#resetFilters').on('click', function() {
        // إعادة تعيين الفلاتر الفردية
        $('#filterDepartment').val('').trigger('change');
        $('#filterCurrentEntity').val('').trigger('change');
        $('#filterBranch').val('').trigger('change');
        $('#filterDateFrom').val('');
        $('#filterDateTo').val('');

        // إعادة تعيين الفلاتر المتعددة
        $('#filterCompletionCertificate').val(null).trigger('change');
        $('#filterCertificateConfirmation').val(null).trigger('change');
        $('#filterDisbursementStatus').val(null).trigger('change');
        $('#filterPreciseDrilling').val(null).trigger('change');
        $('#filterExcavation').val(null).trigger('change');
        $('#filterDemolition').val(null).trigger('change');
        $('#filterF1Form').val(null).trigger('change');
        $('#filterAssetsReceipt').val(null).trigger('change');

        // إعادة تعيين الفلاتر السريعة
        window.activeQuickFilter = '';
        $('.quick-filter').removeClass('active');

        table.ajax.reload();
        savePageState();
    });

    // حفظ الحالة عند تغيير أي فلتر
    $('#filterDepartment, #filterCurrentEntity, #filterBranch, #filterDateFrom, #filterDateTo, #filterCompletionCertificate, #filterCertificateConfirmation, #filterDisbursementStatus, #filterPreciseDrilling, #filterExcavation, #filterDemolition, #filterF1Form, #filterAssetsReceipt').on('change', function() {
        savePageState();
    });

    // الفلاتر السريعة
    $('.quick-filter').on('click', function() {
        const filterType = $(this).data('filter');

        // إعادة تعيين الفلاتر الأساسية
        $('#filterDepartment').val('');
        $('#filterCurrentEntity').val('');
        $('#filterBranch').val('');
        $('#filterDateFrom').val('');
        $('#filterDateTo').val('');
        $('#filterCompletionCertificate').val('');
        $('#filterCertificateConfirmation').val('');
        $('#filterDisbursementStatus').val('');
        $('#filterPreciseDrilling').val([]);
        $('#filterExcavation').val([]);
        $('#filterDemolition').val([]);

        // تفعيل/إلغاء الفلتر السريع
        if (window.activeQuickFilter === filterType) {
            window.activeQuickFilter = '';
            $(this).removeClass('active');
        } else {
            window.activeQuickFilter = filterType;
            $('.quick-filter').removeClass('active');
            $(this).addClass('active');
        }

        table.ajax.reload();
        savePageState();
    });

    // زر وضع النماذج - إظهار/إخفاء الأعمدة
    $('#toggleFormsViewBtn').on('click', function() {
        formsViewActive = !formsViewActive;

        if (formsViewActive) {
            // وضع النماذج: إخفاء الأعمدة غير المطلوبة وإظهار أعمدة النماذج
            table.column(4).visible(false); // الجهة الحالية
            table.column(5).visible(false); // الفرع
            table.column(6).visible(false); // الموقع
            table.column(7).visible(false); // رقم المستخلص
            table.column(8).visible(false); // تاريخ التكليف
            table.column(10).visible(false); // شهادة الإنجاز
            table.column(11).visible(false); // تاريخ ارفاق الشهادة
            table.column(12).visible(false); // تأكيد الشهادة
            table.column(13).visible(false); // تاريخ تأكيد الشهادة
            table.column(14).visible(false); // حالة الصرف
            table.column(15).visible(false); // الحالة

            // إظهار أعمدة النماذج
            table.column(16).visible(true); // الحفر الدقيق
            table.column(17).visible(true); // الكشط
            table.column(18).visible(true); // التخريد
            table.column(19).visible(true); // F1
            table.column(20).visible(true); // استلام الأصول

            $(this).html('<i class="fas fa-table me-1"></i>الوضع العادي');
            $(this).removeClass('btn-info').addClass('btn-warning');
        } else {
            // الوضع العادي: إظهار جميع الأعمدة الأساسية وإخفاء أعمدة النماذج
            table.column(4).visible(true); // الجهة الحالية
            table.column(5).visible(true); // الفرع
            table.column(6).visible(true); // الموقع
            table.column(7).visible(true); // رقم المستخلص
            table.column(8).visible(true); // تاريخ التكليف
            table.column(10).visible(true); // شهادة الإنجاز
            table.column(11).visible(true); // تاريخ ارفاق الشهادة
            table.column(12).visible(true); // تأكيد الشهادة
            table.column(13).visible(true); // تاريخ تأكيد الشهادة
            table.column(14).visible(true); // حالة الصرف
            table.column(15).visible(true); // الحالة

            // إخفاء أعمدة النماذج
            table.column(16).visible(false); // الحفر الدقيق
            table.column(17).visible(false); // الكشط
            table.column(18).visible(false); // التخريد
            table.column(19).visible(false); // F1
            table.column(20).visible(false); // استلام الأصول

            $(this).html('<i class="fas fa-file-alt me-1"></i>وضع النماذج');
            $(this).removeClass('btn-warning').addClass('btn-info');
        }

        // حفظ الحالة
        savePageState();
    });

    // زر الوضع الكامل - إظهار جميع الأعمدة
    $('#toggleFullViewBtn').on('click', function() {
        // إظهار جميع الأعمدة
        for (let i = 1; i < table.columns()[0].length; i++) {
            table.column(i).visible(true);
        }

        // تحديث حالة زر وضع النماذج
        formsViewActive = false;
        $('#toggleFormsViewBtn').html('<i class="fas fa-file-alt me-1"></i>وضع النماذج');
        $('#toggleFormsViewBtn').removeClass('btn-warning').addClass('btn-info');

        savePageState();
    });

    // زر تصدير الجدول الحالي إلى Excel
    $('#exportCurrentTableBtn').on('click', function() {
        // الحصول على جميع الفلاتر الحالية المطبقة على الجدول
        const urlParams = new URLSearchParams(window.location.search);
        const showCompleted = urlParams.get('show_completed') || '0';

        // الفلاتر الأساسية
        const filterDepartment = $('#filterDepartment').val() || '';
        const filterCurrentEntity = $('#filterCurrentEntity').val() || '';
        const filterBranch = $('#filterBranch').val() || '';
        const filterDateFrom = $('#filterDateFrom').val() || '';
        const filterDateTo = $('#filterDateTo').val() || '';

        // الفلاتر المتعددة (Select2)
        const filterCompletionCertificate = $('#filterCompletionCertificate').val() || [];
        const filterCertificateConfirmation = $('#filterCertificateConfirmation').val() || [];
        const filterDisbursementStatus = $('#filterDisbursementStatus').val() || [];
        const filterPreciseDrilling = $('#filterPreciseDrilling').val() || [];
        const filterExcavation = $('#filterExcavation').val() || [];
        const filterDemolition = $('#filterDemolition').val() || [];
        const filterF1Form = $('#filterF1Form').val() || [];
        const filterAssetsReceipt = $('#filterAssetsReceipt').val() || [];

        // الفلتر السريع
        const quickFilter = window.activeQuickFilter || '';

        // بناء URL التصدير مع جميع الفلاتر الحالية
        let exportUrl = `export.php?format=xlsx`;
        exportUrl += `&status=${showCompleted === '1' ? 'all' : 'active'}`;
        exportUrl += `&department=${encodeURIComponent(filterDepartment)}`;
        exportUrl += `&current_entity=${encodeURIComponent(filterCurrentEntity)}`;
        exportUrl += `&branch_id=${encodeURIComponent(filterBranch)}`;
        exportUrl += `&date_from=${encodeURIComponent(filterDateFrom)}`;
        exportUrl += `&date_to=${encodeURIComponent(filterDateTo)}`;
        exportUrl += `&quick_filter=${encodeURIComponent(quickFilter)}`;

        // إضافة الفلاتر المتعددة
        if (filterCompletionCertificate.length > 0) {
            exportUrl += `&completion_certificate=${encodeURIComponent(JSON.stringify(filterCompletionCertificate))}`;
        }
        if (filterCertificateConfirmation.length > 0) {
            exportUrl += `&certificate_confirmation=${encodeURIComponent(JSON.stringify(filterCertificateConfirmation))}`;
        }
        if (filterDisbursementStatus.length > 0) {
            exportUrl += `&disbursement_status=${encodeURIComponent(JSON.stringify(filterDisbursementStatus))}`;
        }
        if (filterPreciseDrilling.length > 0) {
            exportUrl += `&precise_drilling=${encodeURIComponent(JSON.stringify(filterPreciseDrilling))}`;
        }
        if (filterExcavation.length > 0) {
            exportUrl += `&excavation=${encodeURIComponent(JSON.stringify(filterExcavation))}`;
        }
        if (filterDemolition.length > 0) {
            exportUrl += `&demolition=${encodeURIComponent(JSON.stringify(filterDemolition))}`;
        }
        if (filterF1Form.length > 0) {
            exportUrl += `&f1_form=${encodeURIComponent(JSON.stringify(filterF1Form))}`;
        }
        if (filterAssetsReceipt.length > 0) {
            exportUrl += `&assets_receipt=${encodeURIComponent(JSON.stringify(filterAssetsReceipt))}`;
        }

        exportUrl += `&include_extracts=1`;
        exportUrl += `&include_attachments=1`;

        // إظهار رسالة تحميل
        Swal.fire({
            title: 'جاري التصدير...',
            html: '<div class="text-center"><i class="fas fa-file-excel fa-3x text-success mb-3"></i><br>يتم تحضير ملف Excel للجدول الحالي مع الفلاتر المطبقة</div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // بدء التحميل
        window.location.href = exportUrl;

        // إخفاء رسالة التحميل بعد فترة
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                position: 'top-end',
                icon: 'success',
                title: 'تم التصدير بنجاح',
                showConfirmButton: false,
                timer: 2000,
                toast: true
            });
        }, 3000);
    });

    // إخفاء أعمدة النماذج افتراضياً
    table.column(16).visible(false); // الحفر الدقيق
    table.column(17).visible(false); // الكشط
    table.column(18).visible(false); // التخريد
    table.column(19).visible(false); // F1
    table.column(20).visible(false); // استلام الأصول


    // إخفاء/إظهار الفلاتر
    $('#toggleFilters').on('click', function() {
        $('#filtersContainer').slideToggle();
        const icon = $(this).find('i');
        const text = $('#toggleFiltersText');

        if (icon.hasClass('fa-chevron-up')) {
            icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            text.text('إظهار');
        } else {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            text.text('إخفاء');
        }
    });

    // تطبيق الفلتر عند الضغط على Enter
    $('#filtersContainer input, #filtersContainer select').on('keypress', function(e) {
        if (e.which === 13) {
            $('#applyFilters').click();
        }
    });

    // حفظ الحالة عند تغيير الترتيب أو الصفحة
    table.on('order.dt', function() {
        savePageState();
    });

    table.on('page.dt', function() {
        savePageState();
    });

    table.on('length.dt', function() {
        savePageState();
    });
});

// دالة تطبيق الألوان الأولية
function applyInitialColors() {
    // تطبيق ألوان حالة شهادة الإنجاز
    $('.completion-certificate-status-select').each(function() {
        var value = $(this).val();
        $(this).removeClass('certificate-status-not_attached certificate-status-attached certificate-status-not_applicable');
        $(this).addClass('certificate-status-' + value);
    });

    // تطبيق ألوان تأكيد شهادة الإنجاز
    $('.completion-certificate-confirmation-select').each(function() {
        var value = $(this).val();
        $(this).removeClass('confirmation-status-empty confirmation-status-confirmed confirmation-status-accepted confirmation-status-rejected');
        $(this).addClass('confirmation-status-' + value);
    });
}

// دالة تحديث تاريخ الشهادة يدوياً
function updateCertificateDate(workOrderId, dateField, value, element) {
    if (!workOrderId || !dateField) {
        showAlert('error', 'معاملات مطلوبة مفقودة');
        return;
    }

    const currentDataOriginal = $(element).attr('data-original-value');
    const originalValue = currentDataOriginal !== undefined ? currentDataOriginal : element.defaultValue;
    
    if (value === originalValue) {
        return; // لم تتغير القيمة
    }

    $(element).attr('data-original-value', value);

    $(element).addClass('updating-field');

    $.ajax({
        url: 'update-field-ajax.php',
        method: 'POST',
        data: {
            id: workOrderId,
            field: dateField,
            value: value || ''
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(element).removeClass('updating-field').addClass('field-updated');
                setTimeout(function() {
                    $(element).removeClass('field-updated');
                }, 2000);
                showAlert('success', response.message || 'تم تحديث التاريخ بنجاح');
            } else {
                $(element).removeClass('updating-field').addClass('field-error');
                setTimeout(function() {
                    $(element).removeClass('field-error');
                }, 2000);
                showAlert('error', response.message || 'حدث خطأ أثناء التحديث');
            }
        },
        error: function() {
            $(element).removeClass('updating-field').addClass('field-error');
            setTimeout(function() {
                $(element).removeClass('field-error');
            }, 2000);
            showAlert('error', 'حدث خطأ في الاتصال بالخادم');
        }
    });
}

// دالة عرض تفاصيل أمر العمل
function viewWorkOrder(workOrderId) {
    console.log('viewWorkOrder called with ID:', workOrderId);

    $.ajax({
        url: 'view-work-order.php',
        method: 'GET',
        data: { id: workOrderId },
        dataType: 'json',
        beforeSend: function() {
            console.log('Sending AJAX request...');
            // عرض مؤشر التحميل
            $('#viewWorkOrderModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div></div>');
            // فتح النافذة مباشرة
            var modal = new bootstrap.Modal(document.getElementById('viewWorkOrderModal'));
            modal.show();
        },
        success: function(response) {
            console.log('AJAX success:', response);
            if (response.success) {
                $('#viewWorkOrderModalBody').html(response.html);
            } else {
                $('#viewWorkOrderModalBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (response.message || 'حدث خطأ أثناء جلب البيانات') + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX error:', {xhr: xhr, status: status, error: error});
            console.log('Response text:', xhr.responseText);
            $('#viewWorkOrderModalBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>حدث خطأ في الاتصال بالخادم</div>');
        }
    });
}

// دالة حذف أمر العمل
function deleteWorkOrder(workOrderId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف أمر العمل نهائياً ولا يمكن التراجع عن هذا الإجراء',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete-work-order.php',
                method: 'POST',
                data: { id: workOrderId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحذف!',
                            text: 'تم حذف أمر العمل بنجاح',
                            confirmButtonText: 'موافق'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: response.message || 'حدث خطأ أثناء حذف أمر العمل',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: 'حدث خطأ في الاتصال بالخادم',
                        confirmButtonText: 'موافق'
                    });
                }
            });
        }
    });
}

// دالة عرض نافذة تعديل أمر العمل
function editWorkOrder(workOrderId) {
    $.ajax({
        url: 'edit-work-order.php',
        method: 'GET',
        data: { id: workOrderId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editWorkOrderModalBody').html(response.html);
                $('#editWorkOrderModal').modal('show');
            } else {
                showAlert('error', response.message || 'حدث خطأ أثناء جلب البيانات');
            }
        },
        error: function() {
            showAlert('error', 'حدث خطأ في الاتصال بالخادم');
        }
    });
}

// دالة فتح نافذة إضافة أمر عمل
function openCreateModal() {
    // إعادة تعيين النموذج
    $('#createWorkOrderForm')[0].reset();

    // تعيين التاريخ الحالي كافتراضي لتاريخ التكليف
    const today = new Date().toISOString().split('T')[0];
    $('#assignment_date').val(today);

    // مسح رقم أمر العمل والتحقق من صحته
    $('#createWorkOrderModal #work_order_number').val('').removeClass('work-order-valid work-order-invalid');
    $('#work_order_number_help').removeClass('work-order-help-valid work-order-help-invalid')
        .html('<i class="fas fa-info-circle me-1"></i>أدخل 9 أرقام بالضبط');

    // إظهار النافذة
    $('#createWorkOrderModal').modal('show');
}

// دالة حفظ أمر العمل الجديد
function submitCreateForm() {
    // التحقق من صحة رقم أمر العمل قبل الإرسال
    const workOrderNumber = $('#work_order_number').val();

    if (!workOrderNumber || workOrderNumber.length !== 9 || !/^\d{9}$/.test(workOrderNumber)) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ في رقم أمر العمل!',
            text: 'يجب إدخال رقم أمر العمل مكون من 9 أرقام بالضبط',
            confirmButtonText: 'موافق'
        });
        $('#work_order_number').focus();
        return;
    }

    // تنظيف القيم من الفواصل قبل الإرسال
    const actualValueInput = $('#actual_value');
    const estimatedValueInput = $('#estimated_value');

    const actualValueRaw = actualValueInput.val().replace(/,/g, '');
    const estimatedValueRaw = estimatedValueInput.val().replace(/,/g, '');

    actualValueInput.val(actualValueRaw);
    estimatedValueInput.val(estimatedValueRaw);

    const form = $('#createWorkOrderForm')[0];
    const formData = new FormData(form);

    // إعادة تنسيق القيم بالفواصل
    if (actualValueRaw && !isNaN(actualValueRaw)) {
        const formattedActualValue = parseFloat(actualValueRaw).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        actualValueInput.val(formattedActualValue);
    }

    if (estimatedValueRaw && !isNaN(estimatedValueRaw)) {
        const formattedEstimatedValue = parseFloat(estimatedValueRaw).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        estimatedValueInput.val(formattedEstimatedValue);
    }

    const submitBtn = $('#createWorkOrderModal .modal-footer .btn-primary');
    const originalText = submitBtn.html();

    // تعطيل الزر أثناء الإرسال
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...');

    $.ajax({
        url: 'create-ajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم بنجاح!',
                    text: 'تم إنشاء أمر العمل بنجاح',
                    confirmButtonText: 'موافق'
                }).then(() => {
                    $('#createWorkOrderModal').modal('hide');
                    // Reload with new work order ID to highlight it
                    if (response.data && response.data.id) {
                        window.location.href = window.location.pathname + '?new_work_order=' + response.data.id;
                    } else {
                        location.reload();
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: response.message || 'حدث خطأ أثناء إنشاء أمر العمل',
                    confirmButtonText: 'موافق'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            let errorMessage = 'حدث خطأ أثناء الاتصال بالخادم';

            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    console.error('Failed to parse error response:', e);
                }
            }

            Swal.fire({
                icon: 'error',
                title: 'خطأ في الاتصال!',
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

// دالة لفتح مدير المرفقات
function openAttachmentsManager(workOrderId) {
    if (!workOrderId) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'معرف أمر العمل غير صحيح'
        });
        return;
    }

    const url = `attachments-manager.php?work_order_id=${workOrderId}`;
    const windowFeatures = 'width=1400,height=900,scrollbars=yes,resizable=yes,location=no,menubar=no,toolbar=no';

    window.open(url, 'attachmentsManager', windowFeatures);
}

// دالة عرض التنبيهات (Toast صغير في أسفل الشاشة)
function showAlert(type, message, title = null) {
    const alertTypes = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
    };

    // استخدام Toast للرسائل الصغيرة
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: alertTypes[type] || 'info',
        title: message
    });
}

// دالة تحديث عرض القيم الفعلية
function updateActualValueDisplay() {
    $('.actual-value-input').each(function() {
        if (!$(this).is(':focus')) {
            formatActualValue(this);
        }
    });
}

// دالة تحديث حقل أمر العمل
function updateWorkOrderField(workOrderId, field, value, element) {
    // التحقق من صحة المعاملات
    if (!workOrderId || !field || !element) {
        showAlert('error', 'معاملات مطلوبة مفقودة');
        return;
    }

    const currentDataOriginal = $(element).attr('data-original-value');
    const originalValue = currentDataOriginal !== undefined ? currentDataOriginal : element.defaultValue;
    
    if (value === originalValue) {
        return; // لم تتغير القيمة
    }

    $(element).attr('data-original-value', value);

    // إظهار مؤشر التحميل
    // إضافة مؤشر بصري للتحديث
    $(element).addClass('updating-field');

    // إعداد البيانات للإرسال
    const data = {
        id: workOrderId,
        field: field,
        value: value || ''
    };

    // إرسال طلب AJAX
    $.ajax({
        url: 'update-field-ajax.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // تطبيق الألوان الخاصة بالحقل
                applyFieldColors(element, field, value || '');

                // إضافة تأثير بصري للنجاح
                $(element).removeClass('updating-field').addClass('field-updated');
                setTimeout(function() {
                    $(element).removeClass('field-updated');
                }, 2000);

                // عرض رسالة نجاح
                showAlert('success', response.message || 'تم تحديث البيانات بنجاح');
            } else {
                // إرجاع القيمة الأصلية
                $(element).val(originalValue);

                // إضافة تأثير بصري للخطأ
                $(element).removeClass('updating-field').addClass('field-error');
                setTimeout(function() {
                    $(element).removeClass('field-error');
                }, 2000);

                // عرض رسالة خطأ
                showAlert('error', response.message || 'حدث خطأ أثناء تحديث البيانات');
            }
        },
        error: function(xhr, status, error) {
            // إرجاع القيمة الأصلية
            $(element).val(originalValue);

            // إضافة تأثير بصري للخطأ
            $(element).removeClass('updating-field').addClass('field-error');
            setTimeout(function() {
                $(element).removeClass('field-error');
            }, 2000);

            // عرض رسالة خطأ مفصلة
            let errorMessage = 'حدث خطأ في الاتصال بالخادم';
            if (xhr.status === 404) {
                errorMessage = 'الملف المطلوب غير موجود';
            } else if (xhr.status === 500) {
                errorMessage = 'خطأ في الخادم';
            } else if (xhr.status === 0) {
                errorMessage = 'لا يمكن الاتصال بالخادم';
            }

            showAlert('error', errorMessage);
        }
    });
}

// دالة تحديث حقل أمر العمل مع callback
function updateWorkOrderFieldWithCallback(workOrderId, field, value, element, successCallback) {
    // التحقق من صحة المعاملات
    if (!workOrderId || !field || !element) {
        showAlert('error', 'معاملات مطلوبة مفقودة');
        return;
    }

    // إظهار مؤشر التحميل
    const originalValue = value || '';
    $(element).prop('disabled', true);

    // إضافة مؤشر بصري للتحديث
    $(element).addClass('updating-field');

    // إعداد البيانات للإرسال
    const data = {
        id: workOrderId,
        field: field,
        value: originalValue
    };

    // إرسال طلب AJAX
    $.ajax({
        url: 'update-field-ajax.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // تطبيق الألوان الخاصة بالحقل
                applyFieldColors(element, field, originalValue);

                // إضافة تأثير بصري للنجاح
                $(element).removeClass('updating-field').addClass('field-updated');
                setTimeout(function() {
                    $(element).removeClass('field-updated');
                }, 2000);

                // تنفيذ callback إذا تم تمريره
                if (typeof successCallback === 'function') {
                    successCallback();
                }

                // عرض رسالة نجاح
                showAlert('success', response.message || 'تم تحديث البيانات بنجاح');
            } else {
                // إرجاع القيمة الأصلية
                $(element).val(originalValue);

                // إضافة تأثير بصري للخطأ
                $(element).removeClass('updating-field').addClass('field-error');
                setTimeout(function() {
                    $(element).removeClass('field-error');
                }, 2000);

                // عرض رسالة خطأ
                showAlert('error', response.message || 'حدث خطأ أثناء تحديث البيانات');
            }
        },
        error: function(xhr, status, error) {
            // إرجاع القيمة الأصلية
            $(element).val(originalValue);

            // إضافة تأثير بصري للخطأ
            $(element).removeClass('updating-field').addClass('field-error');
            setTimeout(function() {
                $(element).removeClass('field-error');
            }, 2000);

            // عرض رسالة خطأ مفصلة
            let errorMessage = 'حدث خطأ في الاتصال بالخادم';
            if (xhr.status === 404) {
                errorMessage = 'الملف المطلوب غير موجود';
            } else if (xhr.status === 500) {
                errorMessage = 'خطأ في الخادم';
            } else if (xhr.status === 0) {
                errorMessage = 'لا يمكن الاتصال بالخادم';
            }

            showAlert('error', errorMessage);
        },
        complete: function() {
            // إعادة تفعيل العنصر
            $(element).prop('disabled', false);
        }
    });
}

// دالة تطبيق ألوان الحقول
function applyFieldColors(element, field, value) {
    if (field === 'completion_certificate_status') {
        // إزالة جميع كلاسات الألوان القديمة
        $(element).removeClass('certificate-status-not_attached certificate-status-attached certificate-status-not_applicable');
        // إضافة الكلاس الجديد
        $(element).addClass('certificate-status-' + value);
    } else if (field === 'completion_certificate_confirmation') {
        // إزالة جميع كلاسات الألوان القديمة
        $(element).removeClass('confirmation-status-empty confirmation-status-confirmed confirmation-status-accepted confirmation-status-rejected');
        // إضافة الكلاس الجديد
        $(element).addClass('confirmation-status-' + value);
    }
}

// دالة تحديث حالة النموذج
function updateFormStatus(workOrderId, formType, value, element) {
    // التحقق من صحة المعاملات
    if (!workOrderId || !formType || !element) {
        showAlert('error', 'معاملات مطلوبة مفقودة');
        return;
    }

    // إظهار مؤشر التحميل
    const originalValue = $(element).data('original-value') || $(element).val() || '';
    $(element).data('original-value', originalValue);
    $(element).prop('disabled', true);

    // إضافة مؤشر بصري للتحديث
    $(element).addClass('updating-field');

    // إعداد البيانات للإرسال
    const data = {
        work_order_id: workOrderId,
        form_type: formType,
        status: value || 'not_attached'
    };

    // إرسال طلب AJAX
    $.ajax({
        url: 'update-form-status-ajax.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // تطبيق الألوان الخاصة بالحالة
                $(element).removeClass('form-status-not_attached form-status-attached form-status-not_applicable');
                $(element).addClass('form-status-' + value);

                // إضافة تأثير بصري للنجاح
                $(element).removeClass('updating-field').addClass('field-updated');
                setTimeout(function() {
                    $(element).removeClass('field-updated');
                }, 2000);

                // عرض رسالة نجاح
                showAlert('success', response.message || 'تم تحديث حالة النموذج بنجاح');
            } else {
                // إرجاع القيمة الأصلية
                $(element).val(originalValue);

                // إضافة تأثير بصري للخطأ
                $(element).removeClass('updating-field').addClass('field-error');
                setTimeout(function() {
                    $(element).removeClass('field-error');
                }, 2000);

                // عرض رسالة خطأ
                showAlert('error', response.message || 'حدث خطأ أثناء تحديث حالة النموذج');
            }
        },
        error: function(xhr, status, error) {
            // إرجاع القيمة الأصلية
            $(element).val(originalValue);

            // إضافة تأثير بصري للخطأ
            $(element).removeClass('updating-field').addClass('field-error');
            setTimeout(function() {
                $(element).removeClass('field-error');
            }, 2000);

            // عرض رسالة خطأ مفصلة
            let errorMessage = 'حدث خطأ في الاتصال بالخادم';
            if (xhr.status === 404) {
                errorMessage = 'الملف المطلوب غير موجود';
            } else if (xhr.status === 500) {
                errorMessage = 'خطأ في الخادم';
            } else if (xhr.status === 0) {
                errorMessage = 'لا يمكن الاتصال بالخادم';
            }

            showAlert('error', errorMessage);
        },
        complete: function() {
            // إعادة تفعيل العنصر
            $(element).prop('disabled', false);
        }
    });
}

// دالة تنسيق القيمة الفعلية
function formatActualValue(element) {
    const rawValue = $(element).data('raw-value') || 0;
    const formattedValue = parseFloat(rawValue).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    $(element).val(formattedValue);
}

// دالة إرسال نموذج التعديل
function submitEditForm() {
    const form = $('#editWorkOrderForm')[0];
    if (!form) {
        showAlert('error', 'لم يتم العثور على النموذج');
        return;
    }

    const formData = new FormData(form);

    // تعطيل زر الإرسال
    const submitBtn = $('#editWorkOrderModal .modal-footer .btn-primary');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جارٍ الحفظ...');

    $.ajax({
        url: 'update-work-order-ajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم بنجاح!',
                    text: 'تم تحديث أمر العمل بنجاح',
                    confirmButtonText: 'موافق'
                }).then(() => {
                    $('#editWorkOrderModal').modal('hide');
                    location.reload(); // إعادة تحميل الصفحة لإظهار التحديثات
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: response.message || 'حدث خطأ أثناء تحديث أمر العمل',
                    confirmButtonText: 'موافق'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ في الاتصال!',
                text: 'حدث خطأ أثناء الاتصال بالخادم',
                confirmButtonText: 'موافق'
            });
        },
        complete: function() {
            // إعادة تفعيل الزر
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
}

// دالة إدارة المرفقات
function manageAttachments(workOrderId) {
    if (!workOrderId) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'معرف أمر العمل غير صحيح'
        });
        return;
    }

    const url = 'attachments-manager.php?work_order_id=' + workOrderId;
    const windowFeatures = 'width=1000,height=700,scrollbars=yes,resizable=yes';

    window.open(url, 'attachmentsManager', windowFeatures);
}

// دالة إلغاء تنسيق القيمة الفعلية
function unformatActualValue(element) {
    // الحصول على القيمة الحالية وإزالة التنسيق
    let currentValue = $(element).val().replace(/,/g, '');

    // إذا كانت القيمة صحيحة، تحديث القيمة الخام المحفوظة
    if (!isNaN(currentValue) && currentValue !== '') {
        $(element).data('raw-value', currentValue);
        $(element).val(parseFloat(currentValue).toFixed(2));
    } else {
        // إذا لم تكن صحيحة، استخدام القيمة الخام المحفوظة
        const rawValue = $(element).data('raw-value') || 0;
        $(element).val(parseFloat(rawValue).toFixed(2));
    }
}

// دالة تحديث حقل القيمة الفعلية
function updateActualValueField(workOrderId, element) {
    // الحصول على القيمة المدخلة وتنظيفها
    let inputValue = $(element).val().replace(/,/g, '');

    // التحقق من صحة القيمة
    if (isNaN(inputValue) || inputValue === '') {
        // إرجاع القيمة السابقة
        const rawValue = $(element).data('raw-value') || 0;
        $(element).val(parseFloat(rawValue).toFixed(2));
        showAlert('warning', 'يرجى إدخال قيمة رقمية صحيحة');
        return;
    }

    // التحقق من أن القيمة ليست سالبة
    if (parseFloat(inputValue) < 0) {
        const rawValue = $(element).data('raw-value') || 0;
        $(element).val(parseFloat(rawValue).toFixed(2));
        showAlert('warning', 'لا يمكن أن تكون القيمة سالبة');
        return;
    }

    // تحديث القيمة الخام المحفوظة
    $(element).data('raw-value', inputValue);

    // استدعاء دالة التحديث العامة لحفظ القيمة في قاعدة البيانات
    // نحتاج إلى تمرير دالة callback لتطبيق التنسيق بعد النجاح
    updateWorkOrderFieldWithCallback(workOrderId, 'actual_value', inputValue, element, function() {
        // تطبيق التنسيق بعد النجاح
        formatActualValue(element);
    });
}

// دالة تنسيق القيمة الفعلية للإنشاء
function formatCreateActualValue(element) {
    let value = $(element).val().replace(/,/g, '');
    if (value && !isNaN(value)) {
        const formattedValue = parseFloat(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        $(element).val(formattedValue);
    }
}

// دالة إلغاء تنسيق القيمة الفعلية للإنشاء
function unformatCreateActualValue(element) {
    let value = $(element).val().replace(/,/g, '');
    if (value && !isNaN(value)) {
        $(element).val(parseFloat(value).toFixed(2));
    }
}

// دالة تنسيق القيمة المقدرة للإنشاء
function formatCreateEstimatedValue(element) {
    let value = $(element).val().replace(/,/g, '');
    if (value && !isNaN(value)) {
        const formattedValue = parseFloat(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        $(element).val(formattedValue);
    }
}

// دالة إلغاء تنسيق القيمة المقدرة للإنشاء
function unformatCreateEstimatedValue(element) {
    let value = $(element).val().replace(/,/g, '');
    if (value && !isNaN(value)) {
        $(element).val(parseFloat(value).toFixed(2));
    }
}

// دالة إغلاق الإشعار السفلي
function closeBottomNotification(element) {
    $(element).closest('.bottom-notification').fadeOut(300, function() {
        $(this).remove();
    });
}

// دالة فتح نافذة التصدير
function openExportModal() {
    $('#exportModal').modal('show');
}

// دالة بدء التصدير - تصدير أوامر العمل حسب الفلاتر المختارة
function startExport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);

    // الحصول على جميع خيارات التصدير من النموذج
    const format = formData.get('format');
    const status = formData.get('status');
    const department = formData.get('department');
    const branchId = formData.get('branch_id');
    const includeExtracts = formData.get('include_extracts') ? '1' : '0';
    const includeAttachments = formData.get('include_attachments') ? '1' : '0';

    // بناء URL التصدير - استخدام الفلاتر المختارة من النموذج
    let exportUrl = `export.php?format=${format}`;
    exportUrl += `&status=${encodeURIComponent(status)}`;
    exportUrl += `&department=${encodeURIComponent(department)}`;
    exportUrl += `&branch_id=${encodeURIComponent(branchId)}`;
    exportUrl += `&include_extracts=${includeExtracts}`;
    exportUrl += `&include_attachments=${includeAttachments}`;

    // إغلاق النافذة المنبثقة
    $('#exportModal').modal('hide');

    // إظهار رسالة تحميل
    Swal.fire({
        title: 'جاري التصدير...',
        html: '<div class="text-center"><i class="fas fa-file-excel fa-3x text-success mb-3"></i><br>يتم تحضير ملف Excel بجميع أوامر العمل</div>',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // بدء التحميل
    window.location.href = exportUrl;

    // إخفاء رسالة التحميل بعد فترة
    setTimeout(() => {
        Swal.close();
    }, 3000);
}

// دالة التصدير القديمة (للتوافق مع الإصدارات السابقة)
function exportWorkOrders() {
    openExportModal();
}

// دالة الطباعة
function printWorkOrders() {
    window.print();
}

// دالة تبديل حالة المفضلة
function toggleFavorite(workOrderId, element) {
    // تعطيل النجمة مؤقتاً
    $(element).css('pointer-events', 'none').css('opacity', '0.5');

    $.ajax({
        url: 'toggle-favorite-ajax.php',
        method: 'POST',
        data: { work_order_id: workOrderId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // تحديث النجمة بناءً على الحالة الجديدة من السيرفر
                const isFavorite = parseInt(response.is_favorite) === 1;

                // تحديث data attribute
                $(element).attr('data-is-favorite', isFavorite ? '1' : '0');

                if (isFavorite) {
                    $(element).removeClass('far').addClass('fas active');
                    $(element).css('color', '#ffc107');
                    $(element).attr('title', 'إزالة من المفضلة');
                } else {
                    $(element).removeClass('fas active').addClass('far');
                    $(element).css('color', '#6c757d');
                    $(element).attr('title', 'إضافة إلى المفضلة');
                }

                // عرض رسالة نجاح
                showAlert('success', response.message);
            } else {
                showAlert('error', response.message || 'حدث خطأ أثناء تحديث المفضلة');
            }
        },
        error: function(xhr, status, error) {
            showAlert('error', 'حدث خطأ في الاتصال بالخادم');
        },
        complete: function() {
            // إعادة تفعيل النجمة
            $(element).css('pointer-events', 'auto').css('opacity', '1');
        }
    });
}

// دوال حفظ واسترجاع إعدادات الصفحة
function savePageState() {
    if (typeof table === 'undefined') return;

    const state = {
        order: table.order(),
        search: table.search(),
        page: table.page(),
        length: table.page.len(),
        formsViewActive: formsViewActive || false,
        quickFilter: window.activeQuickFilter || '',
        filters: {
            department: $('#filterDepartment').val(),
            currentEntity: $('#filterCurrentEntity').val(),
            branch: $('#filterBranch').val(),
            dateFrom: $('#filterDateFrom').val(),
            dateTo: $('#filterDateTo').val(),
            completionCertificate: $('#filterCompletionCertificate').val(),
            certificateConfirmation: $('#filterCertificateConfirmation').val(),
            disbursementStatus: $('#filterDisbursementStatus').val(),
            preciseDrilling: $('#filterPreciseDrilling').val(),
            excavation: $('#filterExcavation').val(),
            demolition: $('#filterDemolition').val(),
            f1Form: $('#filterF1Form').val(),
            assetsReceipt: $('#filterAssetsReceipt').val()
        }
    };

    localStorage.setItem('workOrdersPageState', JSON.stringify(state));
}

function restorePageState() {
    const savedState = localStorage.getItem('workOrdersPageState');
    if (!savedState) return null;

    try {
        return JSON.parse(savedState);
    } catch (e) {
        console.error('Error parsing saved state:', e);
        return null;
    }
}

</script>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// تهيئة Select2 لجميع الفلاتر المتعددة بنمط Excel
$(document).ready(function() {
    // الفلاتر المتعددة
    const multiSelectFilters = [
        '#filterPreciseDrilling',
        '#filterExcavation',
        '#filterDemolition',
        '#filterF1Form',
        '#filterAssetsReceipt',
        '#filterCompletionCertificate',
        '#filterCertificateConfirmation',
        '#filterDisbursementStatus'
    ];

    // تهيئة Select2 للفلاتر المتعددة
    $(multiSelectFilters.join(', ')).select2({
        theme: 'bootstrap-5',
        placeholder: 'اختر...',
        allowClear: true,
        closeOnSelect: false,
        width: '100%',
        dir: 'rtl',
        language: {
            noResults: function() {
                return "لا توجد نتائج";
            },
            searching: function() {
                return "جاري البحث...";
            }
        },
        templateResult: function(state) {
            if (!state.id) {
                return state.text;
            }

            // إضافة أيقونة checkbox
            var $state = $(
                '<div class="d-flex align-items-center">' +
                    '<input type="checkbox" class="form-check-input me-2" ' +
                    (state.selected ? 'checked' : '') + ' style="pointer-events: none;">' +
                    '<span>' + state.text + '</span>' +
                '</div>'
            );
            return $state;
        }
    });

    // الفلاتر الفردية
    const singleSelectFilters = [
        '#filterDepartment',
        '#filterCurrentEntity',
        '#filterBranch'
    ];

    // تهيئة Select2 للفلاتر الفردية
    $(singleSelectFilters.join(', ')).select2({
        theme: 'bootstrap-5',
        placeholder: 'اختر...',
        allowClear: true,
        width: '100%',
        dir: 'rtl',
        language: {
            noResults: function() {
                return "لا توجد نتائج";
            },
            searching: function() {
                return "جاري البحث...";
            }
        }
    });

    // تحديث checkbox عند التحديد
    $(multiSelectFilters.join(', ')).on('select2:select select2:unselect', function(e) {
        $(this).trigger('change');
    });

    // ===== ميزة التنقل بالأسهم والنسخ السهل والتحديد المتعدد =====
    let selectedCell = null;
    let selectedCells = new Set();
    let selectionStart = null;
    let isDragging = false;
    let dragStartCell = null;

    // معالج mousedown على الخلايا (بداية السحب)
    $('#workOrdersTable tbody').on('mousedown', 'td', function(e) {
        // تجاهل النقر على الأزرار والروابط
        if ($(e.target).closest('button, a, .btn').length) {
            return;
        }

        // تجاهل الزر الأيمن
        if (e.button !== 0) {
            return;
        }

        isDragging = true;
        dragStartCell = this;

        const $cell = $(this);

        // Shift+Click: تحديد نطاق من الخلايا
        if (e.shiftKey && selectionStart) {
            clearCellSelection();
            selectCellRange(selectionStart, this);
            selectedCell = this;
            return;
        }

        // Ctrl+Click: إضافة/إزالة خلية من التحديد
        if (e.ctrlKey || e.metaKey) {
            if (selectedCells.has(this)) {
                selectedCells.delete(this);
                $cell.removeClass('cell-selected');
            } else {
                selectedCells.add(this);
                $cell.addClass('cell-selected');
            }
            selectedCell = this;
            selectionStart = this;
            return;
        }

        // النقر العادي: تحديد خلية واحدة
        clearCellSelection();
        selectedCell = this;
        selectedCells.add(this);
        $cell.addClass('cell-selected');
        selectionStart = this;
    });

    // معالج mouseover على الخلايا (أثناء السحب)
    $('#workOrdersTable tbody').on('mouseover', 'td', function(e) {
        if (!isDragging || !dragStartCell) {
            return;
        }

        // تجاهل النقر على الأزرار والروابط
        if ($(e.target).closest('button, a, .btn').length) {
            return;
        }

        // تحديد النطاق من dragStartCell إلى الخلية الحالية
        clearCellSelection();
        selectCellRange(dragStartCell, this);
        selectedCell = this;
    });

    // معالج mouseup (نهاية السحب)
    $(document).on('mouseup', function(e) {
        isDragging = false;
    });

    // منع تحديد النص الافتراضي عند السحب
    $('#workOrdersTable tbody').on('selectstart', 'td', function(e) {
        if (isDragging) {
            e.preventDefault();
            return false;
        }
    });

    // معالج لوحة المفاتيح للتنقل بالأسهم
    $(document).on('keydown', function(e) {
        if (!selectedCell) return;

        const $cell = $(selectedCell);
        const $row = $cell.closest('tr');
        let $nextCell = null;

        switch(e.which) {
            case 37: // السهم الأيسر (في العربية = اليمين)
                $nextCell = $cell.next('td');
                break;
            case 39: // السهم الأيمن (في العربية = اليسار)
                $nextCell = $cell.prev('td');
                break;
            case 38: // السهم لأعلى
                $nextCell = $row.prev('tr').find('td').eq($cell.index());
                break;
            case 40: // السهم لأسفل
                $nextCell = $row.next('tr').find('td').eq($cell.index());
                break;
            case 67: // Ctrl+C للنسخ
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if (selectedCells.size > 1) {
                        copyMultipleCells();
                    } else {
                        copyCell($cell);
                    }
                    return;
                }
                break;
            default:
                return;
        }

        if ($nextCell && $nextCell.length) {
            e.preventDefault();
            $cell.removeClass('cell-selected');
            selectedCell = $nextCell[0];
            $nextCell.addClass('cell-selected');

            // التمرير إلى الخلية المحددة إذا لزم الأمر
            $nextCell[0].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }
    });

    // دالة إزالة التحديد من جميع الخلايا
    function clearCellSelection() {
        selectedCells.forEach(function(cell) {
            $(cell).removeClass('cell-selected');
        });
        selectedCells.clear();
    }

    // دالة تحديد نطاق من الخلايا (Shift+Click)
    function selectCellRange(startCell, endCell) {
        const $startCell = $(startCell);
        const $endCell = $(endCell);
        const $table = $('#workOrdersTable tbody');

        const startRowIndex = $startCell.closest('tr').index();
        const endRowIndex = $endCell.closest('tr').index();
        const startColIndex = $startCell.index();
        const endColIndex = $endCell.index();

        const minRow = Math.min(startRowIndex, endRowIndex);
        const maxRow = Math.max(startRowIndex, endRowIndex);
        const minCol = Math.min(startColIndex, endColIndex);
        const maxCol = Math.max(startColIndex, endColIndex);

        $table.find('tr').each(function(rowIndex) {
            if (rowIndex >= minRow && rowIndex <= maxRow) {
                $(this).find('td').each(function(colIndex) {
                    if (colIndex >= minCol && colIndex <= maxCol) {
                        selectedCells.add(this);
                        $(this).addClass('cell-selected');
                    }
                });
            }
        });
    }

    // دالة نسخ خلايا متعددة
    function copyMultipleCells() {
        const rows = {};

        // تنظيم الخلايا حسب الصفوف
        selectedCells.forEach(function(cell) {
            const $cell = $(cell);
            const rowIndex = $cell.closest('tr').index();
            const colIndex = $cell.index();

            if (!rows[rowIndex]) {
                rows[rowIndex] = {};
            }
            rows[rowIndex][colIndex] = getCellText($cell);
        });

        // بناء النص للنسخ (مع Tabs و Newlines مثل Excel)
        let text = '';
        const sortedRows = Object.keys(rows).sort((a, b) => a - b);

        sortedRows.forEach(function(rowIndex, index) {
            const row = rows[rowIndex];
            const sortedCols = Object.keys(row).sort((a, b) => a - b);

            text += sortedCols.map(colIndex => row[colIndex]).join('\t');

            if (index < sortedRows.length - 1) {
                text += '\n';
            }
        });

        // نسخ إلى الحافظة
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyNotification($(selectedCell), selectedCells.size + ' خلايا');
            }).catch(function(err) {
                console.error('فشل النسخ:', err);
            });
        } else {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopyNotification($(selectedCell), selectedCells.size + ' خلايا');
        }
    }

    // دالة الحصول على نص الخلية
    function getCellText($cell) {
        let text = $cell.text().trim();

        if ($cell.find('input').length) {
            text = $cell.find('input').val() || text;
        } else if ($cell.find('select').length) {
            text = $cell.find('select').val() || text;
        }

        return text;
    }

    // دالة نسخ محتوى الخلية
    function copyCell($cell) {
        // الحصول على النص من الخلية
        let text = $cell.text().trim();

        // إذا كانت الخلية تحتوي على عنصر input أو select
        if ($cell.find('input').length) {
            text = $cell.find('input').val() || text;
        } else if ($cell.find('select').length) {
            text = $cell.find('select').val() || text;
        }

        // نسخ إلى الحافظة
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                // إظهار تأثير بصري
                showCopyNotification($cell);
            }).catch(function(err) {
                console.error('فشل النسخ:', err);
            });
        } else {
            // طريقة بديلة للمتصفحات القديمة
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopyNotification($cell);
        }
    }

    // إظهار إشعار النسخ
    function showCopyNotification($cell, message = null) {
        const text = message || '✓ تم النسخ';
        const $notification = $('<div class="copy-notification"><small>' + text + '</small></div>');
        $cell.append($notification);

        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 1500);
    }

    // Double-click للنسخ السريع
    $('#workOrdersTable tbody').on('dblclick', 'td', function(e) {
        if ($(e.target).closest('button, a, .btn').length) {
            return;
        }
        copyCell($(this));
    });

    // معالج Escape لإلغاء التحديد
    $(document).on('keydown', function(e) {
        if (e.which === 27) { // Escape key
            clearCellSelection();
            selectedCell = null;
            selectionStart = null;
        }
    });
});
</script>

<style>
/* تنسيق الخلية المحددة */
#workOrdersTable tbody td.cell-selected {
    background-color: #e3f2fd !important;
    border: 2px solid #2196F3 !important;
    box-shadow: inset 0 0 5px rgba(33, 150, 243, 0.3);
    outline: none;
}

/* تنسيق إشعار النسخ */
.copy-notification {
    position: absolute;
    background-color: #4CAF50;
    color: white;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    z-index: 1000;
    pointer-events: none;
    animation: slideIn 0.3s ease-out;
}

.copy-notification small {
    font-size: 9px;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* تحسين تجربة المستخدم */
#workOrdersTable tbody td {
    cursor: cell;
    position: relative;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

#workOrdersTable tbody tr:hover td {
    background-color: #f5f5f5;
}
</style>

<script>
// تثبيت رأس جدول أوامر العمل عند التمرير
$(document).ready(function() {
    var $table = $('#workOrdersTable');
    if (!$table.length) return;

    var $scrollParent = $table.closest('.table-responsive');
    if (!$scrollParent.length) return;

    // إنشاء العنصر المثبت
    var $stickyWrap = $('<div id="workOrdersTable-sticky-header"></div>');
    $('body').append($stickyWrap);

    function buildStickyHeader() {
        var $thead = $table.find('thead');
        if (!$thead.length) return;

        // نسخ الرأس
        var $cloneTable = $('<table class="table table-hover"></table>');
        var $cloneThead = $thead.clone();
        $cloneTable.append($cloneThead);
        $stickyWrap.empty().append($cloneTable);

        // مزامنة عرض الأعمدة
        var $origThs = $thead.find('th');
        var $cloneThs = $cloneThead.find('th');
        $origThs.each(function(i) {
            $cloneThs.eq(i).css({
                'width': $(this).outerWidth() + 'px',
                'min-width': $(this).outerWidth() + 'px'
            });
        });

        $cloneTable.css('width', $table.outerWidth() + 'px');
        syncPosition();
    }

    function syncPosition() {
        var parentRect = $scrollParent[0].getBoundingClientRect();
        var scrollLeft = $scrollParent.scrollLeft();

        $stickyWrap.css({
            'width': parentRect.width + 'px',
            'left': parentRect.left + 'px'
        });

        // مزامنة التمرير الأفقي
        $stickyWrap.scrollLeft(scrollLeft);
    }

    function handleScroll() {
        var $thead = $table.find('thead');
        if (!$thead.length) return;

        var theadTop = $thead.offset().top;
        var tableBottom = $table.offset().top + $table.outerHeight() - $thead.outerHeight();
        var scrollTop = $(window).scrollTop();

        if (scrollTop > theadTop && scrollTop < tableBottom) {
            if ($stickyWrap.css('display') === 'none') {
                buildStickyHeader();
            }
            syncPosition();
            $stickyWrap.show();
        } else {
            $stickyWrap.hide();
        }
    }

    // ===== شريط التمرير الأفقي المثبت في الأسفل =====
    var $stickyScrollbar = $('<div id="workOrdersTable-sticky-scrollbar"><div class="scroll-inner"></div></div>');
    $('body').append($stickyScrollbar);
    var syncing = false;

    function updateScrollbar() {
        var parentRect = $scrollParent[0].getBoundingClientRect();
        $stickyScrollbar.css({
            'width': parentRect.width + 'px',
            'left': parentRect.left + 'px'
        });
        $stickyScrollbar.find('.scroll-inner').css('width', $table.outerWidth() + 'px');
    }

    function handleScrollbar() {
        var parentRect = $scrollParent[0].getBoundingClientRect();
        var viewportHeight = $(window).height();

        // إظهار شريط التمرير فقط إذا الجدول أعرض من الحاوية وأسفل الجدول تحت الشاشة
        var tableWider = $table.outerWidth() > $scrollParent.outerWidth();
        var bottomVisible = parentRect.bottom <= viewportHeight;

        if (tableWider && !bottomVisible && parentRect.top < viewportHeight) {
            updateScrollbar();
            $stickyScrollbar.show();
        } else {
            $stickyScrollbar.hide();
        }
    }

    // مزامنة التمرير: شريط مثبت -> الجدول
    $stickyScrollbar.on('scroll', function() {
        if (syncing) return;
        syncing = true;
        $scrollParent.scrollLeft($stickyScrollbar.scrollLeft());
        if ($stickyWrap.is(':visible')) {
            $stickyWrap.scrollLeft($stickyScrollbar.scrollLeft());
        }
        syncing = false;
    });

    // مزامنة التمرير: الجدول -> شريط مثبت + الرأس
    $scrollParent.on('scroll', function() {
        if (syncing) return;
        syncing = true;
        if ($stickyWrap.is(':visible')) {
            $stickyWrap.scrollLeft($scrollParent.scrollLeft());
        }
        if ($stickyScrollbar.is(':visible')) {
            $stickyScrollbar.scrollLeft($scrollParent.scrollLeft());
        }
        syncing = false;
    });

    $(window).on('scroll', function() {
        handleScroll();
        handleScrollbar();
    });
    $(window).on('resize', function() {
        if ($stickyWrap.is(':visible')) {
            buildStickyHeader();
        }
        handleScrollbar();
    });

    // إعادة بناء عند تحديث DataTable
    $table.on('draw.dt', function() {
        if ($stickyWrap.is(':visible')) {
            buildStickyHeader();
        }
        handleScrollbar();
    });

    // مزامنة سلسة عند إخفاء/إظهار القائمة الجانبية
    var $sidebarToggle = $('#sidebarToggle');
    if ($sidebarToggle.length) {
        $sidebarToggle.on('click', function() {
            var startTime = Date.now();
            var duration = 350; // مدة الانتقال

            function animateSync() {
                syncPosition();
                updateScrollbar();
                if (Date.now() - startTime < duration) {
                    requestAnimationFrame(animateSync);
                } else {
                    // تحديث نهائي بعد اكتمال الانتقال
                    if ($stickyWrap.is(':visible')) {
                        buildStickyHeader();
                    }
                    handleScrollbar();
                }
            }
            requestAnimationFrame(animateSync);
        });
    }
});
</script>

