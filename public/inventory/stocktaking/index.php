<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) redirect('../../auth/login.php');
if (!hasPermission('inventory_stocktaking_view')) {
    setAlert('ليس لديك صلاحية', 'error'); redirect('../../dashboard.php');
}

$model = new StocktakingSession();
$stats = $model->getStocktakingStats();
$statusFilter = $_GET['status'] ?? '';
$where = ''; $params = [];
if ($statusFilter && in_array($statusFilter, ['draft','in_progress','completed','approved','cancelled'])) {
    $where = "WHERE ss.status = ?"; $params = [$statusFilter];
}
$sessions = $model->fetchAll(
    "SELECT ss.*, u.full_name as created_by_name FROM stocktaking_sessions ss LEFT JOIN users u ON ss.created_by = u.id {$where} ORDER BY ss.created_at DESC", $params
);

$pageTitle = 'إدارة الجرد';
$currentPage = 'stocktaking';
ob_start();
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i>إدارة الجرد</h2>
            <p class="text-muted mb-0">إنشاء وإدارة جلسات جرد المخزون مع دعم الباركود</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (hasPermission('inventory_stocktaking_create')): ?>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>جلسة جرد جديدة</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <?php
        $cards = [
            ['إجمالي الجلسات', $stats['total_sessions']??0, 'clipboard-list', 'primary'],
            ['جاري التنفيذ', $stats['active_sessions']??0, 'spinner', 'info'],
            ['بانتظار الاعتماد', $stats['completed_sessions']??0, 'clock', 'warning'],
            ['معتمدة', $stats['approved_sessions']??0, 'check-double', 'success'],
        ];
        foreach($cards as $c): ?>
        <div class="col-md-3">
            <div class="card bg-<?=$c[3]?> text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><div class="small opacity-75"><?=$c[0]?></div><div class="fs-4 fw-bold"><?=number_format($c[1])?></div></div>
                        <i class="fas fa-<?=$c[2]?> fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mb-3"><div class="col-12">
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-outline-secondary <?=empty($statusFilter)?'active':''?>">الكل</a>
            <?php foreach(['draft'=>'مسودة','in_progress'=>'جاري','completed'=>'مكتمل','approved'=>'معتمد','cancelled'=>'ملغي'] as $k=>$v): ?>
            <a href="?status=<?=$k?>" class="btn btn-outline-secondary <?=$statusFilter===$k?'active':''?>"><?=$v?></a>
            <?php endforeach; ?>
        </div>
    </div></div>

    <div class="card">
        <div class="card-body p-0">
        <?php if (empty($sessions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا توجد جلسات جرد</h5>
                <?php if (hasPermission('inventory_stocktaking_create')): ?>
                <a href="create.php" class="btn btn-primary mt-2"><i class="fas fa-plus me-1"></i>إنشاء أول جلسة</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="stocktakingTable">
                    <thead class="table-light">
                        <tr><th>رقم الجلسة</th><th>العنوان</th><th>النوع</th><th>التاريخ</th><th>المواد</th><th>التقدم</th><th>الحالة</th><th>المنشئ</th><th>الإجراءات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s):
                        $sm = ['draft'=>['مسودة','secondary'],'in_progress'=>['جاري','info'],'completed'=>['مكتمل','warning'],'approved'=>['معتمد','success'],'cancelled'=>['ملغي','danger']];
                        $si = $sm[$s['status']] ?? ['?','secondary'];
                        $counted = ($s['total_items']??0) - ($s['not_counted_items']??0);
                        $pct = $s['total_items'] > 0 ? round($counted/$s['total_items']*100) : 0;
                    ?>
                        <tr>
                            <td><a href="view.php?id=<?=$s['id']?>" class="fw-bold text-decoration-none"><?=htmlspecialchars($s['session_number'])?></a></td>
                            <td><?=htmlspecialchars($s['title'])?></td>
                            <td><span class="badge bg-<?=$s['session_type']==='full'?'primary':'info'?>"><?=$s['session_type']==='full'?'كامل':'جزئي'?></span></td>
                            <td><small><?=formatDate($s['start_date'])?></small></td>
                            <td><span class="badge bg-secondary"><?=$s['total_items']??0?></span></td>
                            <td style="min-width:100px">
                                <div class="progress" style="height:6px"><div class="progress-bar bg-<?=$pct>=100?'success':'info'?>" style="width:<?=$pct?>%"></div></div>
                                <small class="text-muted"><?=$counted?>/<?=$s['total_items']??0?></small>
                            </td>
                            <td><span class="badge bg-<?=$si[1]?>"><?=$si[0]?></span></td>
                            <td><small><?=htmlspecialchars($s['created_by_name']??'')?></small></td>
                            <td>
                                <div class="btn-group">
                                    <a href="view.php?id=<?=$s['id']?>" class="btn btn-sm btn-outline-primary" title="عرض"><i class="fas fa-eye"></i></a>
                                    <?php if ($s['status']==='in_progress' && hasPermission('inventory_stocktaking_count')): ?>
                                    <a href="count.php?id=<?=$s['id']?>" class="btn btn-sm btn-outline-success" title="العد"><i class="fas fa-barcode"></i></a>
                                    <?php endif; ?>
                                </div>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    if($('#stocktakingTable').length) $('#stocktakingTable').DataTable({
        language:{sSearch:"ابحث:",sZeroRecords:"لا توجد نتائج",sInfo:"_START_ إلى _END_ من _TOTAL_",
        oPaginate:{sFirst:"الأول",sPrevious:"السابق",sNext:"التالي",sLast:"الأخير"}},
        order:[[3,'desc']],pageLength:25,columnDefs:[{orderable:false,targets:8}]
    });
});
</script>
