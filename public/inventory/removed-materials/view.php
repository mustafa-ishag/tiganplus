<?php
/**
 * صفحة عرض تفاصيل عملية مواد مزالة
 * View Removed Material Transaction
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
    setAlert('ليس لديك صلاحية لعرض المواد المزالة', 'error');
    redirect('/dashboard.php');
}

$currentPage = 'removed-materials';

$removedMaterial = new RemovedMaterial();
$transactionId = $_GET['id'] ?? null;

if (!$transactionId) {
    header('Location: ' . path('inventory/removed-materials/index.php'));
    exit;
}

$transaction = $removedMaterial->getTransactionWithDetails($transactionId);

if (!$transaction) {
    header('Location: ' . path('inventory/removed-materials/index.php'));
    exit;
}

$pageTitle = 'عملية مواد مزالة - ' . $transaction['transaction_number'];
$breadcrumbs = [
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'المواد المزالة', 'url' => 'inventory/removed-materials/index.php'],
    ['title' => $transaction['transaction_number'], 'url' => '']
];

// معالجة الاعتماد / الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' && (hasPermission('removed_materials_approve') || hasPermission('inventory_access'))) {
        $result = $removedMaterial->approveTransaction($transactionId, $_SESSION['user_id']);
        if ($result['success']) {
            header('Location: ' . path('inventory/removed-materials/view.php?id=' . $transactionId . '&msg=approved'));
            exit;
        }
    } elseif ($action === 'reject' && (hasPermission('removed_materials_approve') || hasPermission('inventory_access'))) {
        $reason = $_POST['rejection_reason'] ?? '';
        $result = $removedMaterial->rejectTransaction($transactionId, $_SESSION['user_id'], $reason);
        if ($result['success']) {
            header('Location: ' . path('inventory/removed-materials/view.php?id=' . $transactionId . '&msg=rejected'));
            exit;
        }
    }

    // إعادة تحميل
    $transaction = $removedMaterial->getTransactionWithDetails($transactionId);
}

$statusLabels = [
    'pending' => ['label' => 'في الانتظار', 'class' => 'warning', 'icon' => 'clock'],
    'approved' => ['label' => 'معتمدة', 'class' => 'success', 'icon' => 'check-circle'],
    'rejected' => ['label' => 'مرفوضة', 'class' => 'danger', 'icon' => 'times-circle'],
];

$statusInfo = $statusLabels[$transaction['status']] ?? $statusLabels['pending'];

ob_start();
?>

<!-- المكتبات المطلوبة للتصدير -->
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pizzip@3.1.7/dist/pizzip.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pizzip@3.1.7/dist/pizzip-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docxtemplater@3.50.0/build/docxtemplater.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- مولدات التقارير -->
<script src="<?= path('assets/js/export/MDRPdfGenerator.js') ?>"></script>
<script src="<?= path('assets/js/export/MRRPdfGenerator.js') ?>"></script>
<script src="<?= path('assets/js/export/FATRAPdfGenerator.js') ?>"></script>

<style>
    .detail-card {
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .detail-card h5 {
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f0f0f0;
    }
    .info-row { display: flex; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f8f9fa; }
    .info-row:last-child { border-bottom: none; }
    .info-label { font-weight: 600; color: #6c757d; min-width: 140px; font-size: 0.9rem; }
    .info-value { color: #1a1a2e; font-size: 0.95rem; }
    
    .status-lg {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem;
        border-radius: 50px; font-weight: 600; font-size: 1rem;
    }
    
    .type-badge {
        display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.75rem;
        border-radius: 6px; font-weight: 600; font-size: 0.9rem;
    }
    .type-incoming { background: #d4edda; color: #155724; }
    .type-outgoing { background: #cfe2ff; color: #084298; }
    .category-scrap { background: #f8d7da; color: #721c24; }
    .category-return { background: #d1e7dd; color: #0f5132; }
    .type-capital { background: #e0cffc; color: #431293; }
    .type-operational { background: #e2e3e5; color: #383d41; }
    
    .material-box {
        border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: #fafafa;
    }
    .capital-box {
        background: #f0f7ff; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 0.85rem;
    }
    .image-preview-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    .image-preview { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; cursor: pointer; }
    .image-preview img { width: 100%; height: 100%; object-fit: cover; }
</style>

<?php if (isset($_GET['msg'])): ?>
    <?php
    $messages = [
        'success' => ['type' => 'success', 'text' => 'تم حفظ العملية بنجاح'],
        'approved' => ['type' => 'success', 'text' => 'تم اعتماد العملية بنجاح'],
        'rejected' => ['type' => 'info', 'text' => 'تم رفض العملية'],
    ];
    $msgInfo = $messages[$_GET['msg']] ?? null;
    if ($msgInfo): ?>
        <div class="alert alert-<?= $msgInfo['type'] ?> alert-dismissible fade show">
            <i class="fas fa-check-circle me-1"></i> <?= $msgInfo['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- أزرار الإجراءات -->
<div class="d-flex flex-wrap gap-2 justify-content-between mb-4">
    <div>
        <a href="<?= path('inventory/removed-materials/index.php') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
        </a>

        <?php if ($transaction['status'] === 'pending'): ?>
            <?php if (hasPermission('removed_materials_create') || hasPermission('inventory_access')): ?>
                <a href="<?= path('inventory/removed-materials/create.php?edit=' . $transactionId) ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> تعديل
                </a>
            <?php endif; ?>

            <?php if (hasPermission('removed_materials_approve') || hasPermission('inventory_access')): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من اعتماد هذه العملية؟')">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> اعتماد</button>
                </form>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times me-1"></i> رفض
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div>
        <div class="dropdown d-inline-block">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i> تصدير (تخريد) MDR
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportExcel('MDR')"><i class="fas fa-file-excel text-success me-2"></i> تصدير Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportPdf('MDR')"><i class="fas fa-file-pdf text-danger me-2"></i> تصدير PDF</a></li>
            </ul>
        </div>
        
        <div class="dropdown d-inline-block">
            <button class="btn btn-info dropdown-toggle text-white" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i> تصدير (إرجاع) MRR
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportExcel('MRR')"><i class="fas fa-file-excel text-success me-2"></i> تصدير Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportPdf('MRR')"><i class="fas fa-file-pdf text-danger me-2"></i> تصدير PDF</a></li>
            </ul>
        </div>

        <div class="dropdown d-inline-block">
            <button class="btn btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i> تصدير نماذج أخرى
            </button>
            <ul class="dropdown-menu">
                <li><h6 class="dropdown-header">فاتورة المقاول (FATRA)</h6></li>
                <li><a class="dropdown-item" href="#" onclick="exportWord('FATRA', 'docx')"><i class="fas fa-file-word text-primary me-2"></i> تصدير Word</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportWord('FATRA', 'pdf')"><i class="fas fa-file-pdf text-danger me-2"></i> تصدير PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Inspection Report (IR)</h6></li>
                <li><a class="dropdown-item" href="#" onclick="exportWord('IR', 'docx')"><i class="fas fa-file-word text-primary me-2"></i> تصدير Word</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportWord('IR', 'pdf')"><i class="fas fa-file-pdf text-danger me-2"></i> تصدير PDF</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <!-- تفاصيل العملية -->
    <div class="col-md-8">
        <div class="detail-card">
            <h5><i class="fas fa-info-circle me-2"></i> معلومات العملية</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row"><span class="info-label">رقم العملية:</span><span class="info-value fw-bold"><?= htmlspecialchars($transaction['transaction_number']) ?></span></div>
                    <div class="info-row"><span class="info-label">نوع العملية:</span>
                        <span class="info-value">
                            <?= $transaction['transaction_type'] === 'incoming' ? '<span class="type-badge type-incoming"><i class="fas fa-arrow-down"></i> وارد</span>' : '<span class="type-badge type-outgoing"><i class="fas fa-arrow-up"></i> صادر</span>' ?>
                        </span>
                    </div>
                    <div class="info-row"><span class="info-label">التاريخ:</span><span class="info-value"><?= date('Y/m/d', strtotime($transaction['transaction_date'])) ?></span></div>
                </div>
                <div class="col-md-6">
                    <div class="info-row"><span class="info-label">أمر العمل:</span><span class="info-value fw-bold"><?= htmlspecialchars($transaction['work_order_number'] ?? '-') ?></span></div>
                    <?php if ($transaction['destination']): ?>
                        <div class="info-row"><span class="info-label">جهة التسليم:</span><span class="info-value"><?= htmlspecialchars($transaction['destination']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($transaction['notes']): ?>
                        <div class="info-row"><span class="info-label">الملاحظات:</span><span class="info-value"><?= htmlspecialchars($transaction['notes']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- المواد -->
        <div class="detail-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i> المواد المزالة</h5>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAllItems" onchange="toggleSelectAllItems(this)">
                    <label class="form-check-label fw-bold" for="selectAllItems">تحديد الكل للتصدير</label>
                </div>
            </div>
            
            <?php foreach ($transaction['details'] as $i => $detail): 
                $images = !empty($detail['images']) ? json_decode($detail['images'], true) : [];
            ?>
                <div class="material-box">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-start gap-2">
                            <div class="form-check mt-1">
                                <input class="form-check-input item-checkbox" type="checkbox" value="<?= $detail['id'] ?>" id="item_<?= $detail['id'] ?>" style="transform: scale(1.3); cursor: pointer;">
                            </div>
                            <h6 class="mb-0 fw-bold text-primary mt-1">#<?= $i + 1 ?> - <?= htmlspecialchars($detail['item_number']) ?> (<?= htmlspecialchars($detail['description']) ?>)</h6>
                        </div>
                        <span class="badge bg-secondary ms-2" style="white-space: nowrap;"><?= htmlspecialchars($detail['quantity']) ?> <?= htmlspecialchars($detail['unit']) ?></span>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-3"><strong>النوع:</strong> <span class="type-badge <?= $detail['item_type'] === 'رأس مالي' ? 'type-capital' : 'type-operational' ?>"><?= htmlspecialchars($detail['item_type']) ?></span></div>
                        <div class="col-md-3"><strong>الحالة:</strong> <span class="type-badge <?= $detail['status'] === 'تخريد' ? 'category-scrap' : 'category-return' ?>"><?= htmlspecialchars($detail['status']) ?></span></div>
                        <div class="col-md-6"><strong>سبب التخلص:</strong> <?= htmlspecialchars($detail['disposal_reason'] ?: '-') ?></div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-6"><strong>حالة المادة:</strong> <?= htmlspecialchars($detail['material_condition'] ?: '-') ?></div>
                        <div class="col-md-6"><strong>ملاحظات:</strong> <?= htmlspecialchars($detail['remarks'] ?: '-') ?></div>
                    </div>

                    <?php if ($detail['item_type'] === 'رأس مالي'): ?>
                        <div class="capital-box">
                            <h6 class="mb-2"><i class="fas fa-bolt text-warning"></i> بيانات رأس المال (للمعدات)</h6>
                            <div class="row g-2">
                                <div class="col-md-4"><strong>F.Loc:</strong> <?= htmlspecialchars($detail['functional_location'] ?: '-') ?></div>
                                <div class="col-md-4"><strong>Equipment:</strong> <?= htmlspecialchars($detail['equipment'] ?: '-') ?></div>
                                <div class="col-md-4"><strong>Capacity:</strong> <?= htmlspecialchars($detail['capacity_kva'] ?: '-') ?></div>
                                <div class="col-md-3"><strong>Manufacturer:</strong> <?= htmlspecialchars($detail['manufacturer'] ?: '-') ?></div>
                                <div class="col-md-3"><strong>Volt:</strong> <?= htmlspecialchars($detail['prim_sec_volt'] ?: '-') ?></div>
                                <div class="col-md-3"><strong>Year:</strong> <?= htmlspecialchars($detail['manufacture_year'] ?: '-') ?></div>
                                <div class="col-md-3"><strong>Serial:</strong> <?= htmlspecialchars($detail['serial_number'] ?: '-') ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($images) && is_array($images)): ?>
                        <div class="image-preview-container">
                            <?php foreach ($images as $idx => $imgUrl): ?>
                                <div class="image-preview" onclick="window.open('<?= $imgUrl ?>', '_blank')">
                                    <img src="<?= $imgUrl ?>" alt="صورة المادة">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- الحالة والمعلومات الإضافية -->
    <div class="col-md-4">
        <div class="detail-card text-center">
            <h5><i class="fas fa-flag me-2"></i> الحالة</h5>
            <div class="status-lg bg-<?= $statusInfo['class'] ?> bg-opacity-10 text-<?= $statusInfo['class'] ?> mx-auto">
                <i class="fas fa-<?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
            </div>
        </div>

        <div class="detail-card">
            <h5><i class="fas fa-user me-2"></i> معلومات المنشئ</h5>
            <div class="info-row"><span class="info-label">بواسطة:</span><span class="info-value"><?= htmlspecialchars($transaction['created_by_name'] ?? '-') ?></span></div>
            <div class="info-row"><span class="info-label">تاريخ الإنشاء:</span><span class="info-value"><?= date('Y/m/d H:i', strtotime($transaction['created_at'])) ?></span></div>
        </div>

        <?php if ($transaction['status'] === 'approved' && $transaction['approved_by_name']): ?>
            <div class="detail-card">
                <h5><i class="fas fa-check-circle me-2"></i> معلومات الاعتماد</h5>
                <div class="info-row"><span class="info-label">معتمد بواسطة:</span><span class="info-value"><?= htmlspecialchars($transaction['approved_by_name']) ?></span></div>
                <div class="info-row"><span class="info-label">تاريخ الاعتماد:</span><span class="info-value"><?= date('Y/m/d H:i', strtotime($transaction['approved_at'])) ?></span></div>
            </div>
        <?php endif; ?>
        
        <div class="detail-card">
            <h5><i class="fas fa-chart-pie me-2"></i> ملخص</h5>
            <div class="info-row"><span class="info-label">إجمالي المواد:</span><span class="info-value fw-bold"><?= count($transaction['details']) ?></span></div>
        </div>
    </div>
</div>

<!-- مودال الرفض -->
<?php if ($transaction['status'] === 'pending'): ?>
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-times-circle text-danger me-2"></i>رفض العملية</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">سبب الرفض</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="اكتب سبب الرفض..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i> تأكيد الرفض</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
// تمرير البيانات إلى JS لتسهيل التصدير
const transactionData = <?= json_encode($transaction, JSON_UNESCAPED_UNICODE) ?>;
const transactionDetails = transactionData.details || [];
const woNumber = transactionData.work_order_number || '';

function toggleSelectAllItems(checkbox) {
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    itemCheckboxes.forEach(cb => cb.checked = checkbox.checked);
}

function getSelectedItems(items) {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) return items; // إذا لم يتم تحديد شيء، نستخدم الكل كافتراضي
    
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);
    return items.filter(item => selectedIds.includes(item.id.toString()));
}

function exportExcel(type) {
    if(type === 'MDR') handleExportMDR();
    else if (type === 'MRR') handleExportMRR();
}

function exportPdf(type) {
    let targetItems = getSelectedItems(transactionDetails);
    
    if(type === 'MDR') {
        const scrapItems = targetItems.filter(i => i.status === 'تخريد');
        if(scrapItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد تخريد محددة للتصدير', 'warning');
        MDRPdfGenerator.generateAndDownload(scrapItems, woNumber);
    }
    else if (type === 'MRR') {
        const returnItems = targetItems.filter(i => i.status === 'إرجاع');
        if(returnItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد إرجاع محددة للتصدير', 'warning');
        MRRPdfGenerator.generateAndDownload(returnItems, woNumber);
    }
}

function exportWord(type, format) {
    if(type === 'FATRA') handleExportFATRA(format);
    else if(type === 'IR') handleExportIR(format);
}

async function handleExportMDR() {
    const targetItems = getSelectedItems(transactionDetails);
    const selectedItems = targetItems.filter(i => i.status === 'تخريد');
    if (selectedItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد تخريد محددة للتصدير', 'warning');

    try {
        Swal.fire({title: 'جاري التصدير...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
        
        const response = await fetch('<?= path("assets/templates/MDR.xlsx") ?>');
        if (!response.ok) throw new Error('لم يتم العثور على ملف القالب MDR.xlsx');
        const arrayBuffer = await response.arrayBuffer();

        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(arrayBuffer);
        const worksheet = workbook.getWorksheet(1);

        const startRow = 18;
        selectedItems.forEach((item, i) => {
            const rowNum = startRow + i;
            const row = worksheet.getRow(rowNum);
            row.getCell(2).value = itemc.description || '';
            row.getCell(9).value = itemc.unit || '';
            row.getCell(10).value = item.quantity || 1;
            row.getCell(11).value = item.disposal_reason || '';
            row.getCell(13).value = item.item_number || '';
            row.getCell(14).value = item.material_condition || '';
            row.getCell(16).value = item.remarks || '';
            row.commit();
        });

        for (let r = 32; r <= 35; r++) {
            const row = worksheet.getRow(r);
            row.getCell(7).value = woNumber;
            row.commit();
        }

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = `MDR_${woNumber}_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        Swal.close();
        Swal.fire('نجاح', 'تم التصدير بنجاح', 'success');
    } catch (error) {
        Swal.close();
        Swal.fire('خطأ', error.message, 'error');
    }
}

async function handleExportMRR() {
    const targetItems = getSelectedItems(transactionDetails);
    const selectedItems = targetItems.filter(i => i.status === 'إرجاع');
    if (selectedItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد إرجاع محددة للتصدير', 'warning');

    try {
        Swal.fire({title: 'جاري التصدير...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
        
        const response = await fetch('<?= path("assets/templates/MRR.xlsx") ?>');
        if (!response.ok) throw new Error('لم يتم العثور على ملف القالب MRR.xlsx');
        const arrayBuffer = await response.arrayBuffer();

        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(arrayBuffer);
        const worksheet = workbook.getWorksheet(1);

        const woRow = worksheet.getRow(23);
        woRow.getCell(12).value = woNumber;
        woRow.commit();

        const startRow = 14;
        selectedItems.forEach((item, i) => {
            const rowNum = startRow + i;
            const row = worksheet.getRow(rowNum);
            row.getCell(11).value = item.item_number || '';
            row.getCell(10).value = itemc.unit || '';
            row.getCell(9).value = item.quantity || 1;
            row.getCell(12).value = itemc.description || '';
            if (item.serial_number) row.getCell(16).value = item.serial_number;
            row.commit();
        });

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = `MRR_${woNumber}_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        Swal.close();
        Swal.fire('نجاح', 'تم التصدير بنجاح', 'success');
    } catch (error) {
        Swal.close();
        Swal.fire('خطأ', error.message, 'error');
    }
}

function loadFile(url, callback) {
    PizZipUtils.getBinaryContent(url, callback);
}

function exportWordTemplate(templatePath, dataObj, fileName, format = 'docx') {
    Swal.fire({title: 'جاري التصدير...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    loadFile(templatePath, async function(error, content) {
        if (error) {
            Swal.close();
            return Swal.fire('خطأ', 'خطأ في تحميل القالب', 'error');
        }
        
        try {
            var zip = new PizZip(content);
            var doc = new window.docxtemplater(zip, { 
                paragraphLoop: true, 
                linebreaks: true,
                nullGetter: function(part) {
                    if (!part.module) return "";
                    if (part.module === "rawxml") return "";
                    return "";
                }
            });
            doc.render(dataObj);
            var out = doc.getZip().generate({
                type: "blob",
                mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            });
            
            if (format === 'pdf') {
                const formData = new FormData();
                formData.append('file', out, fileName + '.docx');

                const serverResponse = await fetch('https://pdf-converter-69cg.onrender.com/convert', {
                    method: 'POST',
                    body: formData
                });

                if (!serverResponse.ok) {
                    throw new Error('فشل التحويل إلى PDF. يرجى المحاولة لاحقاً.');
                }

                const pdfBlob = await serverResponse.blob();
                const url = window.URL.createObjectURL(pdfBlob);
                const link = document.createElement('a');
                link.href = url;
                link.download = fileName + '.pdf';
                link.click();
                URL.revokeObjectURL(url);
            } else {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(out);
                link.download = fileName + '.docx';
                link.click();
                URL.revokeObjectURL(link.href);
            }
            
            Swal.close();
            Swal.fire('نجاح', 'تم التصدير بنجاح', 'success');
        } catch (e) {
            Swal.close();
            console.error(e);
            Swal.fire('خطأ', e.message || 'حدث خطأ أثناء ملء القالب', 'error');
        }
    });
}

function handleExportFATRA(format) {
    const targetItems = getSelectedItems(transactionDetails);
    const capitalItems = targetItems.filter(i => i.item_type === 'رأس مالي');
    if (capitalItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد رأس مالية محددة للتصدير كفاتورة مقاول', 'warning');

    if (format === 'pdf') {
        // تصدير PDF عبر PHP API المحلي (mPDF) - ملف لكل مادة
        handleExportViaPHP(capitalItems, 'api/export_fatra.php', 'FATRA');
    } else {
        // تصدير Word عبر PizZip XML replacement
        handleExportWordXML(capitalItems, '<?= path("assets/templates/FATRA.docx") ?>', 'FATRA');
    }
}

function handleExportIR(format) {
    const targetItems = getSelectedItems(transactionDetails);
    const capitalItems = targetItems.filter(i => i.item_type === 'رأس مالي');
    if (capitalItems.length === 0) return Swal.fire('تنبيه', 'لا توجد مواد رأس مالية محددة', 'warning');

    if (format === 'pdf') {
        // تصدير PDF عبر PHP API المحلي (mPDF) - ملف لكل مادة
        handleExportViaPHP(capitalItems, 'api/export_ir.php', 'IR');
    } else {
        // تصدير Word عبر PizZip XML replacement
        handleExportWordXML(capitalItems, '<?= path("assets/templates/IR.docx") ?>', 'IR');
    }
}

// تصدير PDF عبر PHP API المحلي (mPDF)
async function handleExportViaPHP(items, apiUrl, prefix) {
    Swal.fire({title: 'جاري التصدير...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    try {
        const woLocation = transactionData.location || '';
        
        for (const item of items) {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    item: item,
                    woLocation: woLocation,
                    woNumber: woNumber
                })
            });

            const result = await response.json();

            if (result.error) {
                throw new Error(result.error);
            }

            if (result.url) {
                const pdfResponse = await fetch(result.url);
                const pdfBlob = await pdfResponse.blob();
                const isReturn = item.status === 'إرجاع';
                const filePrefix = isReturn ? `${prefix}-RETURN` : prefix;
                const today = new Date().toISOString().slice(0, 10);
                const downloadName = `${filePrefix}_${woNumber || 'export'}_${item.item_number || 'export'}_${today}.pdf`;
                const blobUrl = URL.createObjectURL(pdfBlob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = downloadName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
            }
        }

        Swal.close();
        Swal.fire('نجاح', `تم تصدير ${items.length} نموذج ${prefix} PDF بنجاح`, 'success');
    } catch (error) {
        Swal.close();
        console.error(`${prefix} PDF Export Error:`, error);
        Swal.fire('خطأ', 'خطأ في تصدير PDF: ' + error.message, 'error');
    }
}

// تصدير Word عبر PizZip XML replacement (نفس طريقة النظام الأصلي)
function handleExportWordXML(items, templateUrl, prefix) {
    Swal.fire({title: 'جاري التصدير...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    const woLocation = transactionData.location || '';
    
    loadFile(templateUrl, async function(error, content) {
        if (error) {
            Swal.close();
            return Swal.fire('خطأ', `خطأ في تحميل القالب ${prefix}`, 'error');
        }
        
        try {
            for (const item of items) {
                const isReturn = item.status === 'إرجاع';
                
                const replacements = {
                    '*st*': item.functional_location || '',
                    '*qu*': item.equipment || '',
                    '*ma*': item.manufacturer || '',
                    '*pr*': item.prim_sec_volt || '',
                    '*ca*': item.capacity_kva || '',
                    '*ye*': item.manufacture_year ? String(item.manufacture_year) : '',
                    '*se*': item.serial_number || '',
                    '*sec*': item.item_number || '',
                    '*ar*': woLocation,
                    '*wo*': woNumber || ''
                };
                
                const zip = new PizZip(content);
                const xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];
                
                xmlFiles.forEach(filePath => {
                    const file = zip.file(filePath);
                    if (!file) return;
                    let text = file.asText();
                    for (const [tag, value] of Object.entries(replacements)) {
                        text = text.split(tag).join(value);
                        const tagChars = tag.split('');
                        const regexParts = tagChars.map(ch => ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
                        const regexStr = regexParts.join('(?:<[^>]*>)*');
                        const regex = new RegExp(regexStr, 'g');
                        text = text.replace(regex, (match) => {
                            const xmlTags = match.match(/<[^>]*>/g) || [];
                            return value + xmlTags.join('');
                        });
                    }
                    zip.file(filePath, text);
                });
                
                const out = zip.generate({
                    type: 'blob',
                    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                });
                
                const filePrefix = isReturn ? `${prefix}-RETURN` : prefix;
                const fileName = `${filePrefix}_${item.item_number || 'export'}_${new Date().toISOString().slice(0, 10)}.docx`;
                const url = URL.createObjectURL(out);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            
            Swal.close();
            Swal.fire('نجاح', `تم تصدير ${items.length} نموذج ${prefix} بنجاح`, 'success');
        } catch (e) {
            Swal.close();
            console.error(e);
            Swal.fire('خطأ', e.message || `حدث خطأ أثناء ملء قالب ${prefix}`, 'error');
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../includes/layout.php';
?>