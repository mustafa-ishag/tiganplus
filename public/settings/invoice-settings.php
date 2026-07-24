<?php
/**
 * صفحة إدارة إعدادات الفواتير الضريبية
 * Invoice Settings Management Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    $db = getDB();
} catch (Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
    exit();
}

// جلب الإعدادات الحالية
$settings = null;
try {
    $settingsQuery = "SELECT * FROM invoice_settings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->query($settingsQuery);
    $settings = $stmt->fetch();
} catch (Exception $e) {
    $error = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $supplier_name = trim($_POST['supplier_name']);
        $supplier_address = trim($_POST['supplier_address']);
        $supplier_tax_number = trim($_POST['supplier_tax_number']);
        $client_name = trim($_POST['client_name']);
        $client_address = trim($_POST['client_address']);
        $client_tax_number = trim($_POST['client_tax_number']);
        $contract_date = !empty($_POST['contract_date']) ? $_POST['contract_date'] : null;
        $invoice_title = trim($_POST['invoice_title']);
        $tax_rate = floatval($_POST['tax_rate']);
        $currency = trim($_POST['currency']);
        $header_color = trim($_POST['header_color']);
        $accent_color = trim($_POST['accent_color']);
        $final_header_color = trim($_POST['final_header_color']);
        $final_accent_color = trim($_POST['final_accent_color']);
        $final_extract_header_color = trim($_POST['final_extract_header_color']);
        $final_extract_accent_color = trim($_POST['final_extract_accent_color']);

        // التحقق من صحة البيانات
        if (empty($supplier_name) || empty($supplier_address) || empty($supplier_tax_number) ||
            empty($client_name) || empty($client_address) || empty($client_tax_number) ||
            empty($invoice_title) || empty($currency)) {
            throw new Exception('جميع الحقول مطلوبة');
        }

        if ($tax_rate < 0 || $tax_rate > 100) {
            throw new Exception('نسبة الضريبة يجب أن تكون بين 0 و 100');
        }

        // معالجة رفع الشعار
        $logo_path = $settings ? $settings['supplier_logo_path'] : null;
        if (isset($_FILES['supplier_logo']) && $_FILES['supplier_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/logos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['supplier_logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('نوع الملف غير مدعوم. يرجى رفع صورة بصيغة JPG, PNG أو GIF');
            }

            $new_filename = 'logo_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['supplier_logo']['tmp_name'], $upload_path)) {
                // حذف الشعار القديم إذا كان موجوداً
                if ($logo_path && file_exists(__DIR__ . '/../../' . $logo_path)) {
                    unlink(__DIR__ . '/../../' . $logo_path);
                }
                $logo_path = 'uploads/logos/' . $new_filename;
            } else {
                throw new Exception('فشل في رفع الشعار');
            }
        }

        // معالجة رفع الختم
        $stamp_path = $settings ? $settings['stamp_path'] : null;
        if (isset($_FILES['stamp']) && $_FILES['stamp']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/logos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['stamp']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('نوع ملف الختم غير مدعوم. يرجى رفع صورة بصيغة JPG, PNG أو GIF');
            }

            $new_filename = 'stamp_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['stamp']['tmp_name'], $upload_path)) {
                // حذف الختم القديم إذا كان موجوداً
                if ($stamp_path && file_exists(__DIR__ . '/../../' . $stamp_path)) {
                    unlink(__DIR__ . '/../../' . $stamp_path);
                }
                $stamp_path = 'uploads/logos/' . $new_filename;
            } else {
                throw new Exception('فشل في رفع الختم');
            }
        }

        if ($settings) {
            // تحديث الإعدادات الموجودة
            $updateQuery = "
                UPDATE invoice_settings SET
                    supplier_name = ?,
                    supplier_address = ?,
                    supplier_tax_number = ?,
                    supplier_logo_path = ?,
                    stamp_path = ?,
                    client_name = ?,
                    client_address = ?,
                    client_tax_number = ?,
                    contract_date = ?,
                    invoice_title = ?,
                    tax_rate = ?,
                    currency = ?,
                    header_color = ?,
                    accent_color = ?,
                    final_header_color = ?,
                    final_accent_color = ?,
                    final_extract_header_color = ?,
                    final_extract_accent_color = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";

            $stmt = $db->prepare($updateQuery);
            $stmt->execute([
                $supplier_name, $supplier_address, $supplier_tax_number, $logo_path, $stamp_path,
                $client_name, $client_address, $client_tax_number,
                $contract_date, $invoice_title, $tax_rate, $currency,
                $header_color, $accent_color, $final_header_color, $final_accent_color,
                $final_extract_header_color, $final_extract_accent_color, $settings['id']
            ]);
        } else {
            // إنشاء إعدادات جديدة
            $insertQuery = "
                INSERT INTO invoice_settings (
                    supplier_name, supplier_address, supplier_tax_number, supplier_logo_path, stamp_path,
                    client_name, client_address, client_tax_number,
                    contract_date, invoice_title, tax_rate, currency,
                    header_color, accent_color, final_header_color, final_accent_color,
                    final_extract_header_color, final_extract_accent_color, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $db->prepare($insertQuery);
            $stmt->execute([
                $supplier_name, $supplier_address, $supplier_tax_number, $logo_path, $stamp_path,
                $client_name, $client_address, $client_tax_number,
                $contract_date, $invoice_title, $tax_rate, $currency,
                $header_color, $accent_color, $final_header_color, $final_accent_color,
                $final_extract_header_color, $final_extract_accent_color, $user_id
            ]);
        }

        $success = "تم حفظ الإعدادات بنجاح";

        // إعادة جلب الإعدادات المحدثة
        $stmt = $db->query($settingsQuery);
        $settings = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// تعيين القيم الافتراضية إذا لم توجد إعدادات
if (!$settings) {
    $settings = [
        'supplier_name' => '',
        'supplier_address' => '',
        'supplier_tax_number' => '',
        'supplier_logo_path' => '',
        'stamp_path' => '',
        'client_name' => '',
        'client_address' => '',
        'client_tax_number' => '',
        'contract_date' => '',
        'invoice_title' => 'فاتورة ضريبية',
        'tax_rate' => 15.00,
        'currency' => 'ريال سعودي',
        'header_color' => '#2c3e50',
        'accent_color' => '#3498db',
        'final_header_color' => '#176cb4',
        'final_accent_color' => '#4CAF50',
        'final_extract_header_color' => '#8E44AD',
        'final_extract_accent_color' => '#E74C3C'
    ];
}

$pageTitle = 'إعدادات الفواتير الضريبية';
$currentPage = 'settings';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإعدادات', 'url' => 'settings/index.php'],
    ['title' => 'إعدادات الفواتير', 'url' => '']
];

// بدء تخزين المحتوى
ob_start();
?>

<style>
    .color-preview {
        width: 40px;
        height: 40px;
        border-radius: 5px;
        border: 2px solid #ddd;
        display: inline-block;
        vertical-align: middle;
        margin-left: 10px;
    }

    .logo-preview {
        max-width: 200px;
        max-height: 100px;
        border: 2px dashed #ddd;
        padding: 10px;
        border-radius: 5px;
    }

    .settings-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .section-title {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>

<!-- رسائل النجاح والخطأ -->
<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-primary">
            <i class="fas fa-cog text-primary me-2"></i>
            إعدادات الفواتير الضريبية
        </h1>
        <p class="text-muted mb-0">إدارة بيانات الشركة والعميل وإعدادات الفواتير</p>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <!-- بيانات المورد (الشركة) -->
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-building text-primary me-2"></i>
            بيانات المورد (الشركة)
        </h4>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="supplier_name" class="form-label">اسم الشركة <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="supplier_name" name="supplier_name"
                       value="<?php echo htmlspecialchars($settings['supplier_name']); ?>" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="supplier_tax_number" class="form-label">الرقم الضريبي <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="supplier_tax_number" name="supplier_tax_number"
                       value="<?php echo htmlspecialchars($settings['supplier_tax_number']); ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mb-3">
                <label for="supplier_address" class="form-label">العنوان <span class="text-danger">*</span></label>
                <textarea class="form-control" id="supplier_address" name="supplier_address" rows="3" required><?php echo htmlspecialchars($settings['supplier_address']); ?></textarea>
            </div>

            <div class="col-md-4 mb-3">
                <label for="supplier_logo" class="form-label">شعار الشركة</label>
                <input type="file" class="form-control" id="supplier_logo" name="supplier_logo" accept="image/*">
                <small class="text-muted">JPG, PNG, GIF - حد أقصى 2MB</small>

                <?php if ($settings['supplier_logo_path']): ?>
                    <div class="mt-2">
                        <img src="../../<?php echo htmlspecialchars($settings['supplier_logo_path']); ?>"
                             alt="شعار الشركة" class="logo-preview">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="stamp" class="form-label">ختم الشركة</label>
                <input type="file" class="form-control" id="stamp" name="stamp" accept="image/*">
                <small class="text-muted">JPG, PNG, GIF - حد أقصى 2MB</small>

                <?php if (!empty($settings['stamp_path'])): ?>
                    <div class="mt-2">
                        <img src="../../<?php echo htmlspecialchars($settings['stamp_path']); ?>"
                             alt="ختم الشركة" class="logo-preview">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- بيانات العميل -->
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-user-tie text-success me-2"></i>
            بيانات العميل
        </h4>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="client_name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="client_name" name="client_name"
                       value="<?php echo htmlspecialchars($settings['client_name']); ?>" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="client_tax_number" class="form-label">الرقم الضريبي <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="client_tax_number" name="client_tax_number"
                       value="<?php echo htmlspecialchars($settings['client_tax_number']); ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="client_address" class="form-label">العنوان <span class="text-danger">*</span></label>
            <textarea class="form-control" id="client_address" name="client_address" rows="3" required><?php echo htmlspecialchars($settings['client_address']); ?></textarea>
        </div>
    </div>

    <!-- بيانات العقد -->
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-file-contract text-warning me-2"></i>
            بيانات العقد
        </h4>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="contract_date" class="form-label">تاريخ العقد</label>
                <input type="date" class="form-control" id="contract_date" name="contract_date"
                       value="<?php echo htmlspecialchars($settings['contract_date']); ?>">
            </div>
        </div>
    </div>

    <!-- إعدادات الفاتورة -->
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-receipt text-info me-2"></i>
            إعدادات الفاتورة
        </h4>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="invoice_title" class="form-label">عنوان الفاتورة <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="invoice_title" name="invoice_title"
                       value="<?php echo htmlspecialchars($settings['invoice_title']); ?>" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="tax_rate" class="form-label">نسبة الضريبة (%) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="tax_rate" name="tax_rate"
                       value="<?php echo $settings['tax_rate']; ?>" min="0" max="100" step="0.01" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="currency" class="form-label">العملة <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="currency" name="currency"
                       value="<?php echo htmlspecialchars($settings['currency']); ?>" required>
            </div>
        </div>
    </div>

    <!-- إعدادات التصميم -->
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-palette text-purple me-2"></i>
            إعدادات التصميم
        </h4>

        <h5 class="mb-3">ألوان الفاتورة الجزئية:</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="header_color" class="form-label">لون رأس الفاتورة الجزئية</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="header_color" name="header_color"
                           value="<?php echo htmlspecialchars($settings['header_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['header_color']); ?>" readonly>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <label for="accent_color" class="form-label">اللون المميز للفاتورة الجزئية</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color"
                           value="<?php echo htmlspecialchars($settings['accent_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['accent_color']); ?>" readonly>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">ألوان الفاتورة النهائية للجزئية:</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="final_header_color" class="form-label">لون رأس الفاتورة النهائية</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="final_header_color" name="final_header_color"
                           value="<?php echo htmlspecialchars($settings['final_header_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['final_header_color']); ?>" readonly>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <label for="final_accent_color" class="form-label">اللون المميز للفاتورة النهائية</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="final_accent_color" name="final_accent_color"
                           value="<?php echo htmlspecialchars($settings['final_accent_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['final_accent_color']); ?>" readonly>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">ألوان المستخلص النهائي العادي:</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="final_extract_header_color" class="form-label">لون رأس المستخلص النهائي</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="final_extract_header_color" name="final_extract_header_color"
                           value="<?php echo htmlspecialchars($settings['final_extract_header_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['final_extract_header_color']); ?>" readonly>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <label for="final_extract_accent_color" class="form-label">اللون المميز للمستخلص النهائي</label>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="final_extract_accent_color" name="final_extract_accent_color"
                           value="<?php echo htmlspecialchars($settings['final_extract_accent_color']); ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['final_extract_accent_color']); ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- أزرار الحفظ -->
    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>
            حفظ الإعدادات
        </button>

        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للوحة التحكم
        </a>
    </div>
</form>

<script>
    // تحديث معاينة الألوان
    document.getElementById('header_color').addEventListener('change', function() {
        this.nextElementSibling.value = this.value;
    });

    document.getElementById('accent_color').addEventListener('change', function() {
        this.nextElementSibling.value = this.value;
    });

    // معاينة الشعار قبل الرفع
    document.getElementById('supplier_logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let preview = document.querySelector('#supplier_logo + small + div img');
                if (!preview) {
                    const div = document.createElement('div');
                    div.className = 'mt-2';
                    preview = document.createElement('img');
                    preview.className = 'logo-preview';
                    preview.alt = 'معاينة الشعار';
                    div.appendChild(preview);
                    document.getElementById('supplier_logo').parentNode.appendChild(div);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // معاينة الختم قبل الرفع
    document.getElementById('stamp').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let preview = document.querySelector('#stamp + small + div img');
                if (!preview) {
                    const div = document.createElement('div');
                    div.className = 'mt-2';
                    preview = document.createElement('img');
                    preview.className = 'logo-preview';
                    preview.alt = 'معاينة الختم';
                    div.appendChild(preview);
                    document.getElementById('stamp').parentNode.appendChild(div);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
?>