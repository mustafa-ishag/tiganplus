<?php
/**
 * صفحة تصدير المستخلصات الجزئية
 * Partial Extracts Export Page
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

$pageTitle = 'تصدير المستخلصات الجزئية';
$user_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // معالجة طلب التصدير
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
        require_once __DIR__ . '/../../../includes/PartialExtractExcelExporter.php';
        
        $filters = [
            'branch_id' => $_POST['branch_id'] ?? '',
            'department' => $_POST['department'] ?? '',
            'approval_stage' => $_POST['approval_stage'] ?? '',
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? ''
        ];
        
        $exporter = new PartialExtractExcelExporter($db, $user_id, $filters);
        $exporter->export();
        exit();
    }
    
    // جلب الفروع للفلترة
    $branchesQuery = "SELECT id, name FROM branches WHERE status = 'active' ORDER BY name";
    $branches = $db->query($branchesQuery)->fetchAll();
    
    // جلب إحصائيات المستخلصات
    $statsQuery = "
        SELECT 
            COUNT(*) as total_extracts,
            COUNT(DISTINCT pewo.work_order_id) as total_work_orders,
            SUM(pe.total_amount) as total_amount,
            SUM(pe.tax_amount) as total_tax,
            SUM(pe.net_amount) as total_net
        FROM partial_extracts pe
        LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
    ";
    $stats = $db->query($statsQuery)->fetch();
    
    // جلب سجل العمليات الأخيرة
    $recentLogsQuery = "
        SELECT pel.*, u.full_name as user_name
        FROM partial_extract_import_export_logs pel
        LEFT JOIN users u ON pel.user_id = u.id
        WHERE pel.operation_type = 'export'
        ORDER BY pel.started_at DESC
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
                <li class="breadcrumb-item"><a href="index.php">المستخلصات الجزئية</a></li>
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
                                أوامر العمل
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
                                <?php echo number_format($stats['total_amount'] ?? 0, 2); ?> ر.س
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
                                <?php echo number_format($stats['total_net'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نموذج التصدير -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-2"></i>
                خيارات التصدير
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="branch_id" class="form-label">الفرع</label>
                        <select class="form-select" id="branch_id" name="branch_id">
                            <option value="">جميع الفروع</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>">
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="department" class="form-label">القسم</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">جميع الأقسام</option>
                            <option value="connections">التوصيلات</option>
                            <option value="projects">المشاريع</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="approval_stage" class="form-label">مرحلة الاعتماد</label>
                        <select class="form-select" id="approval_stage" name="approval_stage">
                            <option value="">جميع المراحل</option>
                            <option value="draft">مسودة</option>
                            <option value="pending_supervisor">في انتظار المشرف</option>
                            <option value="pending_manager">في انتظار المدير</option>
                            <option value="pending_finance">في انتظار المالية</option>
                            <option value="disbursed">مصروف</option>
                            <option value="taif_finance">مالية الطائف</option>
                            <option value="rejected">مرفوض</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="date_from" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="date_from" name="date_from">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="date_to" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="date_to" name="date_to">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="export" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>
                            تصدير إلى Excel
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            العودة للقائمة
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- سجل العمليات الأخيرة -->
    <?php if (!empty($recentLogs)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-history me-2"></i>
                سجل عمليات التصدير الأخيرة
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>المستخدم</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ الانتهاء</th>
                            <th>الحالة</th>
                            <th>عدد السجلات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                            <td><?php echo $log['started_at']; ?></td>
                            <td><?php echo $log['completed_at'] ?: '-'; ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'processing' => 'warning',
                                    'pending' => 'info'
                                ];
                                $statusText = [
                                    'completed' => 'مكتمل',
                                    'failed' => 'فاشل',
                                    'processing' => 'قيد المعالجة',
                                    'pending' => 'في الانتظار'
                                ];
                                $class = $statusClass[$log['status']] ?? 'secondary';
                                $text = $statusText[$log['status']] ?? $log['status'];
                                ?>
                                <span class="badge bg-<?php echo $class; ?>"><?php echo $text; ?></span>
                            </td>
                            <td><?php echo number_format($log['total_records']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين التخطيط
include __DIR__ . '/../../includes/layout.php';
?>
