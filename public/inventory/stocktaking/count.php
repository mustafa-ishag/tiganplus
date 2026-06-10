<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) redirect('../../auth/login.php');
if (!hasPermission('inventory_stocktaking_count')) {
    setAlert('ليس لديك صلاحية', 'error'); redirect('index.php');
}

$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId <= 0) { setAlert('معرف غير صحيح', 'error'); redirect('index.php'); }

$model = new StocktakingSession();
$session = $model->getSessionWithItems($sessionId);
if (!$session) { setAlert('الجلسة غير موجودة', 'error'); redirect('index.php'); }
if ($session['status'] !== 'in_progress') { redirect('view.php?id=' . $sessionId); }

$pageTitle = 'العد - ' . $session['session_number'];
$currentPage = 'stocktaking';
ob_start();
?>
<style>
.count-card{border:2px solid #e2e8f0;border-radius:12px;transition:border-color .3s}
.count-card.matched{border-color:#198754;background:#f0fff4}
.count-card.surplus{border-color:#0d6efd;background:#f0f4ff}
.count-card.deficit{border-color:#dc3545;background:#fff5f5}
.count-card.pending{border-color:#ffc107;background:#fffdf0}
.scanner-area{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:12px;padding:1.5rem;color:#fff;margin-bottom:1.5rem}
.scanner-area .btn{border-radius:8px}
.barcode-display{font-family:'Courier New',monospace;font-size:1.1rem;font-weight:700;letter-spacing:2px}
.diff-badge{font-size:0.9rem;padding:4px 10px;border-radius:6px;font-weight:700}
.qty-input{font-size:1.1rem;font-weight:600;text-align:center;border:2px solid #dee2e6;border-radius:8px;padding:8px;width:120px}
.qty-input:focus{border-color:#2c5aa0;box-shadow:0 0 0 .2rem rgba(44,90,160,.25)}
.item-row{padding:12px 16px;border-bottom:1px solid #f0f0f0;transition:background .2s}
.item-row:hover{background:#f8f9fa}
.item-row.just-saved{animation:savedFlash .8s ease}
@keyframes savedFlash{0%{background:#d4edda}100%{background:transparent}}
#scannerVideo{width:100%;max-width:400px;border-radius:8px;border:3px solid #00ff88}
</style>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-7">
            <h2 class="h4 mb-0"><i class="fas fa-barcode text-primary me-2"></i>عد المواد - <?=htmlspecialchars($session['session_number'])?></h2>
            <p class="text-muted mb-0 small"><?=htmlspecialchars($session['title'])?></p>
        </div>
        <div class="col-md-5 text-end">
            <span class="badge bg-info me-2"><?=$session['total_items']?> مادة</span>
            <a href="view.php?id=<?=$sessionId?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>التفاصيل</a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i>العودة</a>
        </div>
    </div>

    <!-- شريط التقدم -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="progress" style="height:10px;border-radius:5px">
                        <div class="progress-bar bg-success" id="progressBar" style="width:0%"></div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span id="progressText" class="fw-bold">0</span> / <?=$session['total_items']?> مادة
                    <span class="ms-2">
                        <span class="text-success"><i class="fas fa-check-circle"></i> <span id="matchCount">0</span></span>
                        <span class="text-primary ms-1"><i class="fas fa-arrow-up"></i> <span id="surplusCount">0</span></span>
                        <span class="text-danger ms-1"><i class="fas fa-arrow-down"></i> <span id="deficitCount">0</span></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- منطقة المسح -->
    <div class="scanner-area">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-white"><i class="fas fa-barcode"></i></span>
                    <input type="text" id="barcodeInput" class="form-control form-control-lg" placeholder="امسح الباركود أو أدخل رقم البند..." autofocus autocomplete="off">
                    <button class="btn btn-success" onclick="lookupBarcode()"><i class="fas fa-search"></i></button>
                </div>
                <small class="text-white-50 mt-1 d-block">امسح الباركود بالماسح الضوئي أو أدخل رقم البند يدوياً</small>
            </div>
            <div class="col-md-3 text-center">
                <button class="btn btn-outline-light" id="cameraBtn" onclick="toggleCamera()">
                    <i class="fas fa-camera me-1"></i>فتح الكاميرا
                </button>
            </div>
            <div class="col-md-3">
                <div id="cameraContainer" style="display:none" class="text-center">
                    <div id="reader" style="width:100%;max-width:300px;margin:0 auto"></div>
                    <button class="btn btn-sm btn-danger mt-1" onclick="toggleCamera()"><i class="fas fa-times"></i> إغلاق</button>
                </div>
            </div>
        </div>
        <!-- نتيجة المسح -->
        <div id="scanResult" class="mt-3" style="display:none">
            <div class="card bg-dark border-success">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-3"><span class="barcode-display text-success" id="scannedCode"></span></div>
                        <div class="col-md-4"><span id="scannedDesc" class="text-white-50"></span></div>
                        <div class="col-md-2"><span class="text-white-50">في النظام: <strong id="scannedSysQty" class="text-white"></strong></span></div>
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <input type="number" id="scannedQtyInput" class="form-control bg-dark text-white border-success" step="0.001" min="0" placeholder="الكمية">
                                <button class="btn btn-success" onclick="saveScanCount()"><i class="fas fa-check"></i> حفظ</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- قائمة المواد -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>قائمة المواد</h5>
            <div>
                <select class="form-select form-select-sm d-inline-block" style="width:auto" id="filterStatus">
                    <option value="all">الكل</option>
                    <option value="pending">لم تُعد</option>
                    <option value="counted">تم عدها</option>
                    <option value="discrepancy">بها فروقات</option>
                </select>
                <input type="text" class="form-control form-control-sm d-inline-block ms-1" style="width:200px" id="searchItems" placeholder="بحث...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="40">#</th>
                            <th>رقم البند</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th class="text-center">كمية النظام</th>
                            <th class="text-center">الكمية المحصاة</th>
                            <th class="text-center">الفرق</th>
                            <th class="text-center">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($session['items'] as $i => $item): ?>
                        <tr class="item-row" id="row-<?=$item['material_id']?>"
                            data-status="<?=$item['status']?>"
                            data-item="<?=htmlspecialchars($item['item_number'])?>"
                            data-desc="<?=htmlspecialchars($item['description'])?>"
                            data-diff="<?=$item['difference']??0?>">
                            <td class="text-muted small"><?=$i+1?></td>
                            <td><strong class="barcode-display"><?=htmlspecialchars($item['item_number'])?></strong></td>
                            <td dir="ltr" class="text-start"><small><?=htmlspecialchars(mb_substr($item['description'],0,50))?></small></td>
                            <td><?=htmlspecialchars($item['unit'])?></td>
                            <td class="text-center fw-bold"><?=formatNumber($item['system_quantity'],3)?></td>
                            <td class="text-center">
                                <input type="number" class="qty-input" step="0.001" min="0"
                                    id="qty-<?=$item['material_id']?>"
                                    value="<?=$item['counted_quantity'] !== null ? $item['counted_quantity'] : ''?>"
                                    data-material="<?=$item['material_id']?>"
                                    data-system="<?=$item['system_quantity']?>"
                                    onchange="saveItemCount(this)"
                                    placeholder="—">
                            </td>
                            <td class="text-center" id="diff-<?=$item['material_id']?>">
                                <?php if($item['counted_quantity'] !== null):
                                    $d = $item['difference'];
                                    $cls = $d == 0 ? 'bg-success' : ($d > 0 ? 'bg-primary' : 'bg-danger');
                                ?>
                                <span class="diff-badge <?=$cls?> text-white"><?=$d > 0 ? '+' : ''?><?=formatNumber($d,3)?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-center" id="status-<?=$item['material_id']?>">
                                <?php if($item['status']==='counted'): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge bg-warning"><i class="fas fa-clock"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- أزرار الإجراءات -->
    <div class="mt-4 d-flex justify-content-between">
        <button class="btn btn-success btn-lg" onclick="completeSession()" id="completeBtn">
            <i class="fas fa-check-double me-2"></i>إكمال الجرد
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">حفظ والمتابعة لاحقاً</a>
    </div>
</div>

<!-- html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const SESSION_ID = <?=$sessionId?>;
const TOTAL_ITEMS = <?=$session['total_items']?>;
let html5QrCode = null;
let cameraOpen = false;
let scannedMaterialId = null;

// تحديث الإحصائيات
function updateStats(){
    let counted=0, matched=0, surplus=0, deficit=0;
    document.querySelectorAll('.item-row').forEach(r=>{
        if(r.dataset.status==='counted'){
            counted++;
            const d = parseFloat(r.dataset.diff)||0;
            if(d===0) matched++; else if(d>0) surplus++; else deficit++;
        }
    });
    document.getElementById('progressText').textContent=counted;
    document.getElementById('progressBar').style.width=(counted/TOTAL_ITEMS*100)+'%';
    document.getElementById('matchCount').textContent=matched;
    document.getElementById('surplusCount').textContent=surplus;
    document.getElementById('deficitCount').textContent=deficit;
}
updateStats();

// حفظ عد مادة
function saveItemCount(input){
    const materialId = input.dataset.material;
    const qty = parseFloat(input.value);
    if(isNaN(qty)||qty<0) return;

    fetch('save-count-ajax.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({session_id:SESSION_ID, material_id:materialId, counted_quantity:qty, input_method:'manual'})
    }).then(r=>r.json()).then(data=>{
        if(data.success){
            const row = document.getElementById('row-'+materialId);
            row.dataset.status='counted';
            const d = data.difference;
            row.dataset.diff = d;
            const cls = d==0?'bg-success':(d>0?'bg-primary':'bg-danger');
            document.getElementById('diff-'+materialId).innerHTML=
                `<span class="diff-badge ${cls} text-white">${d>0?'+':''}${parseFloat(d).toFixed(3)}</span>`;
            document.getElementById('status-'+materialId).innerHTML=
                '<span class="badge bg-success"><i class="fas fa-check"></i></span>';
            row.classList.add('just-saved');
            setTimeout(()=>row.classList.remove('just-saved'),800);
            updateStats();
        } else { alert('خطأ: '+data.message); }
    }).catch(e=>alert('خطأ في الاتصال'));
}

// بحث باركود
function lookupBarcode(){
    const code = document.getElementById('barcodeInput').value.trim();
    if(!code) return;
    fetch('scan-barcode-ajax.php?session_id='+SESSION_ID+'&barcode='+encodeURIComponent(code))
    .then(r=>r.json()).then(data=>{
        if(data.success){
            scannedMaterialId = data.material_id;
            document.getElementById('scannedCode').textContent = data.item_number;
            document.getElementById('scannedDesc').textContent = data.description;
            document.getElementById('scannedSysQty').textContent = data.system_quantity;
            document.getElementById('scannedQtyInput').value = data.counted_quantity || '';
            document.getElementById('scanResult').style.display = '';
            document.getElementById('scannedQtyInput').focus();
            // scroll to row
            const row = document.getElementById('row-'+data.material_id);
            if(row) row.scrollIntoView({behavior:'smooth',block:'center'});
        } else {
            alert(data.message || 'المادة غير موجودة في هذه الجلسة');
        }
        document.getElementById('barcodeInput').value='';
        document.getElementById('barcodeInput').focus();
    });
}

function saveScanCount(){
    if(!scannedMaterialId) return;
    const qty = parseFloat(document.getElementById('scannedQtyInput').value);
    if(isNaN(qty)||qty<0){ alert('أدخل كمية صحيحة'); return; }
    const input = document.getElementById('qty-'+scannedMaterialId);
    if(input){ input.value = qty; saveItemCount(input); }
    document.getElementById('scanResult').style.display='none';
    document.getElementById('barcodeInput').focus();
    scannedMaterialId = null;
}

// ادخال باركود بالإنتر
document.getElementById('barcodeInput').addEventListener('keypress', function(e){
    if(e.key==='Enter'){ e.preventDefault(); lookupBarcode(); }
});

// كاميرا
function toggleCamera(){
    const container = document.getElementById('cameraContainer');
    if(cameraOpen){
        if(html5QrCode) html5QrCode.stop().catch(()=>{});
        container.style.display='none';
        cameraOpen=false;
        document.getElementById('cameraBtn').innerHTML='<i class="fas fa-camera me-1"></i>فتح الكاميرا';
    } else {
        container.style.display='';
        cameraOpen=true;
        document.getElementById('cameraBtn').innerHTML='<i class="fas fa-times me-1"></i>إغلاق الكاميرا';
        html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start(
            {facingMode:"environment"},
            {fps:10, qrbox:{width:250,height:100}},
            (text)=>{
                document.getElementById('barcodeInput').value=text;
                lookupBarcode();
                if(navigator.vibrate) navigator.vibrate(200);
            },
            ()=>{}
        ).catch(err=>{ alert('فشل فتح الكاميرا: '+err); toggleCamera(); });
    }
}

// فلتر
document.getElementById('filterStatus').addEventListener('change', function(){
    const v=this.value;
    document.querySelectorAll('.item-row').forEach(r=>{
        if(v==='all') r.style.display='';
        else if(v==='pending') r.style.display=r.dataset.status==='pending'?'':'none';
        else if(v==='counted') r.style.display=r.dataset.status==='counted'?'':'none';
        else if(v==='discrepancy') r.style.display=(r.dataset.status==='counted'&&parseFloat(r.dataset.diff)!==0)?'':'none';
    });
});
document.getElementById('searchItems').addEventListener('input', function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('.item-row').forEach(r=>{
        const t=(r.dataset.item||'')+' '+(r.dataset.desc||'');
        r.style.display=t.toLowerCase().includes(q)?'':'none';
    });
});

// إكمال الجلسة
function completeSession(){
    const pending = document.querySelectorAll('.item-row[data-status="pending"]').length;
    if(pending>0){ 
        if(!confirm(`لا يزال هناك ${pending} مادة لم يتم عدها. هل تريد المتابعة؟`)) return;
    }
    if(!confirm('هل أنت متأكد من إكمال الجرد؟')) return;
    fetch('update-status-ajax.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({session_id:SESSION_ID, action:'complete'})
    }).then(r=>r.json()).then(data=>{
        if(data.success){ alert('تم إكمال الجرد بنجاح'); window.location.href='view.php?id='+SESSION_ID; }
        else alert('خطأ: '+data.message);
    });
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../includes/layout.php'; ?>
