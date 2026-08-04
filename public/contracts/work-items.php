<?php
/**
 * صفحة إدارة بنود العقد
 * Contract Work Items Management Page
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;

// التأكد من وجود العقد
$stmt = $db->prepare("SELECT * FROM contracts WHERE id = ?");
$stmt->execute([$contract_id]);
$contract = $stmt->fetch();

if (!$contract) {
    die("العقد غير موجود.");
}

$pageTitle = 'بنود العقد - ' . $contract['contract_number'];
$currentPage = 'contracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة العقود', 'url' => 'contracts/index.php'],
    ['title' => 'بنود العقد', 'url' => 'contracts/work-items.php?contract_id=' . $contract_id]
];

// جلب البنود الخاصة بهذا العقد
$itemsStmt = $db->prepare("SELECT * FROM contract_work_items WHERE contract_id = ? ORDER BY id DESC");
$itemsStmt->execute([$contract_id]);
$work_items = $itemsStmt->fetchAll();

// جلب الوحدات الحالية
$unitsStmt = $db->prepare("SELECT DISTINCT unit FROM contract_work_items WHERE contract_id = ? AND unit IS NOT NULL AND unit != '' ORDER BY unit");
$unitsStmt->execute([$contract_id]);
$available_units = $unitsStmt->fetchAll(PDO::FETCH_COLUMN);
$common_units = ['متر طولي', 'متر مربع', 'متر مكعب', 'حبة', 'قطعة', 'مقطوعية', 'يوم', 'شهر', 'KM', 'EA', 'M', 'M2', 'ASM', 'ST', 'M3', '%', 'KIT', 'H', 'BTU', 'MON'];
$all_units = array_unique(array_merge($common_units, $available_units));
sort($all_units);

ob_start();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-list-alt text-primary me-2"></i>
                بنود العقد: <?= htmlspecialchars($contract['contract_number']) ?>
            </h1>
            <p class="text-muted mb-0">إدارة البنود والأسعار الخاصة بهذا العقد</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-right me-1"></i> رجوع للعقود
            </a>
            <button type="button" class="btn btn-primary" onclick="openAddItemModal()">
                <i class="fas fa-plus me-1"></i> إضافة بند جديد
            </button>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i> قائمة بنود العقد (<?= count($work_items) ?> بند)
            </h6>
        </div>
        <div class="card-body">
            <!-- Search Box -->
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="workItemsTableSearch" placeholder="ابحث في البنود...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="workItemsTable" width="100%">
                    <thead>
                        <tr>
                            <th>رقم البند</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>السعر</th>
                            <th>الفئة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($work_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_number']) ?></td>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['unit']) ?></span></td>
                            <td><span class="text-success fw-bold"><?= number_format($item['price'], 2) ?></span></td>
                            <td><?= htmlspecialchars($item['category'] ?? '-') ?></td>
                            <td>
                                <?php if ($item['is_active']): ?>
                                    <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-warning" onclick='openEditItemModal(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= $item['id'] ?>)">
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

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">إضافة بند جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" name="contract_id" value="<?= $contract_id ?>">
                    <input type="hidden" name="item_id" id="item_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم البند <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_number" id="item_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="price" id="price" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الوحدة <span class="text-danger">*</span></label>
                            <select class="form-select" name="unit" id="unit" required>
                                <option value="">اختر الوحدة...</option>
                                <?php foreach ($all_units as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الفئة</label>
                            <input type="text" class="form-control" name="category" id="category">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وصف البند <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" id="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">نشط</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="saveItemBtn">حفظ البند</button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
/* إخفاء مربع البحث الافتراضي لـ DataTables */
.dataTables_filter {
    display: none !important;
}
</style>
<script>
$(document).ready(function() {
    var table = $('#workItemsTable').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
        order: [[0, 'asc']]
    });

    // ربط مربع البحث المخصص بـ DataTable
    $('#workItemsTableSearch').on('keyup', function() {
        table.search(this.value).draw();
    });

    $('#itemForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#saveItemBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...');
        
        $.ajax({
            url: 'save-work-item-ajax.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح',
                        text: 'تم حفظ البند بنجاح',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('خطأ', response.message || 'حدث خطأ غير متوقع', 'error');
                    btn.prop('disabled', false).html('حفظ البند');
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ في الاتصال بالخادم', 'error');
                btn.prop('disabled', false).html('حفظ البند');
            }
        });
    });
});

function openAddItemModal() {
    $('#itemModalTitle').text('إضافة بند جديد');
    $('#itemForm')[0].reset();
    $('#item_id').val('');
    $('#is_active').prop('checked', true);
    var modal = new bootstrap.Modal(document.getElementById('itemModal'));
    modal.show();
}

function openEditItemModal(item) {
    $('#itemModalTitle').text('تعديل البند');
    $('#item_id').val(item.id);
    $('#item_number').val(item.item_number);
    $('#description').val(item.description);
    
    // إضافة الوحدة إذا لم تكن موجودة في القائمة المنسدلة
    if ($('#unit option[value="' + item.unit + '"]').length === 0) {
        $('#unit').append(new Option(item.unit, item.unit, true, true));
    } else {
        $('#unit').val(item.unit);
    }
    
    $('#price').val(item.price);
    $('#category').val(item.category);
    $('#is_active').prop('checked', item.is_active == 1);
    var modal = new bootstrap.Modal(document.getElementById('itemModal'));
    modal.show();
}

function deleteItem(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: "لن تتمكن من التراجع عن هذا الإجراء!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'save-work-item-ajax.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحذف',
                            text: 'تم حذف البند بنجاح',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('خطأ', response.message || 'حدث خطأ أثناء الحذف', 'error');
                    }
                }
            });
        }
    });
}
</script>
<?php
$content = ob_get_clean();

require_once __DIR__ . '/../includes/layout.php';
?>
