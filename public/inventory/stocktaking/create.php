<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';
require_once __DIR__ . '/../../../models/Material.php';

if (!isset($_SESSION['user_id'])) redirect('../../auth/login.php');
if (!hasPermission('inventory_stocktaking_create')) {
    setAlert('ليس لديك صلاحية', 'error'); redirect('index.php');
}

$model = new StocktakingSession();
$materialModel = new Material();
$materials = $materialModel->getActiveMaterials();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['session_type'] ?? 'full';
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $selectedMaterials = $_POST['material_ids'] ?? [];

    if (empty($title)) $errors[] = 'عنوان الجلسة مطلوب';
    if ($type === 'partial' && empty($selectedMaterials)) $errors[] = 'يجب اختيار مواد للجرد الجزئي';

    if (empty($errors)) {
        $result = $model->createSession([
            'title' => $title,
            'session_type' => $type,
            'start_date' => $startDate,
            'notes' => $notes,
            'created_by' => $_SESSION['user_id']
        ]);

        if ($result['success']) {
            $matIds = $type === 'full' ? null : $selectedMaterials;
            $startResult = $model->startSession($result['session_id'], $matIds);
            if ($startResult['success']) {
                setAlert("تم إنشاء وبدء جلسة الجرد بنجاح ({$startResult['items_count']} مادة)", 'success');
                redirect('count.php?id=' . $result['session_id']);
            } else {
                setAlert('تم إنشاء الجلسة لكن فشل البدء: ' . $startResult['message'], 'warning');
                redirect('view.php?id=' . $result['session_id']);
            }
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'إنشاء جلسة جرد';
$currentPage = 'stocktaking';
ob_start();
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>إنشاء جلسة جرد جديدة</h2>
            <p class="text-muted mb-0">إعداد جلسة جرد كامل أو جزئي للمخزون</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right me-1"></i>العودة</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?=$e?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" id="createForm">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-cog me-2"></i>إعدادات الجلسة</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">عنوان الجلسة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" required placeholder="مثال: جرد شهر مايو 2026">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ البدء</label>
                                <input type="date" class="form-control" name="start_date" value="<?=date('Y-m-d')?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع الجرد <span class="text-danger">*</span></label>
                                <select class="form-select" name="session_type" id="sessionType">
                                    <option value="full">جرد كامل (جميع المواد النشطة)</option>
                                    <option value="partial">جرد جزئي (مواد محددة)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">ملاحظات</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="ملاحظات اختيارية..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- اختيار المواد للجرد الجزئي -->
                <div class="card mb-4" id="materialSelection" style="display:none">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>اختيار المواد</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">تحديد الكل</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">إلغاء الكل</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3"><input type="text" class="form-control" id="materialSearch" placeholder="ابحث عن مادة..."></div>
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top"><tr><th width="40"></th><th>رقم البند</th><th>الوصف</th><th>الوحدة</th><th>المخزون</th></tr></thead>
                                <tbody id="materialsBody">
                                <?php foreach($materials as $m): ?>
                                <tr class="material-row">
                                    <td><input type="checkbox" name="material_ids[]" value="<?=$m['id']?>" class="form-check-input material-cb"></td>
                                    <td class="item-num"><strong><?=htmlspecialchars($m['item_number'])?></strong></td>
                                    <td class="item-desc" dir="ltr" style="text-align:left"><?=htmlspecialchars(mb_substr($m['description'],0,60))?></td>
                                    <td><?=htmlspecialchars($m['unit'])?></td>
                                    <td><?=formatNumber($m['current_stock'],3)?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top"><span id="selectedCount">0</span> مادة محددة</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-play me-2"></i>إنشاء وبدء الجرد</button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg">إلغاء</a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>معلومات</h6></div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3"><i class="fas fa-boxes me-1"></i><strong><?=count($materials)?></strong> مادة نشطة في المخزون</div>
                        <h6>الجرد الكامل:</h6><p class="small text-muted">يشمل جميع المواد النشطة (<?=count($materials)?> مادة)</p>
                        <h6>الجرد الجزئي:</h6><p class="small text-muted">اختر مواد محددة للجرد</p>
                        <h6>سير العمل:</h6>
                        <ol class="small text-muted"><li>إنشاء الجلسة</li><li>عد المواد (يدوي أو باركود)</li><li>مراجعة الفروقات</li><li>اعتماد وتسوية</li></ol>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
document.getElementById('sessionType').addEventListener('change', function(){
    document.getElementById('materialSelection').style.display = this.value === 'partial' ? '' : 'none';
});
document.getElementById('materialSearch')?.addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('.material-row').forEach(r => {
        const t = (r.querySelector('.item-num')?.textContent||'') + ' ' + (r.querySelector('.item-desc')?.textContent||'');
        r.style.display = t.toLowerCase().includes(q) ? '' : 'none';
    });
});
function selectAll(){ document.querySelectorAll('.material-cb').forEach(c=>{if(c.closest('tr').style.display!=='none')c.checked=true}); updateCount(); }
function deselectAll(){ document.querySelectorAll('.material-cb').forEach(c=>c.checked=false); updateCount(); }
function updateCount(){ document.getElementById('selectedCount').textContent = document.querySelectorAll('.material-cb:checked').length; }
document.querySelectorAll('.material-cb').forEach(c=>c.addEventListener('change',updateCount));
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../includes/layout.php'; ?>
