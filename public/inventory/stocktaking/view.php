<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) redirect('../../auth/login.php');
if (!hasPermission('inventory_stocktaking_view')) {
    setAlert('ليس لديك صلاحية', 'error'); redirect('index.php');
}

$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId <= 0) { setAlert('معرف غير صحيح', 'error'); redirect('index.php'); }

$model = new StocktakingSession();
$session = $model->getSessionWithItems($sessionId);
if (!$session) { setAlert('الجلسة غير موجودة', 'error'); redirect('index.php'); }

$statusMap = ['draft'=>['مسودة','secondary','pen'],'in_progress'=>['جاري التنفيذ','info','spinner'],
    'completed'=>['مكتمل','warning','check'],'approved'=>['معتمد','success','check-double'],'cancelled'=>['ملغي','danger','times']];
$si = $statusMap[$session['status']] ?? ['?','secondary','question'];

$counted = ($session['total_items']??0) - ($session['not_counted_items']??0);
$pct = $session['total_items'] > 0 ? round($counted/$session['total_items']*100) : 0;

$pageTitle = 'تفاصيل الجرد - ' . $session['session_number'];
$currentPage = 'stocktaking';
ob_start();
?>
<style>
.stat-card{border-radius:10px;padding:1rem;text-align:center;border:1px solid #e2e8f0}
.stat-card .num{font-size:1.8rem;font-weight:700}
.stat-card .lbl{font-size:.8rem;color:#6c757d}
.detail-label{font-weight:600;color:#6c757d;font-size:.85rem}
.detail-value{font-weight:500}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-7">
            <h2 class="h4 mb-1"><i class="fas fa-clipboard-check text-primary me-2"></i><?=htmlspecialchars($session['session_number'])?></h2>
            <p class="text-muted mb-0"><?=htmlspecialchars($session['title'])?></p>
        </div>
        <div class="col-md-5 text-end">
            <?php if ($session['status'] === 'in_progress' && hasPermission('inventory_stocktaking_count')): ?>
            <a href="count.php?id=<?=$sessionId?>" class="btn btn-success"><i class="fas fa-barcode me-1"></i>متابعة العد</a>
            <?php endif; ?>
            <?php if ($session['status'] === 'completed' && hasPermission('inventory_stocktaking_approve')): ?>
            <button class="btn btn-primary" onclick="approveSession()"><i class="fas fa-check-double me-1"></i>اعتماد وتسوية</button>
            <?php endif; ?>
            <?php if (in_array($session['status'],['draft','in_progress','completed']) && hasPermission('inventory_stocktaking_create')): ?>
            <button class="btn btn-outline-danger" onclick="cancelSession()"><i class="fas fa-times me-1"></i>إلغاء</button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right me-1"></i>العودة</a>
        </div>
    </div>

    <!-- معلومات الجلسة -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="detail-label">الحالة</div><span class="badge bg-<?=$si[1]?> fs-6"><i class="fas fa-<?=$si[2]?> me-1"></i><?=$si[0]?></span></div>
                        <div class="col-md-4"><div class="detail-label">النوع</div><div class="detail-value"><?=$session['session_type']==='full'?'جرد كامل':'جرد جزئي'?></div></div>
                        <div class="col-md-4"><div class="detail-label">تاريخ البدء</div><div class="detail-value"><?=formatDate($session['start_date'])?></div></div>
                        <div class="col-md-4"><div class="detail-label">المنشئ</div><div class="detail-value"><?=htmlspecialchars($session['created_by_name']??'')?></div></div>
                        <?php if ($session['approved_by_name']): ?>
                        <div class="col-md-4"><div class="detail-label">المعتمد</div><div class="detail-value"><?=htmlspecialchars($session['approved_by_name'])?></div></div>
                        <div class="col-md-4"><div class="detail-label">تاريخ الاعتماد</div><div class="detail-value"><?=formatDate($session['approved_at'])?></div></div>
                        <?php endif; ?>
                        <?php if ($session['notes']): ?>
                        <div class="col-12"><div class="detail-label">ملاحظات</div><div class="detail-value"><?=htmlspecialchars($session['notes'])?></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- التقدم -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="fw-bold">تقدم الجرد</span><span><?=$pct?>%</span></div>
                    <div class="progress mb-3" style="height:12px;border-radius:6px">
                        <div class="progress-bar bg-success" style="width:<?=$pct?>%"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col"><div class="stat-card"><div class="num text-primary"><?=$session['total_items']??0?></div><div class="lbl">إجمالي المواد</div></div></div>
                        <div class="col"><div class="stat-card"><div class="num text-success"><?=$session['matched_items']??0?></div><div class="lbl">متطابقة</div></div></div>
                        <div class="col"><div class="stat-card"><div class="num text-info"><?=$session['surplus_items']??0?></div><div class="lbl">فائض</div></div></div>
                        <div class="col"><div class="stat-card"><div class="num text-danger"><?=$session['deficit_items']??0?></div><div class="lbl">عجز</div></div></div>
                        <div class="col"><div class="stat-card"><div class="num text-warning"><?=$session['not_counted_items']??0?></div><div class="lbl">لم تُعد</div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- رسم بياني -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie me-1"></i>توزيع النتائج</h6></div>
                <div class="card-body"><canvas id="resultChart" height="200"></canvas></div>
            </div>
        </div>
    </div>

    <!-- جدول المواد -->
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>تفاصيل المواد</h5>
            <select class="form-select form-select-sm" style="width:auto" id="viewFilter">
                <option value="all">الكل</option><option value="discrepancy">بها فروقات فقط</option>
                <option value="surplus">فائض</option><option value="deficit">عجز</option>
            </select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr><th>#</th><th>رقم البند</th><th>الوصف</th><th>الوحدة</th><th class="text-center">كمية النظام</th><th class="text-center">الكمية المحصاة</th><th class="text-center">الفرق</th><th class="text-center">الحالة</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($session['items'] as $i=>$item):
                        $d = $item['difference'] ?? 0;
                        $rowClass = '';
                        if($item['status']==='counted'){ if($d>0) $rowClass='table-info'; elseif($d<0) $rowClass='table-danger'; }
                    ?>
                    <tr class="<?=$rowClass?>" data-diff="<?=$d?>" data-status="<?=$item['status']?>">
                        <td class="text-muted small"><?=$i+1?></td>
                        <td><strong><?=htmlspecialchars($item['item_number'])?></strong></td>
                        <td dir="ltr" class="text-start"><small><?=htmlspecialchars(mb_substr($item['description'],0,50))?></small></td>
                        <td><?=htmlspecialchars($item['unit'])?></td>
                        <td class="text-center"><?=formatNumber($item['system_quantity'],3)?></td>
                        <td class="text-center fw-bold"><?=$item['counted_quantity']!==null?formatNumber($item['counted_quantity'],3):'<span class="text-muted">—</span>'?></td>
                        <td class="text-center">
                            <?php if($item['counted_quantity']!==null):
                                $cls=$d==0?'bg-success':($d>0?'bg-primary':'bg-danger'); ?>
                            <span class="badge <?=$cls?>"><?=$d>0?'+':''?><?=formatNumber($d,3)?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($item['status']==='counted'): ?><span class="badge bg-success"><i class="fas fa-check"></i></span>
                            <?php else: ?><span class="badge bg-warning"><i class="fas fa-clock"></i></span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Chart
new Chart(document.getElementById('resultChart'),{
    type:'doughnut',
    data:{
        labels:['متطابقة','فائض','عجز','لم تُعد'],
        datasets:[{data:[<?=$session['matched_items']??0?>,<?=$session['surplus_items']??0?>,<?=$session['deficit_items']??0?>,<?=$session['not_counted_items']??0?>],
            backgroundColor:['#198754','#0d6efd','#dc3545','#ffc107']}]
    },
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{family:'Tajawal'}}}}}
});

// Filter
document.getElementById('viewFilter').addEventListener('change',function(){
    const v=this.value;
    document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{
        const d=parseFloat(r.dataset.diff)||0, s=r.dataset.status;
        if(v==='all') r.style.display='';
        else if(v==='discrepancy') r.style.display=(s==='counted'&&d!==0)?'':'none';
        else if(v==='surplus') r.style.display=(s==='counted'&&d>0)?'':'none';
        else if(v==='deficit') r.style.display=(s==='counted'&&d<0)?'':'none';
    });
});

function approveSession(){
    if(!confirm('هل أنت متأكد من اعتماد الجرد وتسوية المخزون؟\nسيتم تعديل المخزون تلقائياً للمواد التي بها فروقات.')) return;
    fetch('update-status-ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({session_id:<?=$sessionId?>,action:'approve'})
    }).then(r=>r.json()).then(d=>{
        if(d.success){alert('تم اعتماد الجرد وتسوية '+d.adjustments_count+' مادة');location.reload();}
        else alert('خطأ: '+d.message);
    });
}
function cancelSession(){
    if(!confirm('هل أنت متأكد من إلغاء هذه الجلسة؟')) return;
    fetch('update-status-ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({session_id:<?=$sessionId?>,action:'cancel'})
    }).then(r=>r.json()).then(d=>{
        if(d.success){alert('تم إلغاء الجلسة');location.reload();}
        else alert('خطأ: '+d.message);
    });
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../includes/layout.php'; ?>
