<?php
/**
 * صفحة كتالوج المواد
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

if (!hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية للوصول لكتالوج المواد', 'error');
    redirect('../../dashboard.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// معالجة طلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM material_catalog WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'تم حذف المادة من الكتالوج بنجاح']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء الحذف']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
        }
        exit();
    }

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $item_number = trim($_POST['item_number'] ?? '');
        $group_number = trim($_POST['group_number'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['unit'] ?? 'قطعة');
        $unit_price = floatval(str_replace(',', '', $_POST['unit_price'] ?? 0));

        if (empty($item_number) || empty($description)) {
            echo json_encode(['success' => false, 'message' => 'رقم البند والوصف مطلوبان']);
            exit();
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE material_catalog SET item_number=?, group_number=?, description=?, unit=?, unit_price=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$item_number, $group_number, $description, $unit, $unit_price, $id]);
                echo json_encode(['success' => true, 'message' => 'تم تحديث المادة بنجاح']);
            } else {
                $check = $db->prepare("SELECT id FROM material_catalog WHERE item_number = ?");
                $check->execute([$item_number]);
                if ($check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'رقم البند موجود مسبقاً في الكتالوج']);
                    exit();
                }
                $stmt = $db->prepare("INSERT INTO material_catalog (item_number, group_number, description, unit, unit_price, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_number, $group_number, $description, $unit, $unit_price, $user_id]);
                echo json_encode(['success' => true, 'message' => 'تمت إضافة المادة بنجاح']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'get') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM material_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $item
            ? json_encode(['success' => true, 'data' => $item])
            : json_encode(['success' => false, 'message' => 'العنصر غير موجود']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
    exit();
}

// إنشاء الجدول إذا لم يكن موجوداً
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS material_catalog (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_number VARCHAR(50) NOT NULL UNIQUE,
            group_number VARCHAR(20) DEFAULT NULL,
            description TEXT NOT NULL,
            unit VARCHAR(50) DEFAULT 'قطعة',
            unit_price DECIMAL(12,4) DEFAULT 0.0000,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_number (item_number),
            INDEX idx_group_number (group_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
}

// جلب البيانات
$catalogItems = $db->query("SELECT * FROM material_catalog ORDER BY item_number ASC")->fetchAll(PDO::FETCH_ASSOC);
$groups = $db->query("SELECT DISTINCT group_number FROM material_catalog WHERE group_number IS NOT NULL AND group_number != '' ORDER BY group_number")->fetchAll(PDO::FETCH_COLUMN);
$stats = $db->query("SELECT COUNT(*) as total, COUNT(DISTINCT group_number) as total_groups, AVG(unit_price) as avg_price, MAX(unit_price) as max_price FROM material_catalog")->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'كتالوج المواد';
$currentPage = 'material-catalog';

ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-book-open text-primary me-2"></i>
                كتالوج المواد
            </h2>
            <p class="text-muted mb-0">قاعدة بيانات مرجعية شاملة لجميع المواد وأسعارها</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <?php if (hasPermission('inventory_materials_edit') || hasPermission('inventory_materials_create')): ?>
                    <a href="import-export.php" class="btn btn-outline-success">
                        <i class="fas fa-exchange-alt me-1"></i> استيراد/تصدير
                    </a>
                    <button type="button" class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus me-1"></i> إضافة مادة
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-layer-group text-primary fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($stats['total'] ?? 0) ?></div>
                        <small class="text-muted">إجمالي المواد في الكتالوج</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fas fa-tags text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($stats['total_groups'] ?? 0) ?></div>
                        <small class="text-muted">عدد المجموعات</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 p-3 me-3">
                        <i class="fas fa-dollar-sign text-info fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($stats['avg_price'] ?? 0, 2) ?></div>
                        <small class="text-muted">متوسط السعر (ريال)</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="fas fa-arrow-up text-warning fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($stats['max_price'] ?? 0, 2) ?></div>
                        <small class="text-muted">أعلى سعر (ريال)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- فلتر المجموعة (يعمل مع DataTable) -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label small mb-0 text-muted">فلترة حسب المجموعة:</label>
                </div>
                <div class="col-md-3">
                    <select id="groupFilter" class="form-select form-select-sm">
                        <option value="">جميع المجموعات</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm" id="clearGroupFilter">
                        <i class="fas fa-times me-1"></i> مسح الفلتر
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول الكتالوج -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i>
                قائمة المواد (<?= number_format(count($catalogItems)) ?> مادة)
            </h5>
            <div>
                <?php if (hasPermission('inventory_materials_edit') || hasPermission('inventory_materials_create')): ?>
                    <button class="btn btn-sm btn-outline-success" onclick="exportCatalog()">
                        <i class="fas fa-file-excel me-1"></i> تصدير Excel
                    </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary ms-1" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> طباعة
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($catalogItems)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">الكتالوج فارغ</h5>
                    <p class="text-muted">لا توجد مواد في الكتالوج بعد. ابدأ بإضافة مواد أو استيرادها.</p>
                    <?php if (hasPermission('inventory_materials_edit') || hasPermission('inventory_materials_create')): ?>
                        <button type="button" class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus me-1"></i> إضافة أول مادة
                        </button>
                        <a href="import-export.php" class="btn btn-outline-success ms-2">
                            <i class="fas fa-upload me-1"></i> استيراد من ملف
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="catalogTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم البند</th>
                                <th>رقم المجموعة</th>
                                <th>الوصف</th>
                                <th>الوحدة</th>
                                <th>سعر الوحدة</th>
                                <th class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catalogItems as $item): ?>
                                <tr id="row-<?= $item['id'] ?>">
                                    <td><strong><?= htmlspecialchars($item['item_number']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($item['group_number'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($item['group_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width:350px"
                                            title="<?= htmlspecialchars($item['description']) ?>">
                                            <?= htmlspecialchars($item['description']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['unit'] ?: '-') ?></td>
                                    <td><strong><?= number_format($item['unit_price'], 2) ?> ريال</strong></td>
                                    <td class="text-center">
                                        <?php if (hasPermission('inventory_materials_edit') || hasPermission('inventory_materials_create')): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="editItem(<?= $item['id'] ?>)" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteItem(<?= $item['id'] ?>, '<?= addslashes($item['item_number']) ?>')"
                                                    title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

<!-- Modal إضافة/تعديل -->
<div class="modal fade" id="catalogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-plus-circle me-2"></i> إضافة مادة جديدة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="formError" class="alert alert-danger d-none"></div>
                <input type="hidden" id="itemId" value="0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">رقم البند <span class="text-danger">*</span></label>
                        <input type="text" id="itemNumber" class="form-control" placeholder="مثال: E-001-001"
                            maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم المجموعة</label>
                        <input type="text" id="groupNumber" class="form-control" placeholder="مثال: GRP-001"
                            maxlength="20">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">الوصف <span class="text-danger">*</span></label>
                        <textarea id="itemDescription" class="form-control" rows="3"
                            placeholder="وصف تفصيلي للمادة..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">وحدة القياس</label>
                        <input type="text" id="itemUnit" class="form-control" placeholder="مثال: قطعة، متر..."
                            value="قطعة">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سعر الوحدة (ريال)</label>
                        <input type="number" id="itemPrice" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="saveBtn" onclick="saveItem()">
                    <i class="fas fa-save me-1"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
// ↑ layout.php يحمّل: jQuery → Bootstrap → DataTables → SweetAlert2
// ↓ هذا الكود يُنفَّذ بعد تحميل جميع المكتبات
?>

<script>
    /* ===== دوال الـ Modal ===== */
    let catalogModal = null;
    function getCatalogModal() {
        if (!catalogModal) {
            catalogModal = new bootstrap.Modal(document.getElementById('catalogModal'));
        }
        return catalogModal;
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>إضافة مادة جديدة';
        document.getElementById('itemId').value = '0';
        document.getElementById('itemNumber').value = '';
        document.getElementById('groupNumber').value = '';
        document.getElementById('itemDescription').value = '';
        document.getElementById('itemUnit').value = 'قطعة';
        document.getElementById('itemPrice').value = '';
        document.getElementById('formError').classList.add('d-none');
        document.getElementById('itemNumber').removeAttribute('readonly');
        getCatalogModal().show();
    }

    function editItem(id) {
        fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'ajax_action=get&id=' + id })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = data.data;
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>تعديل: ' + item.item_number;
                    document.getElementById('itemId').value = item.id;
                    document.getElementById('itemNumber').value = item.item_number;
                    document.getElementById('itemNumber').setAttribute('readonly', 'readonly');
                    document.getElementById('groupNumber').value = item.group_number || '';
                    document.getElementById('itemDescription').value = item.description;
                    document.getElementById('itemUnit').value = item.unit || 'قطعة';
                    document.getElementById('itemPrice').value = item.unit_price || '';
                    document.getElementById('formError').classList.add('d-none');
                    getCatalogModal().show();
                } else {
                    alert('حدث خطأ: ' + data.message);
                }
            });
    }

    function saveItem() {
        const id = document.getElementById('itemId').value;
        const itemNumber = document.getElementById('itemNumber').value.trim();
        const groupNumber = document.getElementById('groupNumber').value.trim();
        const description = document.getElementById('itemDescription').value.trim();
        const unit = document.getElementById('itemUnit').value.trim();
        const price = document.getElementById('itemPrice').value;

        if (!itemNumber || !description) { showFormError('رقم البند والوصف مطلوبان'); return; }

        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...';

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ ajax_action: 'save', id, item_number: itemNumber, group_number: groupNumber, description, unit, unit_price: price || '0' }).toString()
        })
            .then(r => r.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> حفظ';
                if (data.success) {
                    getCatalogModal().hide();
                    Swal.fire({ icon: 'success', title: 'تم بنجاح', text: data.message, timer: 1500 }).then(() => location.reload());
                } else {
                    showFormError(data.message);
                }
            })
            .catch(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> حفظ';
                showFormError('حدث خطأ في الاتصال بالخادم');
            });
    }

    function deleteItem(id, itemNumber) {
        Swal.fire({
            title: 'تأكيد الحذف',
            html: `هل تريد حذف المادة <strong>${itemNumber}</strong> من الكتالوج؟`,
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف', cancelButtonText: 'إلغاء'
        }).then(result => {
            if (result.isConfirmed) {
                fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'ajax_action=delete&id=' + id })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('row-' + id)?.remove();
                            Swal.fire({ icon: 'success', title: 'تم الحذف', text: data.message, timer: 1500 });
                        } else {
                            Swal.fire({ icon: 'error', title: 'خطأ', text: data.message });
                        }
                    });
            }
        });
    }

    function exportCatalog() { window.location.href = 'import-export.php?action=export_direct'; }
    function showFormError(msg) { const el = document.getElementById('formError'); el.textContent = msg; el.classList.remove('d-none'); }

    /* ===== تهيئة DataTable ===== */
    let catalogDT;
    $(document).ready(function () {
        if ($('#catalogTable').length) {
            catalogDT = $('#catalogTable').DataTable({
                language: {
                    sProcessing: 'جارٍ التحميل...', sLengthMenu: 'أظهر _MENU_ مدخلات',
                    sZeroRecords: 'لم يعثر على أية سجلات',
                    sInfo: 'إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل',
                    sInfoEmpty: 'يعرض 0 إلى 0 من أصل 0 سجل',
                    sInfoFiltered: '(منتقاة من مجموع _MAX_ مُدخل)', sSearch: 'ابحث:',
                    oPaginate: { sFirst: 'الأول', sPrevious: 'السابق', sNext: 'التالي', sLast: 'الأخير' }
                },
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
                order: [[0, 'asc']],
                stateSave: false,
                columnDefs: [
                    { orderable: false, targets: -1 },
                    { className: 'text-center', targets: [1, 3, 4, 5] }
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                    '<"row"<"col-sm-12"tr>>' +
                    '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });

            // فلتر المجموعة الخارجي
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                const groupVal = $('#groupFilter').val();
                if (!groupVal) return true;
                return $(catalogDT.cell(dataIndex, 1).node()).text().trim() === groupVal;
            });
            $('#groupFilter').on('change', function () { catalogDT.draw(); });
            $('#clearGroupFilter').on('click', function () { $('#groupFilter').val(''); catalogDT.draw(); });
        }
    });
</script>