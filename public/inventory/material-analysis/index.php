<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة تحليل المواد لأمر عمل
 * Material Analysis by Work Order
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية للوصول إلى هذه الصفحة', 'error');
    redirect('../index.php');
}

$workOrderModel = new WorkOrder();

// جلب أوامر العمل المتاحة
$workOrders = $workOrderModel->getWorkOrdersForMaterialRequest($_SESSION['user_branch_id'] ?? null);

// ---- AJAX: جلب بيانات المواد لأمر عمل محدد ----
if (isset($_GET['ajax']) && $_GET['ajax'] === 'materials' && !empty($_GET['work_order_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);

    try {
        $workOrderId = (int) $_GET['work_order_id'];
        $db = getDB();

        // 1) المواد من طلبات الصرف
        $sqlRequests = "
            SELECT
                mrd.material_id,
                m.item_number,
                mc.description,
                mc.unit,
                SUM(mrd.requested_quantity) as request_qty
            FROM material_request_details mrd
            JOIN material_requests mr ON mrd.request_id = mr.id
            JOIN materials m ON mrd.material_id = m.id
            LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE mr.work_order_id = ?
            GROUP BY mrd.material_id, m.item_number, mc.description, mc.unit
        ";
        $stmtReq = $db->prepare($sqlRequests);
        $stmtReq->execute([$workOrderId]);
        $requestMaterials = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

        // 2) المواد من المعاملات (الصادرة والمرتجعات)
        // صافي الكمية = المصروف (outgoing) - المرتجع (return)
        $sqlTransactions = "
            SELECT
                td.material_id,
                m.item_number,
                mc.description,
                mc.unit,
                SUM(CASE WHEN it.transaction_type = 'outgoing' THEN td.quantity ELSE 0 END) as outgoing_qty,
                SUM(CASE WHEN it.transaction_type = 'return' THEN td.quantity ELSE 0 END) as return_qty,
                SUM(CASE WHEN it.transaction_type = 'outgoing' THEN td.quantity ELSE 0 END) -
                SUM(CASE WHEN it.transaction_type = 'return' THEN td.quantity ELSE 0 END) as transaction_qty
            FROM transaction_details td
            JOIN inventory_transactions it ON td.transaction_id = it.id
            JOIN materials m ON td.material_id = m.id
            LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE it.work_order_id = ?
              AND it.transaction_type IN ('outgoing', 'return')
              AND it.status = 'approved'
            GROUP BY td.material_id, m.item_number, mc.description, mc.unit
        ";
        $stmtTx = $db->prepare($sqlTransactions);
        $stmtTx->execute([$workOrderId]);
        $transactionMaterials = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

        // 3) كميات المقايسة من شهادات الإنجاز
        $sqlEstimates = "
            SELECT
                ccm.material_id,
                SUM(ccm.estimated_quantity) as estimated_qty,
                SUM(ccm.dispensed_quantity)  as dispensed_qty,
                SUM(ccm.returned_quantity)   as returned_qty
            FROM completion_certificate_materials ccm
            JOIN completion_certificates cc ON ccm.certificate_id = cc.id
            WHERE cc.work_order_id = ?
            GROUP BY ccm.material_id
        ";
        $stmtEst = $db->prepare($sqlEstimates);
        $stmtEst->execute([$workOrderId]);
        $estimateMaterials = [];
        foreach ($stmtEst->fetchAll(PDO::FETCH_ASSOC) as $em) {
            $estimateMaterials[$em['material_id']] = [
                'estimated_qty' => (float) $em['estimated_qty'],
                'dispensed_qty' => (float) $em['dispensed_qty'],
                'returned_qty' => (float) $em['returned_qty'],
            ];
        }

        // 4) دمج البيانات
        $merged = [];

        foreach ($requestMaterials as $rm) {
            $mid = $rm['material_id'];
            $merged[$mid] = [
                'material_id' => $mid,
                'item_number' => $rm['item_number'],
                'description' => $rm['description'],
                'unit' => $rm['unit'],
                'request_qty' => (float) $rm['request_qty'],
                'transaction_qty' => 0,
                'estimated_qty' => $estimateMaterials[$mid]['estimated_qty'] ?? 0,
                'dispensed_qty' => $estimateMaterials[$mid]['dispensed_qty'] ?? 0,
                'returned_qty' => $estimateMaterials[$mid]['returned_qty'] ?? 0,
            ];
        }

        foreach ($transactionMaterials as $tm) {
            $mid = $tm['material_id'];
            if (isset($merged[$mid])) {
                $merged[$mid]['transaction_qty'] = (float) $tm['transaction_qty'];
            } else {
                $merged[$mid] = [
                    'material_id' => $mid,
                    'item_number' => $tm['item_number'],
                    'description' => $tm['description'],
                    'unit' => $tm['unit'],
                    'request_qty' => 0,
                    'transaction_qty' => (float) $tm['transaction_qty'],
                    'estimated_qty' => $estimateMaterials[$mid]['estimated_qty'] ?? 0,
                    'dispensed_qty' => $estimateMaterials[$mid]['dispensed_qty'] ?? 0,
                    'returned_qty' => $estimateMaterials[$mid]['returned_qty'] ?? 0,
                ];
            }
        }

        // إضافة مواد المقايسة التي لم تظهر في المصدرين الآخرين
        foreach ($estimateMaterials as $mid => $estData) {
            if (!isset($merged[$mid])) {
                $matInfo = $db->prepare("SELECT m.item_number, mc.description, mc.unit FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.id = ?");
                $matInfo->execute([$mid]);
                $mat = $matInfo->fetch(PDO::FETCH_ASSOC);
                if ($mat) {
                    $merged[$mid] = [
                        'material_id' => $mid,
                        'item_number' => $mat['item_number'],
                        'description' => $mat['description'],
                        'unit' => $mat['unit'],
                        'request_qty' => 0,
                        'transaction_qty' => 0,
                        'estimated_qty' => $estData['estimated_qty'],
                        'dispensed_qty' => $estData['dispensed_qty'],
                        'returned_qty' => $estData['returned_qty'],
                    ];
                }
            }
        }

        // ترتيب حسب رقم المادة
        usort($merged, function ($a, $b) {
            return strcmp($a['item_number'], $b['item_number']);
        });

        // حساب الإجماليات
        $result = [];
        $totalDispensedQty = 0;
        $totalEstimatedQty = 0;

        foreach ($merged as $item) {
            $item['total_qty'] = $item['transaction_qty'];
            $result[] = $item;
            $totalDispensedQty += $item['transaction_qty'];
            $totalEstimatedQty += $item['estimated_qty'];
        }

        // جلب معلومات أمر العمل
        $woInfo = $db->prepare("
            SELECT wo.*, wot.description as type_description, wot.type_code
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE wo.id = ?
        ");
        $woInfo->execute([$workOrderId]);
        $workOrderInfo = $woInfo->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'materials' => array_values($result),
            'summary' => [
                'total_materials' => count($result),
                'total_combined_qty' => $totalDispensedQty,
                'total_estimated_qty' => $totalEstimatedQty,
            ],
            'work_order' => $workOrderInfo,
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        error_log('[material-analysis] AJAX error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$pageTitle = 'تحليل المواد';
$currentPage = 'material-analysis';

// بدء تخزين المحتوى
ob_start();
?>

<style>
    /* ===== Page Header ===== */
    .page-header {
        background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
    }

    .page-header h2 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
    }

    .page-header p {
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
        opacity: 0.85;
    }

    /* ===== Cards ===== */
    .analysis-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.25rem;
        overflow: hidden;
    }

    .analysis-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.85rem 1.25rem;
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .analysis-card .card-header i {
        color: #176cb4;
    }

    .analysis-card .card-body {
        padding: 1.25rem;
    }

    /* ===== Work Order Dropdown ===== */
    .wo-select-container {
        position: relative;
    }

    .wo-select-container .wo-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        max-height: 260px;
        overflow-y: auto;
        z-index: 1050;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
        margin-top: 2px;
    }

    .wo-select-container .wo-dropdown.show {
        display: block;
    }

    .wo-dropdown-item {
        padding: 0.65rem 0.9rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background .15s;
    }

    .wo-dropdown-item:last-child {
        border-bottom: none;
    }

    .wo-dropdown-item:hover {
        background: #f0f7ff;
    }

    .wo-dropdown-item .wo-number {
        font-weight: 600;
        font-size: .9rem;
        color: #176cb4;
    }

    .wo-dropdown-item .wo-type {
        font-size: .78rem;
        color: #888;
        margin-top: 1px;
    }

    .wo-dropdown-item .wo-value {
        font-size: .78rem;
        color: #666;
    }

    .wo-selected {
        border-color: #176cb4 !important;
        background: #f0f7ff !important;
    }

    /* ===== WO Info Bar ===== */
    .wo-info-bar {
        display: none;
        background: linear-gradient(135deg, #f0f7ff 0%, #e8f0fe 100%);
        border: 1px solid #c5d8f0;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }

    .wo-info-bar.show {
        display: block;
    }

    .wo-info-bar .wo-info-title {
        font-weight: 700;
        color: #176cb4;
        font-size: 1.05rem;
        margin-bottom: 0.5rem;
    }

    .wo-info-bar .wo-info-detail {
        display: inline-block;
        background: #fff;
        border-radius: 6px;
        padding: 0.3rem 0.75rem;
        margin: 0.2rem;
        font-size: 0.85rem;
        border: 1px solid #e2e8f0;
    }

    .wo-info-bar .wo-info-detail i {
        color: #176cb4;
        margin-left: 4px;
    }

    /* ===== Stats Cards ===== */
    .stat-card {
        border-radius: 10px;
        padding: 1rem 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: -20px;
        left: -20px;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
    }

    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .stat-card .stat-label {
        font-size: 0.82rem;
        opacity: 0.9;
    }

    .stat-bg-1 {
        background: linear-gradient(135deg, #176cb4, #4fa5e6);
    }

    .stat-bg-2 {
        background: linear-gradient(135deg, #28a745, #1e7e34);
    }

    .stat-bg-3 {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }

    .stat-bg-4 {
        background: linear-gradient(135deg, #fd7e14, #e8590c);
    }

    /* ===== Results Table ===== */
    .results-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.9rem;
    }

    .results-table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        padding: 0.75rem 0.85rem;
        font-weight: 700;
        color: #333;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .results-table tbody td {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }

    .results-table tbody tr:hover {
        background: #f8fafc;
    }

    .results-table .text-total {
        font-weight: 700;
        color: #176cb4;
    }

    .results-table tfoot td {
        background: #f0f7ff;
        font-weight: 700;
        padding: 0.75rem 0.85rem;
        border-top: 2px solid #176cb4;
    }

    /* ===== Empty State ===== */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #adb5bd;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h5 {
        color: #6c757d;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        font-size: 0.9rem;
    }

    /* ===== Loading ===== */
    .loading-overlay {
        display: none;
        text-align: center;
        padding: 3rem;
    }

    .loading-overlay.show {
        display: block;
    }

    .loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid #e2e8f0;
        border-top-color: #176cb4;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ===== Source Badge ===== */
    .source-badge {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .source-badge.requests {
        background: #e3f2fd;
        color: #1565c0;
    }

    .source-badge.transactions {
        background: #fce4ec;
        color: #c62828;
    }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
        .page-header {
            padding: 1rem 1.25rem;
        }

        .analysis-card .card-body {
            padding: 1rem;
        }

        .results-table {
            font-size: 0.82rem;
        }

        .results-table thead th,
        .results-table tbody td {
            padding: 0.5rem 0.6rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div><i class="fas fa-chart-bar fa-lg"></i></div>
            <div>
                <h2>تحليل المواد لأمر عمل</h2>
                <p>عرض جميع المواد المنصرفة على أمر عمل من طلبات الصرف والمعاملات الصادرة</p>
            </div>
        </div>
        <a href="../index.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-arrow-right me-1"></i> العودة للمخزون
        </a>
    </div>

    <!-- اختيار أمر العمل -->
    <div class="analysis-card" style="overflow: visible;">
        <div class="card-header"><i class="fas fa-search"></i> اختيار أمر العمل</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-clipboard-list text-muted me-1"></i> أمر العمل
                    </label>
                    <div class="wo-select-container">
                        <input type="text" class="form-control" id="wo_search_input"
                            placeholder="اكتب رقم أمر العمل أو النوع للبحث..." autocomplete="off">
                        <input type="hidden" id="work_order_id" value="">
                        <div class="wo-dropdown" id="woDropdown"></div>
                    </div>
                    <div class="form-text">اختر أمر العمل لعرض المواد المنصرفة عليه</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" class="btn btn-primary" id="btnAnalyze" disabled onclick="loadAnalysis()">
                        <i class="fas fa-chart-bar me-1"></i> عرض التحليل
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- شريط معلومات أمر العمل -->
    <div class="wo-info-bar" id="woInfoBar"></div>

    <!-- إحصائيات سريعة -->
    <div class="row mb-3" id="statsRow" style="display: none;">
        <div class="col-md-4 col-6 mb-2">
            <div class="stat-card stat-bg-1">
                <div class="stat-number" id="statMaterials">0</div>
                <div class="stat-label">عدد المواد</div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="stat-card stat-bg-2">
                <div class="stat-number" id="statCombinedQty">0</div>
                <div class="stat-label">إجمالي الكمية المنصرفة</div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="stat-card stat-bg-4">
                <div class="stat-number" id="statEstimatedQty">0</div>
                <div class="stat-label">إجمالي كمية المقايسة</div>
            </div>
        </div>
    </div>

    <!-- جدول النتائج -->
    <div class="analysis-card" id="resultsCard" style="display: none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-table"></i> تفاصيل المواد المنصرفة</div>
            <button class="btn btn-outline-success btn-sm" onclick="exportTable()">
                <i class="fas fa-file-excel me-1"></i> تصدير
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>رقم المادة</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>المصروف</th>
                            <th>المقايسة</th>
                            <th>الصرف</th>
                            <th>الإرجاع</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody"></tbody>
                    <tfoot id="resultsFoot"></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- حالة التحميل -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p class="text-muted">جاري تحميل بيانات المواد...</p>
    </div>

    <!-- حالة فارغة -->
    <div class="empty-state" id="emptyState" style="display: none;">
        <i class="fas fa-inbox d-block"></i>
        <h5>لا توجد مواد منصرفة</h5>
        <p>لم يتم العثور على مواد منصرفة على أمر العمل المحدد</p>
    </div>

    <!-- الحالة الأولية -->
    <div class="empty-state" id="initialState">
        <i class="fas fa-hand-pointer d-block"></i>
        <h5>اختر أمر العمل</h5>
        <p>اختر أمر عمل من القائمة أعلاه لعرض تحليل المواد المنصرفة عليه</p>
    </div>
</div>

<script>
    var workOrdersData = <?= json_encode($workOrders) ?>;
    var selectedWorkOrderId = null;

    // ===== البحث في أوامر العمل =====
    document.getElementById('wo_search_input').addEventListener('input', function () {
        var searchTerm = this.value.toLowerCase();
        var dropdown = document.getElementById('woDropdown');
        var hiddenInput = document.getElementById('work_order_id');
        var btn = document.getElementById('btnAnalyze');

        if (searchTerm.length < 1) {
            dropdown.innerHTML = '';
            dropdown.classList.remove('show');
            this.classList.remove('wo-selected');
            hiddenInput.value = '';
            btn.disabled = true;
            selectedWorkOrderId = null;
            return;
        }

        var filtered = workOrdersData.filter(function (wo) {
            return wo.work_order_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                (wo.type_code && wo.type_code.toLowerCase().indexOf(searchTerm) !== -1) ||
                (wo.work_order_type_description && wo.work_order_type_description.toLowerCase().indexOf(searchTerm) !== -1);
        }).slice(0, 10);

        if (filtered.length > 0) {
            dropdown.innerHTML = filtered.map(function (wo) {
                var escapedNum = wo.work_order_number.replace(/'/g, "\\'");
                return '<div class="wo-dropdown-item" onclick="selectWorkOrder(' + wo.id + ', \'' + escapedNum + '\')">' +
                    '<div class="wo-number">' + wo.work_order_number + '</div>' +
                    '<div class="wo-type">' + (wo.type_code || '') + ' - ' + (wo.work_order_type_description || '') + '</div>' +
                    '<div class="wo-value">القيمة: ' + parseFloat(wo.estimated_value).toLocaleString() + ' ريال</div>' +
                    '</div>';
            }).join('');
            dropdown.classList.add('show');
        } else {
            dropdown.innerHTML = '<div class="wo-dropdown-item text-muted">لا توجد نتائج</div>';
            dropdown.classList.add('show');
        }
    });

    // ===== اختيار أمر عمل =====
    function selectWorkOrder(woId, woNumber) {
        var input = document.getElementById('wo_search_input');
        var hiddenInput = document.getElementById('work_order_id');
        var dropdown = document.getElementById('woDropdown');
        var btn = document.getElementById('btnAnalyze');

        input.value = woNumber;
        input.classList.add('wo-selected');
        hiddenInput.value = woId;
        selectedWorkOrderId = woId;
        dropdown.classList.remove('show');
        btn.disabled = false;

        // تحميل التحليل تلقائياً
        loadAnalysis();
    }

    // ===== إغلاق القائمة عند النقر خارجها =====
    document.addEventListener('click', function (e) {
        var woContainer = document.querySelector('.wo-select-container');
        if (woContainer && !woContainer.contains(e.target)) {
            document.getElementById('woDropdown').classList.remove('show');
        }
    });

    // ===== تحميل التحليل =====
    function loadAnalysis() {
        var woId = document.getElementById('work_order_id').value;
        if (!woId) return;

        // إخفاء كل الأقسام
        document.getElementById('initialState').style.display = 'none';
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('resultsCard').style.display = 'none';
        document.getElementById('statsRow').style.display = 'none';
        document.getElementById('woInfoBar').classList.remove('show');
        document.getElementById('loadingOverlay').classList.add('show');

        fetch('index.php?ajax=materials&work_order_id=' + woId)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                document.getElementById('loadingOverlay').classList.remove('show');

                if (!data.success || data.materials.length === 0) {
                    document.getElementById('emptyState').style.display = 'block';
                    return;
                }

                // عرض معلومات أمر العمل
                showWorkOrderInfo(data.work_order);

                // عرض الإحصائيات
                showStats(data.summary);

                // عرض الجدول
                showTable(data.materials, data.summary);
            })
            .catch(function (err) {
                document.getElementById('loadingOverlay').classList.remove('show');
                alert('حدث خطأ أثناء تحميل البيانات: ' + err.message);
            });
    }

    // ===== عرض معلومات أمر العمل =====
    function showWorkOrderInfo(wo) {
        if (!wo) return;
        var bar = document.getElementById('woInfoBar');
        bar.innerHTML =
            '<div class="wo-info-title"><i class="fas fa-clipboard-list me-1"></i> ' + wo.work_order_number + '</div>' +
            '<div>' +
            (wo.type_description ? '<span class="wo-info-detail"><i class="fas fa-tag"></i> ' + wo.type_description + '</span>' : '') +
            (wo.type_code ? '<span class="wo-info-detail"><i class="fas fa-code"></i> ' + wo.type_code + '</span>' : '') +
            (wo.estimated_value ? '<span class="wo-info-detail"><i class="fas fa-coins"></i> ' + parseFloat(wo.estimated_value).toLocaleString() + ' ريال</span>' : '') +
            (wo.assignment_date ? '<span class="wo-info-detail"><i class="fas fa-calendar"></i> ' + wo.assignment_date + '</span>' : '') +
            (wo.status ? '<span class="wo-info-detail"><i class="fas fa-info-circle"></i> ' + wo.status + '</span>' : '') +
            '</div>';
        bar.classList.add('show');
    }

    // ===== عرض الإحصائيات =====
    function showStats(summary) {
        document.getElementById('statMaterials').textContent = summary.total_materials;
        document.getElementById('statCombinedQty').textContent = parseFloat(summary.total_combined_qty).toLocaleString();
        document.getElementById('statEstimatedQty').textContent = parseFloat(summary.total_estimated_qty).toLocaleString();
        document.getElementById('statsRow').style.display = 'flex';
    }

    // ===== عرض الجدول =====
    function showTable(materials, summary) {
        var tbody = document.getElementById('resultsBody');
        var tfoot = document.getElementById('resultsFoot');

        tbody.innerHTML = materials.map(function (m, i) {
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td><strong>' + (m.item_number || '-') + '</strong></td>' +
                '<td>' + (m.description || '-') + '</td>' +
                '<td class="text-center">' + (m.unit || '-') + '</td>' +
                '<td class="text-center text-total">' + parseFloat(m.total_qty).toLocaleString() + '</td>' +
                '<td class="text-center">' + (m.estimated_qty > 0 ? parseFloat(m.estimated_qty).toLocaleString() : '<span class="text-muted">0</span>') + '</td>' +
                '<td class="text-center">' + (m.dispensed_qty > 0 ? parseFloat(m.dispensed_qty).toLocaleString() : '<span class="text-muted">0</span>') + '</td>' +
                '<td class="text-center">' + (m.returned_qty > 0 ? parseFloat(m.returned_qty).toLocaleString() : '<span class="text-muted">0</span>') + '</td>' +
                '</tr>';
        }).join('');

        tfoot.innerHTML = '<tr>' +
            '<td colspan="4" class="text-center">الإجمالي</td>' +
            '<td class="text-center">' + parseFloat(summary.total_combined_qty).toLocaleString() + '</td>' +
            '<td class="text-center">' + parseFloat(summary.total_estimated_qty).toLocaleString() + '</td>' +
            '<td colspan="2"></td>' +
            '</tr>';

        document.getElementById('resultsCard').style.display = 'block';
    }

    // ===== تصدير إلى Excel (باستخدام القالب) =====
    function exportTable() {
        var woId = document.getElementById('work_order_id').value;
        if (!woId) {
            alert('يرجى اختيار أمر العمل أولاً');
            return;
        }
        window.location.href = 'export-excel.php?work_order_id=' + woId;
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>