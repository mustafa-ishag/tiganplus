<?php
/**
 * صفحة إدارة العقود
 * Contracts Management Page
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إدارة العقود';
$currentPage = 'contracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة العقود', 'url' => 'contracts/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// جلب العقود مع إحصائيات أوامر العمل
$contractsQuery = "
    SELECT c.*,
           u.full_name as created_by_name,
           COUNT(DISTINCT wo.id) as work_orders_count,
           CASE 
               WHEN CURDATE() BETWEEN c.start_date AND c.end_date THEN 'current'
               WHEN CURDATE() < c.start_date THEN 'future'
               ELSE 'expired'
           END as period_status
    FROM contracts c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN work_orders wo ON wo.contract_id = c.id
    GROUP BY c.id
    ORDER BY c.start_date DESC
";
$contracts = $db->query($contractsQuery)->fetchAll();

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-file-contract text-primary me-2"></i>
                إدارة العقود
            </h1>
            <p class="text-muted mb-0">إدارة عقود الشركة وربطها بأوامر العمل تلقائياً</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contractModal" onclick="openAddModal()">
                <i class="fas fa-plus me-1"></i>
                إضافة عقد جديد
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php
        $totalContracts = count($contracts);
        $currentContracts = count(array_filter($contracts, fn($c) => $c['period_status'] === 'current'));
        $expiredContracts = count(array_filter($contracts, fn($c) => $c['period_status'] === 'expired'));
        $totalWorkOrders = array_sum(array_column($contracts, 'work_orders_count'));
        ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي العقود</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalContracts ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-contract fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">العقود الحالية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $currentContracts ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">العقود المنتهية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $expiredContracts ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-history fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">أوامر العمل المرتبطة</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalWorkOrders ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-link fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                قائمة العقود
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="contractsTable" width="100%">
                    <thead>
                        <tr>
                            <th>رقم العقد</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>الحالة</th>
                            <th>أوامر العمل</th>
                            <th>الوصف</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($contract['contract_number']) ?></span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($contract['start_date'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($contract['end_date'])) ?></td>
                            <td>
                                <?php if ($contract['period_status'] === 'current'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>نشط حالياً</span>
                                <?php elseif ($contract['period_status'] === 'future'): ?>
                                    <span class="badge bg-info"><i class="fas fa-clock me-1"></i>مستقبلي</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-history me-1"></i>منتهي</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= $contract['work_orders_count'] ?> أمر عمل</span>
                            </td>
                            <td><?= htmlspecialchars($contract['description'] ?? '-') ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($contract)) ?>)" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteContract(<?= $contract['id'] ?>, '<?= htmlspecialchars($contract['contract_number']) ?>')" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة/تعديل عقد -->
<div class="modal fade" id="contractModal" tabindex="-1" aria-labelledby="contractModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contractModalLabel">
                    <i class="fas fa-file-contract me-2"></i>
                    <span id="modalTitleText">إضافة عقد جديد</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="contractForm">
                <div class="modal-body">
                    <input type="hidden" id="contract_id" name="contract_id" value="">
                    
                    <div class="mb-3">
                        <label for="contract_number" class="form-label">
                            رقم العقد <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="contract_number" name="contract_number" 
                               pattern="[0-9]{10}" maxlength="10" required
                               placeholder="أدخل رقم العقد (10 أرقام)"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        <div class="form-text">يجب أن يكون مكون من 10 أرقام فقط</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">
                                تاريخ البداية <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">
                                تاريخ النهاية <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">الوصف (اختياري)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="وصف أو ملاحظات حول العقد"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save me-1"></i>
                        حفظ
                    </button>
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
    $('#contractsTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        pageLength: 25,
        order: [[1, 'desc']]
    });
});

function openAddModal() {
    document.getElementById('modalTitleText').textContent = 'إضافة عقد جديد';
    document.getElementById('contract_id').value = '';
    document.getElementById('contractForm').reset();
}

function openEditModal(contract) {
    document.getElementById('modalTitleText').textContent = 'تعديل العقد';
    document.getElementById('contract_id').value = contract.id;
    document.getElementById('contract_number').value = contract.contract_number;
    document.getElementById('start_date').value = contract.start_date;
    document.getElementById('end_date').value = contract.end_date;
    document.getElementById('description').value = contract.description || '';
    
    var modal = new bootstrap.Modal(document.getElementById('contractModal'));
    modal.show();
}

function deleteContract(id, contractNumber) {
    Swal.fire({
        title: 'تأكيد الحذف',
        html: `هل أنت متأكد من حذف العقد <strong>${contractNumber}</strong>؟<br><small class="text-danger">سيتم إلغاء ربط أوامر العمل المرتبطة بهذا العقد.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، حذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete-ajax.php',
                type: 'POST',
                data: { contract_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('تم الحذف!', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('خطأ!', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف', 'error');
                }
            });
        }
    });
}

$('#contractForm').on('submit', function(e) {
    e.preventDefault();
    
    var contractNumber = $('#contract_number').val();
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    
    // التحقق من رقم العقد
    if (contractNumber.length !== 10 || !/^\d{10}$/.test(contractNumber)) {
        Swal.fire('خطأ!', 'رقم العقد يجب أن يكون 10 أرقام فقط', 'error');
        return;
    }
    
    // التحقق من التواريخ
    if (endDate <= startDate) {
        Swal.fire('خطأ!', 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية', 'error');
        return;
    }
    
    var saveBtn = $('#saveBtn');
    var originalText = saveBtn.html();
    saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...');
    
    $.ajax({
        url: 'save-ajax.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('تم بنجاح!', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('خطأ!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('خطأ!', 'حدث خطأ أثناء الحفظ', 'error');
        },
        complete: function() {
            saveBtn.prop('disabled', false).html(originalText);
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
