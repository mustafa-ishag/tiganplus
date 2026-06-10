<?php
/**
 * صفحة استيراد بيانات المقايسة من الصور باستخدام OCR
 * Import BOQ Data from Images using OCR
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'استيراد مقايسة من صور (OCR)';
$currentPage = 'import-boq';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'شهادات الإنجاز', 'url' => 'inventory/completion-certificates/index.php'],
    ['title' => 'استيراد مقايسة (OCR)', 'url' => 'inventory/completion-certificates/import-boq.php']
];

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// جلب أوامر العمل
$db = getDB();
$workOrders = $db->query("
    SELECT wo.id, wo.work_order_number, wot.type_code 
    FROM work_orders wo
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    WHERE wo.status = 'active'
    ORDER BY wo.work_order_number
")->fetchAll();

ob_start();
?>

<style>
    /* ===== المتغيرات ===== */
    :root {
        --ocr-primary: #4f46e5;
        --ocr-primary-light: rgba(79, 70, 229, 0.1);
        --ocr-success: #059669;
        --ocr-warning: #d97706;
        --ocr-danger: #dc2626;
        --ocr-card-bg: #fff;
        --ocr-border: #e5e7eb;
        --ocr-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* ===== بطاقات الصفحة ===== */
    .ocr-card {
        background: var(--ocr-card-bg);
        border-radius: 12px;
        border: 1px solid var(--ocr-border);
        box-shadow: var(--ocr-shadow);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .ocr-card .card-header {
        background: linear-gradient(135deg, var(--ocr-primary), #7c3aed);
        color: white;
        padding: 14px 20px;
        font-weight: 600;
        font-size: 15px;
    }

    .ocr-card .card-header i {
        margin-left: 8px;
    }

    .ocr-card .card-body {
        padding: 20px;
    }

    /* ===== منطقة الرفع ===== */
    .upload-zone {
        border: 2px dashed var(--ocr-border);
        border-radius: 12px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f9fafb;
        position: relative;
    }

    .upload-zone:hover,
    .upload-zone.drag-over {
        border-color: var(--ocr-primary);
        background: var(--ocr-primary-light);
    }

    .upload-zone i.upload-icon {
        font-size: 48px;
        color: var(--ocr-primary);
        margin-bottom: 12px;
        display: block;
    }

    .upload-zone h5 {
        color: #374151;
        margin-bottom: 8px;
    }

    .upload-zone p {
        color: #6b7280;
        font-size: 14px;
        margin: 0;
    }

    .upload-zone input[type="file"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    /* ===== قائمة الملفات المرفوعة ===== */
    .file-list {
        list-style: none;
        padding: 0;
        margin: 15px 0 0;
    }

    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 8px;
        border: 1px solid var(--ocr-border);
    }

    .file-item .file-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .file-item .file-info i {
        color: var(--ocr-primary);
        font-size: 18px;
    }

    .file-item .file-name {
        font-weight: 500;
        font-size: 14px;
    }

    .file-item .file-size {
        color: #9ca3af;
        font-size: 12px;
    }

    .file-item .file-status {
        font-size: 13px;
    }

    .file-item .file-status.pending {
        color: #9ca3af;
    }

    .file-item .file-status.processing {
        color: var(--ocr-warning);
    }

    .file-item .file-status.done {
        color: var(--ocr-success);
    }

    .file-item .file-status.error {
        color: var(--ocr-danger);
    }

    .file-item .btn-remove {
        background: none;
        border: none;
        color: var(--ocr-danger);
        cursor: pointer;
        padding: 4px 8px;
        font-size: 16px;
    }

    /* ===== شريط التقدم ===== */
    .progress-section {
        margin: 20px 0;
        display: none;
    }

    .progress-bar-container {
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--ocr-primary), #7c3aed);
        border-radius: 4px;
        transition: width 0.3s ease;
        width: 0;
    }

    .progress-text {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-size: 13px;
        color: #6b7280;
    }

    /* ===== جدول النتائج ===== */
    .results-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }

    .results-table thead th {
        background: #f3f4f6;
        padding: 10px 12px;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid var(--ocr-border);
        text-align: center;
        white-space: nowrap;
    }

    .results-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .results-table tbody tr:hover {
        background: #f9fafb;
    }

    .results-table .editable-cell {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 4px;
        padding: 4px 8px;
        min-width: 60px;
    }

    .results-table input.edit-input {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 13px;
        width: 100%;
        text-align: center;
    }

    .results-table input.edit-input:focus {
        outline: none;
        border-color: var(--ocr-primary);
        box-shadow: 0 0 0 2px var(--ocr-primary-light);
    }

    /* ===== أزرار ===== */
    .btn-ocr-primary {
        background: linear-gradient(135deg, var(--ocr-primary), #7c3aed);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-ocr-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        color: white;
    }

    .btn-ocr-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-ocr-success {
        background: linear-gradient(135deg, var(--ocr-success), #047857);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-ocr-success:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        color: white;
    }

    .btn-ocr-success:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* ===== تحذيرات ===== */
    .ocr-alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ocr-alert.info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .ocr-alert.warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .ocr-alert.success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .ocr-alert.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    /* ===== النص الخام ===== */
    .raw-text-toggle {
        cursor: pointer;
        color: var(--ocr-primary);
        font-size: 13px;
        margin-top: 10px;
        display: inline-block;
    }

    .raw-text-box {
        background: #1f2937;
        color: #e5e7eb;
        padding: 15px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 12px;
        direction: ltr;
        text-align: left;
        max-height: 200px;
        overflow-y: auto;
        white-space: pre-wrap;
        display: none;
        margin-top: 10px;
    }

    /* ===== حالة بدون بيانات ===== */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 12px;
        display: block;
    }

    /* ===== التجاوبية ===== */
    @media (max-width: 768px) {
        .upload-zone {
            padding: 25px 15px;
        }

        .upload-zone i.upload-icon {
            font-size: 36px;
        }

        .results-table {
            font-size: 12px;
        }

        .results-table thead th,
        .results-table tbody td {
            padding: 6px 8px;
        }
    }
</style>

<div class="container-fluid py-3">
    <!-- العنوان -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-camera me-2" style="color: var(--ocr-primary);"></i> استيراد مقايسة من صور
            (OCR)</h4>
        <a href="create.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-right me-1"></i> العودة لشهادات الإنجاز
        </a>
    </div>

    <!-- تنبيه توضيحي -->
    <div class="ocr-alert info">
        <i class="fas fa-info-circle"></i>
        <span>قم برفع صور المقايسة من النظام وسيتم استخراج بيانات المواد تلقائياً. يمكنك مراجعة وتعديل البيانات قبل
            الحفظ.</span>
    </div>

    <!-- الخطوة 1: اختيار أمر العمل -->
    <div class="ocr-card" style="overflow: visible;">
        <div class="card-header"><i class="fas fa-clipboard-list"></i> الخطوة 1: اختيار أمر العمل</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label fw-bold">أمر العمل <span class="text-danger">*</span></label>
                    <select id="workOrderSelect" class="form-select">
                        <option value="">-- اختر أمر عمل --</option>
                        <?php foreach ($workOrders as $wo): ?>
                            <option value="<?= $wo['id'] ?>">
                                <?= htmlspecialchars($wo['work_order_number']) ?>
                                <?= $wo['type_code'] ? ' - ' . htmlspecialchars($wo['type_code']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">شهادة الإنجاز (اختياري)</label>
                    <select id="certificateSelect" class="form-select" disabled>
                        <option value="">-- اختر أو أنشئ جديدة --</option>
                    </select>
                    <small class="text-muted">اختر شهادة موجودة لإضافة المواد إليها، أو اتركها فارغة</small>
                </div>
            </div>
        </div>
    </div>

    <!-- الخطوة 2: رفع الصور -->
    <div class="ocr-card" id="uploadCard" style="display: none;">
        <div class="card-header"><i class="fas fa-cloud-upload-alt"></i> الخطوة 2: رفع الصور</div>
        <div class="card-body">
            <div class="upload-zone" id="uploadZone">
                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                <h5>اسحب الصور هنا أو انقر للاختيار</h5>
                <p>صيغ مدعومة: PNG, JPG, BMP, TIFF — حد أقصى 20 صورة</p>
                <input type="file" id="fileInput" accept="image/*" multiple>
            </div>

            <ul class="file-list" id="fileList"></ul>

            <!-- شريط التقدم -->
            <div class="progress-section" id="progressSection">
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="progressBar"></div>
                </div>
                <div class="progress-text">
                    <span id="progressLabel">جاري المعالجة...</span>
                    <span id="progressPercent">0%</span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="text-muted" id="filesCount">لم يتم اختيار ملفات</span>
                <button class="btn-ocr-primary" id="processBtn" disabled onclick="processAllImages()">
                    <i class="fas fa-magic me-1"></i> معالجة الصور
                </button>
            </div>
        </div>
    </div>

    <!-- الخطوة 3: مراجعة النتائج -->
    <div class="ocr-card" id="resultsCard" style="display: none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-table"></i> الخطوة 3: مراجعة وتعديل النتائج</div>
            <span class="badge bg-light text-dark" id="totalMaterialsBadge">0 مادة</span>
        </div>
        <div class="card-body">
            <div class="ocr-alert warning" id="reviewWarning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>يرجى مراجعة البيانات المستخرجة وتعديلها عند الحاجة. قد يحتاج OCR لتصحيح يدوي.</span>
            </div>

            <div class="table-responsive">
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" checked></th>
                            <th>#</th>
                            <th>رقم البند</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>الكمية الفعلية</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody"></tbody>
                </table>
            </div>

            <!-- النص الخام -->
            <span class="raw-text-toggle" onclick="toggleRawText()">
                <i class="fas fa-code me-1"></i> عرض/إخفاء النص الخام
            </span>
            <div class="raw-text-box" id="rawTextBox"></div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <button class="btn btn-outline-danger btn-sm" onclick="clearResults()">
                    <i class="fas fa-trash me-1"></i> مسح الكل
                </button>
                <button class="btn-ocr-success" id="saveBtn" onclick="saveToDatabase()">
                    <i class="fas fa-save me-1"></i> حفظ المواد في شهادة الإنجاز
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ===== المتغيرات العامة =====
    var selectedFiles = [];
    var allExtractedMaterials = [];
    var allRawTexts = [];

    // ===== الخطوة 1: اختيار أمر العمل =====
    document.getElementById('workOrderSelect').addEventListener('change', function () {
        var woId = this.value;
        var uploadCard = document.getElementById('uploadCard');
        var certSelect = document.getElementById('certificateSelect');

        if (woId) {
            uploadCard.style.display = 'block';
            certSelect.disabled = false;
            // جلب شهادات الإنجاز لأمر العمل
            loadCertificates(woId);
        } else {
            uploadCard.style.display = 'none';
            certSelect.disabled = true;
            certSelect.innerHTML = '<option value="">-- اختر أو أنشئ جديدة --</option>';
        }
    });

    function loadCertificates(workOrderId) {
        var select = document.getElementById('certificateSelect');
        select.innerHTML = '<option value="">-- جاري التحميل... --</option>';

        var db = null;
        fetch('index.php?ajax=certificates&work_order_id=' + workOrderId)
            .then(function (r) { return r.json(); })
            .catch(function () { return { certificates: [] }; })
            .then(function (data) {
                select.innerHTML = '<option value="new">إنشاء شهادة جديدة تلقائياً</option>';
                if (data.certificates && data.certificates.length) {
                    data.certificates.forEach(function (c) {
                        select.innerHTML += '<option value="' + c.id + '">' + c.title + ' (' + c.certificate_date + ')</option>';
                    });
                }
            });
    }

    // ===== الخطوة 2: رفع الصور =====
    var uploadZone = document.getElementById('uploadZone');
    var fileInput = document.getElementById('fileInput');

    // السحب والإفلات
    uploadZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    uploadZone.addEventListener('dragleave', function () {
        this.classList.remove('drag-over');
    });
    uploadZone.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', function () {
        handleFiles(this.files);
        this.value = ''; // سماح بإعادة الاختيار
    });

    function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            if (selectedFiles.length >= 20) {
                alert('الحد الأقصى 20 صورة');
                break;
            }
            if (files[i].type.startsWith('image/')) {
                selectedFiles.push(files[i]);
            }
        }
        renderFileList();
    }

    function renderFileList() {
        var list = document.getElementById('fileList');
        var countEl = document.getElementById('filesCount');
        var btn = document.getElementById('processBtn');

        list.innerHTML = '';
        selectedFiles.forEach(function (file, idx) {
            var size = (file.size / 1024).toFixed(1);
            list.innerHTML += '<li class="file-item" id="file-' + idx + '">' +
                '<div class="file-info">' +
                '<i class="fas fa-image"></i>' +
                '<div><div class="file-name">' + file.name + '</div>' +
                '<div class="file-size">' + size + ' KB</div></div>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '<span class="file-status pending" id="status-' + idx + '">في الانتظار</span>' +
                '<button class="btn-remove" onclick="removeFile(' + idx + ')">&times;</button>' +
                '</div>' +
                '</li>';
        });

        countEl.textContent = selectedFiles.length > 0 ? selectedFiles.length + ' ملفات مختارة' : 'لم يتم اختيار ملفات';
        btn.disabled = selectedFiles.length === 0;
    }

    function removeFile(idx) {
        selectedFiles.splice(idx, 1);
        renderFileList();
    }

    // ===== معالجة الصور =====
    function processAllImages() {
        if (selectedFiles.length === 0) return;

        var progressSection = document.getElementById('progressSection');
        var progressBar = document.getElementById('progressBar');
        var progressLabel = document.getElementById('progressLabel');
        var progressPercent = document.getElementById('progressPercent');
        var processBtn = document.getElementById('processBtn');

        progressSection.style.display = 'block';
        processBtn.disabled = true;
        allExtractedMaterials = [];
        allRawTexts = [];

        var total = selectedFiles.length;
        var completed = 0;
        var errors = 0;

        function processNext(idx) {
            if (idx >= total) {
                // انتهت المعالجة
                progressLabel.textContent = 'اكتملت المعالجة!';
                processBtn.disabled = false;
                showResults();
                return;
            }

            var statusEl = document.getElementById('status-' + idx);
            statusEl.className = 'file-status processing';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
            progressLabel.textContent = 'جاري معالجة: ' + selectedFiles[idx].name;

            var formData = new FormData();
            formData.append('boq_image', selectedFiles[idx]);

            fetch('process-boq-image.php', {
                method: 'POST',
                body: formData
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    completed++;
                    var pct = Math.round((completed / total) * 100);
                    progressBar.style.width = pct + '%';
                    progressPercent.textContent = pct + '%';

                    if (data.success) {
                        statusEl.className = 'file-status done';
                        statusEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.materials_count + ' مادة';

                        // إضافة المواد مع المصدر
                        data.materials.forEach(function (m) {
                            m._source = selectedFiles[idx].name;
                            allExtractedMaterials.push(m);
                        });
                        allRawTexts.push({ file: selectedFiles[idx].name, text: data.raw_text });

                        // === اختيار أمر العمل تلقائياً إذا تم اكتشافه ===
                        if (data.matched_work_order && data.matched_work_order.id) {
                            var woSelect = document.getElementById('workOrderSelect');
                            var woId = data.matched_work_order.id.toString();
                            // البحث عن أمر العمل في القائمة المنسدلة
                            for (var oi = 0; oi < woSelect.options.length; oi++) {
                                if (woSelect.options[oi].value === woId) {
                                    woSelect.value = woId;
                                    woSelect.dispatchEvent(new Event('change'));
                                    break;
                                }
                            }
                        }
                    } else {
                        errors++;
                        statusEl.className = 'file-status error';
                        statusEl.innerHTML = '<i class="fas fa-times-circle"></i> ' + (data.error || 'خطأ');
                    }

                    processNext(idx + 1);
                })
                .catch(function (err) {
                    completed++;
                    errors++;
                    var pct = Math.round((completed / total) * 100);
                    progressBar.style.width = pct + '%';
                    progressPercent.textContent = pct + '%';

                    statusEl.className = 'file-status error';
                    statusEl.innerHTML = '<i class="fas fa-times-circle"></i> خطأ في الاتصال';

                    processNext(idx + 1);
                });
        }

        processNext(0);
    }

    // ===== عرض النتائج =====
    function showResults() {
        var card = document.getElementById('resultsCard');
        var tbody = document.getElementById('resultsBody');
        var badge = document.getElementById('totalMaterialsBadge');

        if (allExtractedMaterials.length === 0) {
            card.style.display = 'block';
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">لم يتم استخراج أي مواد من الصور</td></tr>';
            badge.textContent = '0 مادة';
            return;
        }

        badge.textContent = allExtractedMaterials.length + ' مادة';

        tbody.innerHTML = allExtractedMaterials.map(function (m, i) {
            var dbStatus = m.found_in_db 
                ? '<i class="fas fa-check-circle text-success" title="موجود في قاعدة البيانات"></i>' 
                : '<i class="fas fa-exclamation-triangle text-warning" title="غير موجود في قاعدة البيانات"></i>';
            return '<tr id="row-' + i + '">' +
                '<td class="text-center"><input type="checkbox" class="material-check" data-idx="' + i + '" checked></td>' +
                '<td class="text-center">' + (i + 1) + '</td>' +
                '<td><input class="edit-input" id="item-' + i + '" value="' + escapeHtml(m.item_number || '') + '"></td>' +
                '<td><input class="edit-input" id="desc-' + i + '" value="' + escapeHtml(mc.description || '') + '" style="min-width:200px;text-align:right;"></td>' +
                '<td><input class="edit-input" id="unit-' + i + '" value="' + escapeHtml(mc.unit || '') + '" style="width:60px;"></td>' +
                '<td><input class="edit-input" type="number" step="0.001" id="qty-' + i + '" value="' + (m.estimated_quantity || 0) + '" style="width:90px;"></td>' +
                '<td class="text-center">' + dbStatus + '</td>' +
                '<td><button class="btn-remove" onclick="removeRow(' + i + ')" title="حذف">&times;</button></td>' +
                '</tr>';
        }).join('');

        card.style.display = 'block';

        // عرض النص الخام
        var rawBox = document.getElementById('rawTextBox');
        rawBox.textContent = allRawTexts.map(function (r) {
            return '=== ' + r.file + ' ===\n' + r.text;
        }).join('\n\n');

        // تحديد الكل
        document.getElementById('selectAll').addEventListener('change', function () {
            var checks = document.querySelectorAll('.material-check');
            var val = this.checked;
            checks.forEach(function (c) { c.checked = val; });
        });
    }

    function removeRow(idx) {
        var row = document.getElementById('row-' + idx);
        if (row) row.style.display = 'none';
        var check = row.querySelector('.material-check');
        if (check) check.checked = false;
    }

    function toggleRawText() {
        var box = document.getElementById('rawTextBox');
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }

    function clearResults() {
        if (!confirm('هل أنت متأكد من مسح كل النتائج؟')) return;
        allExtractedMaterials = [];
        allRawTexts = [];
        document.getElementById('resultsCard').style.display = 'none';
        document.getElementById('resultsBody').innerHTML = '';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ===== حفظ في قاعدة البيانات =====
    function saveToDatabase() {
        var workOrderId = document.getElementById('workOrderSelect').value;
        var certificateId = document.getElementById('certificateSelect').value;

        if (!workOrderId) {
            alert('يرجى اختيار أمر العمل');
            return;
        }

        // جمع المواد المحددة
        var materialsToSave = [];
        var checks = document.querySelectorAll('.material-check:checked');

        checks.forEach(function (check) {
            var idx = check.dataset.idx;
            var row = document.getElementById('row-' + idx);
            if (!row || row.style.display === 'none') return;

            materialsToSave.push({
                item_number: document.getElementById('item-' + idx).value,
                description: document.getElementById('desc-' + idx).value,
                unit: document.getElementById('unit-' + idx).value,
                estimated_quantity: parseFloat(document.getElementById('qty-' + idx).value) || 0
            });
        });

        if (materialsToSave.length === 0) {
            alert('يرجى تحديد مادة واحدة على الأقل');
            return;
        }

        if (!confirm('سيتم حفظ ' + materialsToSave.length + ' مادة في شهادة الإنجاز. متأكد؟')) return;

        var saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...';

        fetch('save-boq-materials.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                work_order_id: workOrderId,
                certificate_id: certificateId,
                materials: materialsToSave
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> حفظ المواد في شهادة الإنجاز';

                if (data.success) {
                    // عرض رسالة نجاح
                    var alert = document.createElement('div');
                    alert.className = 'ocr-alert success';
                    alert.innerHTML = '<i class="fas fa-check-circle"></i> <span>تم حفظ ' + data.saved_count + ' مادة بنجاح في شهادة الإنجاز رقم ' + (data.certificate_id || '') + '</span>';
                    document.getElementById('resultsCard').querySelector('.card-body').prepend(alert);

                    // إخفاء التحذير
                    document.getElementById('reviewWarning').style.display = 'none';
                } else {
                    alert(data.error || 'حدث خطأ أثناء الحفظ');
                }
            })
            .catch(function (err) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> حفظ المواد في شهادة الإنجاز';
                alert('خطأ في الاتصال بالخادم');
            });
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>