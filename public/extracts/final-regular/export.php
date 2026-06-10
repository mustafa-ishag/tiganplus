<?php
/**
 * صفحة تصدير المستخلصات النهائية العادية
 * Final Regular Extracts Export Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_export')) {
    header('Location: index.php');
    exit();
}

$pageTitle = 'تصدير المستخلصات النهائية العادية';
$user_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // معالجة طلب التصدير
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
        require_once __DIR__ . '/../../../includes/FinalRegularExtractExcelExporter.php';
        
        $filters = [
            'branch_id' => $_POST['branch_id'] ?? '',
            'department' => $_POST['department'] ?? '',
            'approval_stage' => $_POST['approval_stage'] ?? '',
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? ''
        ];
        
        $exporter = new FinalRegularExtractExcelExporter($db, $user_id, $filters);
        $exporter->export();
        exit();
    }
    
    // جلب الفروع للفلترة
    $branchesQuery = "SELECT id, name FROM branches WHERE status = 'active' ORDER BY name";
    $branches = $db->query($branchesQuery)->fetchAll();
    
    // جلب إحصائيات المستخلصات
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT fre.id) as total_extracts,
            COUNT(DISTINCT frewo.work_order_id) as total_work_orders,
            SUM(fre.total_amount) as total_amount,
            SUM(fre.tax_amount) as total_tax,
            SUM(fre.total_penalty_amount) as total_penalty,
            SUM(fre.net_amount) as total_net
        FROM final_regular_extracts fre
        LEFT JOIN final_regular_extract_work_orders frewo ON fre.id = frewo.final_regular_extract_id
    ";
    $stats = $db->query($statsQuery)->fetch();
    
    // جلب سجل العمليات الأخيرة
    $recentLogsQuery = "
        SELECT frel.*, u.full_name as user_name
        FROM final_regular_extract_import_export_logs frel
        LEFT JOIN users u ON frel.user_id = u.id
        WHERE frel.operation_type = 'export'
        ORDER BY frel.started_at DESC
        LIMIT 10
    ";
    $recentLogs = $db->query($recentLogsQuery)->fetchAll();

} catch (Exception $e) {
    $error = 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage();
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-download me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/etganplus/public/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php">المستخلصات النهائية العادية</a></li>
                <li class="breadcrumb-item active">تصدير</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي المستخلصات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_extracts'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                إجمالي أوامر العمل
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_work_orders'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                إجمالي المبالغ
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_amount'] ?? 0, 2); ?> ريال
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                صافي المبالغ
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_net'] ?? 0, 2); ?> ريال
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- نموذج التصدير -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-file-excel me-2"></i>
                        تصدير البيانات
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="branch_id" class="form-label">الفرع</label>
                            <select name="branch_id" id="branch_id" class="form-select">
                                <option value="">جميع الفروع</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>">
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="department" class="form-label">القسم</label>
                            <select name="department" id="department" class="form-select">
                                <option value="">جميع الأقسام</option>
                                <option value="connections">التوصيلات</option>
                                <option value="projects">المشاريع</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="approval_stage" class="form-label">مرحلة الاعتماد</label>
                            <select name="approval_stage" id="approval_stage" class="form-select">
                                <option value="">جميع المراحل</option>
                                <option value="technical_support">الدعم الفني</option>
                                <option value="construction">الإنشاءات</option>
                                <option value="department_manager">مدير القسم</option>
                                <option value="administration_manager">مدير الإدارة</option>
                                <option value="taif_finance">مالية الطائف</option>
                                <option value="disbursed">تم الصرف</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_from" class="form-label">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_to" class="form-label">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="export" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>
                                تصدير إلى Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- تحميل نموذج الاستيراد -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-file-download me-2"></i>
                        نموذج الاستيراد
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">قم بتحميل نموذج Excel فارغ لاستيراد المستخلصات النهائية العادية</p>
                    <a href="export-template.php" class="btn btn-info">
                        <i class="fas fa-download me-2"></i>
                        تحميل النموذج
                    </a>
                </div>
            </div>
        </div>

        <!-- سجل العمليات الأخيرة -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i>
                        سجل عمليات التصدير الأخيرة
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLogs)): ?>
                        <p class="text-muted text-center">لا توجد عمليات تصدير سابقة</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>المستخدم</th>
                                        <th>عدد السجلات</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($log['started_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                            <td><?php echo number_format($log['records_count']); ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'success'): ?>
                                                    <span class="badge bg-success">نجح</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">فشل</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>

