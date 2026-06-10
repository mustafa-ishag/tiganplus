<?php
/**
 * صفحة تحليل المواد المزالة لأمر عمل
 * Removed Materials Analysis by Work Order
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/RemovedMaterial.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

if (!hasPermission('removed_materials_view') && !hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية لعرض تحليل المواد المزالة', 'error');
    redirect('/dashboard.php');
}

$pageTitle = 'تحليل المواد المزالة';
$currentPage = 'removed-materials-analysis';

$removedMaterial = new RemovedMaterial();
$db = getDB();

// جلب أوامر العمل مع كود نوع أمر العمل
$workOrders = $db->query("
    SELECT wo.id, wo.work_order_number, wo.department, wo.location,
           b.name as branch_name,
           wot.type_code as work_order_type_code
    FROM work_orders wo
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    ORDER BY wo.work_order_number DESC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedWorkOrderId = $_GET['work_order_id'] ?? '';

// AJAX: جلب بيانات التحليل
if (isset($_GET['ajax']) && $_GET['ajax'] === 'analysis' && !empty($_GET['work_order_id'])) {
    header('Content-Type: application/json');

    $woId = (int) $_GET['work_order_id'];
    $materials = $removedMaterial->getMaterialsByWorkOrder($woId);

    // الإحصائيات
    $totalScrapIncoming = 0;
    $totalReturnIncoming = 0;
    $totalScrapOutgoing = 0;
    $totalReturnOutgoing = 0;
    foreach ($materials as &$m) {
        $m['remaining_qty'] = $m['incoming_qty'] - $m['outgoing_qty'];

        if ($m['material_category'] === 'scrap') {
            $totalScrapIncoming += $m['incoming_qty'];
            $totalScrapOutgoing += $m['outgoing_qty'];
        } else {
            $totalReturnIncoming += $m['incoming_qty'];
            $totalReturnOutgoing += $m['outgoing_qty'];
        }
    }

    // جلب عمليات أمر العمل
    $transactions = $removedMaterial->fetchAll(
        "SELECT rmt.*, u.full_name as created_by_name
         FROM removed_material_transactions rmt
         LEFT JOIN users u ON rmt.created_by = u.id
         WHERE rmt.work_order_id = ?
         ORDER BY rmt.transaction_date DESC",
        [$woId]
    );

    echo json_encode([
        'success' => true,
        'materials' => array_values($materials),
        'transactions' => $transactions,
        'stats' => [
            'total_materials' => count($materials),
            'total_scrap_incoming' => $totalScrapIncoming,
            'total_return_incoming' => $totalReturnIncoming,
            'total_scrap_outgoing' => $totalScrapOutgoing,
            'total_return_outgoing' => $totalReturnOutgoing
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

ob_start();
?>

<style>
    .analysis-card {
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .analysis-card h5 {
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .stat-mini {
        text-align: center;
        padding: 1rem;
        border-radius: 10px;
        background: #f8f9fa;
    }

    .stat-mini .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .stat-mini .stat-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .category-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .cat-scrap {
        background: #f8d7da;
        color: #721c24;
    }

    .cat-return {
        background: #d1e7dd;
        color: #0f5132;
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
    }

    .loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: #6c757d;
    }

    .loading-spinner .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    /* بحث أمر العمل */
    .search-container {
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
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
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 2px;
    }

    .selected-item {
        background-color: #e7f3ff;
        border-color: #0d6efd;
        color: #0d6efd;
        font-weight: 600;
    }
</style>

<!-- اختيار أمر العمل -->
<div class="analysis-card">
    <h5><i class="fas fa-search me-2"></i> اختر أمر العمل</h5>
    <div class="row align-items-end">
        <div class="col-md-6">
            <label class="form-label">أمر العمل</label>
            <div class="search-container">
                <input type="text" class="form-control" id="work_order_search"
                    placeholder="ابحث عن أمر العمل بالرقم أو النوع أو الفرع..." autocomplete="off">
                <select id="workOrderSelect" class="form-select d-none">
                    <option value="">-- اختر أمر العمل --</option>
                    <?php foreach ($workOrders as $wo): ?>
                        <option value="<?= $wo['id'] ?>" data-number="<?= htmlspecialchars($wo['work_order_number']) ?>"
                            data-type-code="<?= htmlspecialchars($wo['work_order_type_code'] ?? '') ?>"
                            data-branch="<?= htmlspecialchars($wo['branch_name'] ?? '') ?>"
                            <?= $selectedWorkOrderId == $wo['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wo['work_order_number']) ?>
                            (<?= htmlspecialchars($wo['work_order_type_code'] ?? '') ?>) -
                            <?= htmlspecialchars($wo['branch_name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="work_order_suggestions" class="custom-dropdown"></div>
            </div>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-primary w-100" onclick="loadAnalysis()">
                <i class="fas fa-chart-bar me-1"></i> عرض التحليل
            </button>
        </div>
    </div>
</div>

<!-- منطقة النتائج -->
<div id="analysisResults" style="display: none;">
    <!-- الإحصائيات -->
    <div class="row mb-4" id="analysisStats">
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-mini">
                <div class="stat-number" id="statTotalMaterials">0</div>
                <div class="stat-text">عدد المواد</div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-mini" style="background: #fff3cd;">
                <div class="stat-number" id="statScrapIn">0</div>
                <div class="stat-text">وارد تخريد</div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-mini" style="background: #d4edda;">
                <div class="stat-number" id="statReturnIn">0</div>
                <div class="stat-text">وارد إرجاع</div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-mini" style="background: #f8d7da;">
                <div class="stat-number" id="statScrapOut">0</div>
                <div class="stat-text">صادر تخريد</div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-mini" style="background: #cfe2ff;">
                <div class="stat-number" id="statReturnOut">0</div>
                <div class="stat-text">صادر إرجاع</div>
            </div>
        </div>
        </div>
    </div>

    <!-- جدول المواد -->
    <div class="analysis-card">
        <h5><i class="fas fa-boxes me-2"></i> تفاصيل المواد المزالة</h5>
        <div class="table-responsive">
            <table class="table table-hover" id="materialsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم المادة</th>
                        <th>الوصف</th>
                        <th>الوحدة</th>
                        <th>التصنيف</th>
                        <th>الكمية الواردة</th>
                        <th>الكمية الصادرة</th>
                        <th>الباقي</th>
                    </tr>
                </thead>
                <tbody id="materialsBody">
                </tbody>
            </table>
        </div>
    </div>

    <!-- جدول العمليات -->
    <div class="analysis-card">
        <h5><i class="fas fa-history me-2"></i> سجل العمليات</h5>
        <div class="table-responsive">
            <table class="table table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>رقم العملية</th>
                        <th>النوع</th>
                        <th>التصنيف</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th>بواسطة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="transactionsBody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Loading -->
<div id="loadingArea" style="display: none;">
    <div class="analysis-card">
        <div class="loading-spinner">
            <div class="spinner-border text-primary mb-3"></div>
            <p>جاري تحميل بيانات التحليل...</p>
        </div>
    </div>
</div>

<!-- رسالة فارغة -->
<div id="emptyArea" style="display: none;">
    <div class="analysis-card text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">لا توجد مواد مزالة لأمر العمل المحدد</h5>
    </div>
</div>

<script>
    // بيانات أوامر العمل
    const workOrdersData = <?= json_encode($workOrders, JSON_UNESCAPED_UNICODE) ?>;

    function escapeJs(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // ============ بحث أوامر العمل ============
    document.addEventListener('DOMContentLoaded', function () {
        initializeWorkOrderSearch();

        // تحميل تلقائي إذا أمر العمل محدد في URL
        const workOrderSelect = document.getElementById('workOrderSelect');
        if (workOrderSelect.value) {
            // تعبئة حقل البحث بالقيمة المحددة
            const selectedOption = workOrderSelect.options[workOrderSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const searchInput = document.getElementById('work_order_search');
                searchInput.value = selectedOption.textContent.trim();
                searchInput.classList.add('selected-item');
            }
            loadAnalysis();
        }
    });

    function initializeWorkOrderSearch() {
        const searchInput = document.getElementById('work_order_search');
        const suggestionsContainer = document.getElementById('work_order_suggestions');

        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            this.classList.remove('selected-item');

            if (searchTerm.length < 1) {
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.classList.remove('show');
                return;
            }

            const filteredOrders = workOrdersData.filter(order =>
                order.work_order_number.toLowerCase().includes(searchTerm) ||
                (order.branch_name && order.branch_name.toLowerCase().includes(searchTerm)) ||
                (order.work_order_type_code && order.work_order_type_code.toLowerCase().includes(searchTerm)) ||
                (order.location && order.location.toLowerCase().includes(searchTerm)) ||
                (order.department && order.department.toLowerCase().includes(searchTerm))
            ).slice(0, 10);

            if (filteredOrders.length > 0) {
                suggestionsContainer.innerHTML = filteredOrders.map(order => `
                    <div class="dropdown-item-custom" onclick="selectWorkOrder(${order.id}, '${escapeJs(order.work_order_number)}', '${escapeJs(order.branch_name || '')}', '${escapeJs(order.work_order_type_code || '')}')">
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

        searchInput.addEventListener('focus', function () {
            if (this.value.length >= 1 && !this.classList.contains('selected-item')) {
                this.dispatchEvent(new Event('input'));
            }
        });

        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.remove('show');
            }
        });
    }

    function selectWorkOrder(id, number, branch, typeCode) {
        const searchInput = document.getElementById('work_order_search');
        const selectElement = document.getElementById('workOrderSelect');
        const suggestionsContainer = document.getElementById('work_order_suggestions');

        searchInput.value = `${number} (${typeCode || 'غير محدد'}) - ${branch}`;
        searchInput.classList.add('selected-item');
        selectElement.value = id;
        suggestionsContainer.classList.remove('show');

        // تحميل التحليل تلقائياً عند اختيار أمر العمل
        loadAnalysis();
    }

    // ============ تحليل البيانات ============
    function loadAnalysis() {
        const workOrderId = document.getElementById('workOrderSelect').value;
        if (!workOrderId) {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: 'يرجى اختيار أمر عمل أولاً',
                confirmButtonText: 'حسناً'
            });
            return;
        }

        document.getElementById('analysisResults').style.display = 'none';
        document.getElementById('emptyArea').style.display = 'none';
        document.getElementById('loadingArea').style.display = 'block';

        fetch(`?ajax=analysis&work_order_id=${workOrderId}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingArea').style.display = 'none';

                if (!data.success || data.materials.length === 0) {
                    document.getElementById('emptyArea').style.display = 'block';
                    return;
                }

                // تحديث الإحصائيات
                document.getElementById('statTotalMaterials').textContent = data.stats.total_materials;
                document.getElementById('statScrapIn').textContent = parseFloat(data.stats.total_scrap_incoming).toFixed(1);
                document.getElementById('statReturnIn').textContent = parseFloat(data.stats.total_return_incoming).toFixed(1);
                document.getElementById('statScrapOut').textContent = parseFloat(data.stats.total_scrap_outgoing).toFixed(1);
                document.getElementById('statReturnOut').textContent = parseFloat(data.stats.total_return_outgoing).toFixed(1);

                // بناء جدول المواد
                let materialsHtml = '';
                data.materials.forEach((m, i) => {
                    const catClass = m.material_category === 'scrap' ? 'cat-scrap' : 'cat-return';
                    const catLabel = m.material_category === 'scrap' ? 'تخريد' : 'إرجاع';
                    const remaining = m.remaining_qty;
                    const remainingClass = remaining > 0 ? 'text-warning' : (remaining === 0 ? 'text-success' : 'text-danger');

                    materialsHtml += `
                    <tr>
                        <td>${i + 1}</td>
                        <td class="fw-bold">${m.item_number}</td>
                        <td>${mc.description}</td>
                        <td>${mc.unit}</td>
                        <td><span class="category-badge ${catClass}">${catLabel}</span></td>
                        <td>${parseFloat(m.incoming_qty).toFixed(3)}</td>
                        <td>${parseFloat(m.outgoing_qty).toFixed(3)}</td>
                        <td class="${remainingClass} fw-bold">${parseFloat(remaining).toFixed(3)}</td>
                    </tr>
                `;
                });
                document.getElementById('materialsBody').innerHTML = materialsHtml;

                // بناء جدول العمليات
                let transHtml = '';
                const statusLabels = {
                    'pending': '<span class="badge bg-warning text-dark">في الانتظار</span>',
                    'approved': '<span class="badge bg-success">معتمد</span>',
                    'rejected': '<span class="badge bg-danger">مرفوض</span>'
                };

                data.transactions.forEach(t => {
                    const typeBadge = t.transaction_type === 'incoming'
                        ? '<span class="badge bg-success">وارد</span>'
                        : '<span class="badge bg-primary">صادر</span>';
                    const catBadge = t.material_category === 'scrap'
                        ? '<span class="category-badge cat-scrap">تخريد</span>'
                        : '<span class="category-badge cat-return">إرجاع</span>';

                    transHtml += `
                    <tr>
                        <td class="fw-bold">${t.transaction_number}</td>
                        <td>${typeBadge}</td>
                        <td>${catBadge}</td>
                        <td>${t.transaction_date}</td>
                        <td>${statusLabels[t.status] || t.status}</td>
                        <td>${t.created_by_name || '-'}</td>
                        <td>
                            <a href="<?= path('inventory/removed-materials/view.php') ?>?id=${t.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                `;
                });
                document.getElementById('transactionsBody').innerHTML = transHtml;

                document.getElementById('analysisResults').style.display = 'block';
            })
            .catch(err => {
                document.getElementById('loadingArea').style.display = 'none';
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'فشل في تحميل البيانات',
                    confirmButtonText: 'حسناً'
                });
                console.error(err);
            });
    }
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../includes/layout.php';
?>