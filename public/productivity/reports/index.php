<?php
/**
 * تقارير الإنتاجية المنطقية والمفيدة
 * Logical and Useful Productivity Reports
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_reports_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تقارير الإنتاجية';
$currentPage = 'productivity-reports';

// معالجة الفلاتر
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_id = $_GET['branch_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'daily_trends';

$db = getDB();

// جلب قائمة الفروع
$branchesStmt = $db->prepare("SELECT id, name FROM branches ORDER BY name");
$branchesStmt->execute();
$branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

// بناء شروط الاستعلام
$whereConditions = ["pdl.status = 'approved'"];
$params = [];

if (!empty($date_from)) {
    $whereConditions[] = "pdl.log_date >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $whereConditions[] = "pdl.log_date <= ?";
    $params[] = $date_to;
}
if (!empty($branch_id)) {
    $whereConditions[] = "wo.branch_id = ?";
    $params[] = $branch_id;
}

$whereClause = implode(' AND ', $whereConditions);

// الإحصائيات العامة
$summaryQuery = "
    SELECT
        COUNT(DISTINCT pdl.id) as total_logs,
        COUNT(DISTINCT wo.id) as total_work_orders,
        COUNT(DISTINCT wo.branch_id) as total_branches,
        SUM(pdl.quantity_completed) as total_quantity,
        SUM(pdl.quantity_completed * pwi.unit_price) as total_value,
        AVG(pdl.quantity_completed * pwi.unit_price) as avg_value_per_log,
        COUNT(DISTINCT pdl.log_date) as working_days
    FROM productivity_daily_logs pdl
    JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    WHERE $whereClause
";

$summaryStmt = $db->prepare($summaryQuery);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// بيانات التقرير حسب النوع
$reportData = [];
$chartLabels = [];
$chartData = [];

switch ($report_type) {
    case 'daily_trends':
        // اتجاهات القيمة اليومية
        $trendsQuery = "
            SELECT
                pdl.log_date,
                SUM(pdl.quantity_completed * pwi.unit_price) as daily_value,
                SUM(pdl.quantity_completed) as daily_quantity,
                COUNT(pdl.id) as daily_logs
            FROM productivity_daily_logs pdl
            JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
            JOIN work_orders wo ON pwi.work_order_id = wo.id
            WHERE $whereClause
            GROUP BY pdl.log_date
            ORDER BY pdl.log_date
        ";

        $trendsStmt = $db->prepare($trendsQuery);
        $trendsStmt->execute($params);
        $reportData = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

        $chartLabels = array_column($reportData, 'log_date');
        $chartData = [
            'current_values' => array_map('floatval', array_column($reportData, 'daily_value'))
        ];
        break;

    case 'weekly_trends':
        // اتجاهات القيمة الأسبوعية
        $weeklyQuery = "
            SELECT
                CONCAT('أسبوع ', WEEK(pdl.log_date, 1), '-', YEAR(pdl.log_date)) as week_label,
                YEAR(pdl.log_date) as year,
                WEEK(pdl.log_date, 1) as week_num,
                SUM(pdl.quantity_completed * pwi.unit_price) as weekly_value,
                COUNT(pdl.id) as weekly_logs,
                COUNT(DISTINCT pdl.log_date) as working_days
            FROM productivity_daily_logs pdl
            JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
            JOIN work_orders wo ON pwi.work_order_id = wo.id
            WHERE $whereClause
            GROUP BY YEAR(pdl.log_date), WEEK(pdl.log_date, 1)
            ORDER BY YEAR(pdl.log_date), WEEK(pdl.log_date, 1)
        ";

        $weeklyStmt = $db->prepare($weeklyQuery);
        $weeklyStmt->execute($params);
        $reportData = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

        $chartLabels = array_column($reportData, 'week_label');
        $chartData = [
            'current_values' => array_map('floatval', array_column($reportData, 'weekly_value'))
        ];
        break;

    case 'monthly_trends':
        // اتجاهات القيمة الشهرية
        $monthlyQuery = "
            SELECT
                DATE_FORMAT(pdl.log_date, '%Y-%m') as month_label,
                YEAR(pdl.log_date) as year,
                MONTH(pdl.log_date) as month,
                SUM(pdl.quantity_completed * pwi.unit_price) as monthly_value,
                COUNT(pdl.id) as monthly_logs,
                COUNT(DISTINCT pdl.log_date) as working_days
            FROM productivity_daily_logs pdl
            JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
            JOIN work_orders wo ON pwi.work_order_id = wo.id
            WHERE $whereClause
            GROUP BY YEAR(pdl.log_date), MONTH(pdl.log_date)
            ORDER BY YEAR(pdl.log_date), MONTH(pdl.log_date)
        ";

        $monthlyStmt = $db->prepare($monthlyQuery);
        $monthlyStmt->execute($params);
        $reportData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

        $chartLabels = array_column($reportData, 'month_label');
        $chartData = [
            'current_values' => array_map('floatval', array_column($reportData, 'monthly_value'))
        ];
        break;

    case 'branch_comparison':
        // مقارنة الفروع
        $branchQuery = "
            SELECT
                b.name as branch_name,
                SUM(pdl.quantity_completed * pwi.unit_price) as branch_value,
                COUNT(pdl.id) as branch_logs,
                COUNT(DISTINCT wo.id) as branch_work_orders,
                COUNT(DISTINCT pdl.log_date) as working_days
            FROM productivity_daily_logs pdl
            JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
            JOIN work_orders wo ON pwi.work_order_id = wo.id
            JOIN branches b ON wo.branch_id = b.id
            WHERE $whereClause
            GROUP BY b.id, b.name
            ORDER BY branch_value DESC
        ";

        $branchStmt = $db->prepare($branchQuery);
        $branchStmt->execute($params);
        $reportData = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

        $chartLabels = array_column($reportData, 'branch_name');
        $chartData = [
            'current_values' => array_map('floatval', array_column($reportData, 'branch_value'))
        ];
        break;
}

// تحويل البيانات إلى JSON للرسوم البيانية
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);

// بدء تخزين المحتوى
ob_start();
?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- عنوان الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-chart-bar text-primary"></i>
        تقارير الإنتاجية
    </h1>
    <div class="btn-group" role="group">
        <a href="../index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> العودة للإنتاجية
        </a>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line me-2"></i>
                        تقارير الإنتاجية المنطقية
                    </h3>
                </div>
                
                <div class="card-body">
                    <!-- فلاتر التقرير -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($date_from ?? '') ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($date_to ?? '') ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="branch_id" class="form-label">الفرع</label>
                            <select class="form-select" id="branch_id" name="branch_id">
                                <option value="">جميع الفروع</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" 
                                        <?= $branch_id == $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">نوع التقرير</label>
                            <select class="form-select" id="report_type" name="report_type">
                                <option value="daily_trends" <?= $report_type === 'daily_trends' ? 'selected' : '' ?>>اتجاهات القيمة اليومية</option>
                                <option value="weekly_trends" <?= $report_type === 'weekly_trends' ? 'selected' : '' ?>>اتجاهات القيمة الأسبوعية</option>
                                <option value="monthly_trends" <?= $report_type === 'monthly_trends' ? 'selected' : '' ?>>اتجاهات القيمة الشهرية</option>
                                <option value="branch_comparison" <?= $report_type === 'branch_comparison' ? 'selected' : '' ?>>مقارنة الفروع</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>
                                عرض التقرير
                            </button>
                            
                            <button type="button" class="btn btn-success ms-2" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>
                                تصدير Excel
                            </button>
                        </div>
                    </form>

                    <!-- الإحصائيات العامة -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0"><?= number_format($summary['total_logs'] ?? 0) ?></h4>
                                            <p class="mb-0">إجمالي السجلات</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clipboard-list fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0"><?= number_format($summary['total_value'] ?? 0, 2) ?> ريال</h4>
                                            <p class="mb-0">إجمالي القيمة</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-money-bill-wave fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0"><?= number_format($summary['working_days'] ?? 0) ?></h4>
                                            <p class="mb-0">أيام العمل</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-calendar fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0"><?= number_format(($summary['total_value'] ?? 0) / max($summary['working_days'] ?? 1, 1), 2) ?> ريال</h4>
                                            <p class="mb-0">متوسط قيمة يومية</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-chart-line fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الرسوم البيانية -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-area me-2"></i>
                                        <?php
                                        $chartTitles = [
                                            'daily_trends' => 'اتجاهات القيمة اليومية - مقارنة الأيام',
                                            'weekly_trends' => 'اتجاهات القيمة الأسبوعية - مقارنة الأسابيع',
                                            'monthly_trends' => 'اتجاهات القيمة الشهرية - مقارنة الشهور',
                                            'branch_comparison' => 'مقارنة القيمة بين الفروع'
                                        ];
                                        echo $chartTitles[$report_type] ?? 'الرسم البياني';
                                        ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="productivityChart" style="height: 400px; width: 100%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- جدول البيانات التفصيلية -->
                    <?php if (!empty($reportData)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        البيانات التفصيلية
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="reportTable">
                                            <thead class="table-dark">
                                                <tr>
                                                    <?php if ($report_type === 'daily_trends'): ?>
                                                        <th>التاريخ</th>
                                                        <th>القيمة اليومية (ريال)</th>
                                                        <th>الكمية المنجزة</th>
                                                        <th>عدد السجلات</th>
                                                    <?php elseif ($report_type === 'weekly_trends'): ?>
                                                        <th>الأسبوع</th>
                                                        <th>إجمالي القيمة (ريال)</th>
                                                        <th>عدد السجلات</th>
                                                        <th>أيام العمل</th>
                                                    <?php elseif ($report_type === 'monthly_trends'): ?>
                                                        <th>الشهر</th>
                                                        <th>إجمالي القيمة (ريال)</th>
                                                        <th>عدد السجلات</th>
                                                        <th>أيام العمل</th>
                                                    <?php elseif ($report_type === 'branch_comparison'): ?>
                                                        <th>الفرع</th>
                                                        <th>إجمالي القيمة (ريال)</th>
                                                        <th>عدد السجلات</th>
                                                        <th>أوامر العمل</th>
                                                        <th>أيام العمل</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData as $row): ?>
                                                <tr>
                                                    <?php if ($report_type === 'daily_trends'): ?>
                                                        <td><?= htmlspecialchars($row['log_date'] ?? '') ?></td>
                                                        <td><?= number_format($row['daily_value'] ?? 0, 2) ?></td>
                                                        <td><?= number_format($row['daily_quantity'] ?? 0, 2) ?></td>
                                                        <td><?= number_format($row['daily_logs'] ?? 0) ?></td>
                                                    <?php elseif ($report_type === 'weekly_trends'): ?>
                                                        <td><?= htmlspecialchars($row['week_label'] ?? '') ?></td>
                                                        <td><?= number_format($row['weekly_value'] ?? 0, 2) ?></td>
                                                        <td><?= number_format($row['weekly_logs'] ?? 0) ?></td>
                                                        <td><?= number_format($row['working_days'] ?? 0) ?></td>
                                                    <?php elseif ($report_type === 'monthly_trends'): ?>
                                                        <td><?= htmlspecialchars($row['month_label'] ?? '') ?></td>
                                                        <td><?= number_format($row['monthly_value'] ?? 0, 2) ?></td>
                                                        <td><?= number_format($row['monthly_logs'] ?? 0) ?></td>
                                                        <td><?= number_format($row['working_days'] ?? 0) ?></td>
                                                    <?php elseif ($report_type === 'branch_comparison'): ?>
                                                        <td><?= htmlspecialchars($row['branch_name'] ?? '') ?></td>
                                                        <td><?= number_format($row['branch_value'] ?? 0, 2) ?></td>
                                                        <td><?= number_format($row['branch_logs'] ?? 0) ?></td>
                                                        <td><?= number_format($row['branch_work_orders'] ?? 0) ?></td>
                                                        <td><?= number_format($row['working_days'] ?? 0) ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ECharts -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
// بيانات الرسوم البيانية
const chartLabels = <?= $chartLabelsJson ?>;
const chartData = <?= $chartDataJson ?>;

// الرسم البياني الرئيسي
let productivityChart;

// دالة إنشاء الرسم البياني باستخدام ECharts
function initProductivityChart() {
    const chartContainer = document.getElementById('productivityChart');

    // تدمير الرسم البياني السابق إن وجد
    if (productivityChart) {
        productivityChart.dispose();
    }

    // إنشاء مثيل ECharts
    productivityChart = echarts.init(chartContainer, null, {
        renderer: 'canvas',
        useDirtyRect: false
    });

    const reportType = '<?= $report_type ?>';
    const values = chartData.current_values || [];

    // تحديد نوع الرسم البياني والألوان حسب نوع التقرير
    let chartType = 'bar';
    let colorScheme = [];
    let title = '';

    switch (reportType) {
        case 'daily_trends':
            title = 'اتجاهات القيمة اليومية';
            colorScheme = ['#4f46e5', '#6366f1', '#8b5cf6', '#a855f7', '#c084fc'];
            break;
        case 'weekly_trends':
            title = 'اتجاهات القيمة الأسبوعية';
            colorScheme = ['#059669', '#10b981', '#34d399', '#6ee7b7', '#a7f3d0'];
            break;
        case 'monthly_trends':
            title = 'اتجاهات القيمة الشهرية';
            colorScheme = ['#dc2626', '#ef4444', '#f87171', '#fca5a5', '#fecaca'];
            break;
        case 'branch_comparison':
            title = 'مقارنة القيمة بين الفروع';
            colorScheme = ['#4f46e5', '#059669', '#dc2626', '#f59e0b', '#8b5cf6', '#06b6d4', '#84cc16', '#ef4444'];
            break;
    }

    // إعداد خيارات ECharts
    const option = {
        title: {
            text: title,
            left: 'center',
            top: 20,
            textStyle: {
                fontSize: 18,
                fontWeight: 'bold',
                color: '#333'
            }
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            borderColor: '#ddd',
            borderWidth: 1,
            textStyle: {
                color: '#fff',
                fontSize: 14
            },
            formatter: function(params) {
                const value = params[0].value;
                const formattedValue = new Intl.NumberFormat('ar-SA', {
                    style: 'currency',
                    currency: 'SAR'
                }).format(value);
                return `${params[0].name}<br/>القيمة: ${formattedValue}`;
            }
        },
        grid: {
            left: '10%',
            right: '10%',
            bottom: '15%',
            top: '20%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: chartLabels,
            axisLabel: {
                rotate: 45,
                fontSize: 12,
                color: '#666'
            },
            axisLine: {
                lineStyle: {
                    color: '#ddd'
                }
            }
        },
        yAxis: {
            type: 'value',
            name: 'القيمة (ريال)',
            nameLocation: 'middle',
            nameGap: 50,
            nameTextStyle: {
                fontSize: 14,
                fontWeight: 'bold',
                color: '#333'
            },
            axisLabel: {
                formatter: function(value) {
                    return new Intl.NumberFormat('ar-SA', {
                        style: 'currency',
                        currency: 'SAR',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(value);
                },
                fontSize: 12,
                color: '#666'
            },
            splitLine: {
                lineStyle: {
                    color: '#f0f0f0'
                }
            }
        },
        series: [{
            name: 'القيمة',
            type: chartType,
            data: values,
            itemStyle: {
                color: function(params) {
                    return colorScheme[params.dataIndex % colorScheme.length];
                },
                borderRadius: [4, 4, 0, 0]
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            },
            animationDelay: function(idx) {
                return idx * 100;
            }
        }],
        animationEasing: 'elasticOut',
        animationDelayUpdate: function(idx) {
            return idx * 50;
        }
    };

    // تطبيق الخيارات على الرسم البياني
    productivityChart.setOption(option);

    // جعل الرسم البياني متجاوب
    window.addEventListener('resize', function() {
        if (productivityChart) {
            productivityChart.resize();
        }
    });
}

// تصدير إلى Excel
function exportToExcel() {
    const table = document.getElementById('reportTable');
    if (!table) {
        alert('لا توجد بيانات للتصدير');
        return;
    }

    // إنشاء workbook جديد
    const wb = XLSX.utils.table_to_book(table);
    const filename = 'تقرير_الإنتاجية_' + new Date().toISOString().split('T')[0] + '.xlsx';
    XLSX.writeFile(wb, filename);
}

// تهيئة الرسوم البيانية عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    initProductivityChart();
});
</script>

<!-- SheetJS for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
