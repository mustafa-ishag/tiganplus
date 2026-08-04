<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إضافة أمر عمل جديد';
$currentPage = 'work-orders';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أوامر العمل', 'url' => 'work-orders/index.php'],
    ['title' => 'إضافة أمر عمل جديد', 'url' => 'work-orders/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_create')) {
    header('Location: ' . path('work-orders/index.php'));
    exit();
}

try {
    $db = getDB();
    
    // جلب الفروع النشطة
    $branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب أنواع أوامر العمل النشطة
    $workOrderTypes = $db->query("SELECT * FROM work_order_types WHERE status = 'active' ORDER BY type_code")->fetchAll(PDO::FETCH_ASSOC);
    
    // No automatic number generation needed
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $branches = [];
    $workOrderTypes = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<style>
    /* Minimalist & Premium Design */
    .form-control, .form-select {
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        border: 1px solid transparent;
        background-color: #f1f5f9; /* Soft gray */
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s ease-in-out;
    }
    .form-control:focus, .form-select:focus {
        background-color: #ffffff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.1);
    }
    .form-label {
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.4rem;
        font-size: 0.8rem;
    }
    .card-custom {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    .btn-submit {
        background-color: var(--primary-color);
        border: none;
        border-radius: 0.5rem;
        padding: 0.6rem 2rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        transition: all 0.2s;
    }
    .btn-submit:hover {
        background-color: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
        color: white;
    }
    .btn-cancel {
        border-radius: 0.5rem;
        padding: 0.6rem 2rem;
        font-weight: 600;
        font-size: 0.9rem;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        transition: all 0.2s;
    }
    .btn-cancel:hover {
        background-color: #f8fafc;
        color: #0f172a;
    }
</style>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 shadow-sm py-2" role="alert">
        <i class="fas fa-exclamation-triangle ms-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- نموذج إضافة أمر العمل -->
<div class="card card-custom mb-4">
    <div class="card-body p-4 p-lg-5">
        <form id="createWorkOrderForm">
            
            <div class="row g-4">
                <!-- Row 1: Basic Info -->
                <div class="col-md-3">
                    <label for="work_order_number" class="form-label">رقم أمر العمل <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="work_order_number" name="work_order_number" required maxlength="9" pattern="[0-9]{9}" placeholder="مثال: 123456789">
                </div>
                
                <div class="col-md-3">
                    <label for="work_order_type_id" class="form-label">نوع أمر العمل <span class="text-danger">*</span></label>
                    <select class="form-select" id="work_order_type_id" name="work_order_type_id" required>
                        <option value="">اختر...</option>
                        <?php foreach ($workOrderTypes as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['type_code']) ?> - <?= htmlspecialchars($type['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="department" class="form-label">القسم <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="">اختر...</option>
                        <option value="connections">التوصيلات</option>
                        <option value="projects">المشاريع</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="branch_id" class="form-label">الفرع <span class="text-danger">*</span></label>
                    <select class="form-select" id="branch_id" name="branch_id" required>
                        <option value="">اختر...</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" data-code="<?= $branch['code'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Row 2: Dates & Financials -->
                <div class="col-md-3">
                    <label for="assignment_date" class="form-label">تاريخ التكليف</label>
                    <input type="date" class="form-control" id="assignment_date" name="assignment_date">
                </div>

                <div class="col-md-3">
                    <label for="receipt_date" class="form-label">تاريخ الاستلام</label>
                    <input type="date" class="form-control" id="receipt_date" name="receipt_date">
                </div>

                <div class="col-md-3">
                    <label for="estimated_value" class="form-label">القيمة المقدرة (ريال)</label>
                    <input type="number" class="form-control" id="estimated_value" name="estimated_value" step="0.01" min="0" placeholder="0.00">
                </div>

                <div class="col-md-3">
                    <label for="actual_value" class="form-label">القيمة الفعلية (ريال)</label>
                    <input type="number" class="form-control" id="actual_value" name="actual_value" step="0.01" min="0" placeholder="0.00">
                </div>

                <!-- Row 3: Status & Notes -->
                <div class="col-md-3">
                    <label for="disbursement_status" class="form-label">حالة الصرف</label>
                    <select class="form-select" id="disbursement_status" name="disbursement_status">
                        <option value="none">لا يوجد</option>
                        <option value="pending_disbursement">في انتظار الصرف</option>
                        <option value="disbursement">تم الصرف</option>
                        <option value="completed">مكتمل</option>
                        <option value="return">مرتجع</option>
                        <option value="partial_disbursement">صرف جزئي</option>
                        <option value="cancelled_disbursement">صرف ملغي</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">حالة أمر العمل</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                        <option value="completed">مكتمل</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="notes" class="form-label">الملاحظات</label>
                    <input type="text" class="form-control" id="notes" name="notes" placeholder="أدخل أي ملاحظات إضافية...">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-5 pt-4 border-top">
                <button type="button" class="btn-cancel" onclick="window.history.back()">إلغاء</button>
                <button type="submit" class="btn-submit">حفظ أمر العمل</button>
            </div>
        </form>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // إضافة CSS للتحقق من صحة رقم أمر العمل
    const style = document.createElement('style');
    style.textContent = `
        .work-order-valid {
            border-color: #28a745 !important;
            background-color: #f8fff9 !important;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
        }
        .work-order-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        .work-order-help-valid {
            color: #28a745 !important;
        }
        .work-order-help-invalid {
            color: #dc3545 !important;
        }
    `;
    document.head.appendChild(style);

    // التحقق من صحة رقم أمر العمل
    function validateWorkOrderNumber(input) {
        const value = input.val();
        const helpText = $('#work_order_number_help');

        // إزالة الفئات السابقة
        input.removeClass('work-order-valid work-order-invalid');
        helpText.removeClass('work-order-help-valid work-order-help-invalid');

        if (value.length === 0) {
            helpText.html('<i class="fas fa-info-circle me-1"></i>أدخل 9 أرقام بالضبط');
            return false;
        } else if (value.length < 9) {
            input.addClass('work-order-invalid');
            helpText.addClass('work-order-help-invalid');
            helpText.html('<i class="fas fa-exclamation-triangle me-1"></i>يجب إدخال 9 أرقام (تم إدخال ' + value.length + ')');
            return false;
        } else if (value.length === 9) {
            input.addClass('work-order-valid');
            helpText.addClass('work-order-help-valid');
            helpText.html('<i class="fas fa-check-circle me-1"></i>رقم أمر العمل صحيح');
            return true;
        }
        return false;
    }

    // معالج إدخال رقم أمر العمل
    $('#work_order_number').on('input', function() {
        let value = $(this).val();

        // إزالة أي أحرف غير رقمية
        value = value.replace(/[^0-9]/g, '');

        // تحديد الطول إلى 9 أرقام
        if (value.length > 9) {
            value = value.substring(0, 9);
        }

        // تحديث القيمة
        $(this).val(value);

        // التحقق من الصحة
        validateWorkOrderNumber($(this));
    });

    // منع لصق النصوص غير الرقمية
    $('#work_order_number').on('paste', function(e) {
        e.preventDefault();
        let paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        paste = paste.replace(/[^0-9]/g, '');
        if (paste.length > 9) {
            paste = paste.substring(0, 9);
        }
        $(this).val(paste);
        validateWorkOrderNumber($(this));
    });

    // منع إدخال أحرف غير رقمية من لوحة المفاتيح
    $('#work_order_number').on('keypress', function(e) {
        // السماح بمفاتيح التحكم (Backspace, Delete, Tab, Escape, Enter, Home, End, Arrow keys)
        if ([8, 9, 27, 13, 46, 35, 36, 37, 38, 39, 40].indexOf(e.keyCode) !== -1 ||
            // السماح بـ Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+Z
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true) ||
            (e.keyCode === 90 && e.ctrlKey === true)) {
            return;
        }

        // التأكد من أن المفتاح المضغوط هو رقم (0-9)
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }

        // منع إدخال أكثر من 9 أرقام
        if ($(this).val().length >= 9) {
            e.preventDefault();
        }
    });

    // No automatic number generation - user will enter manually

    // معالجة إرسال النموذج
    $('#createWorkOrderForm').on('submit', function(e) {
        e.preventDefault();

        // التحقق من صحة رقم أمر العمل قبل الإرسال
        const workOrderNumber = $('#work_order_number').val();

        if (!workOrderNumber || workOrderNumber.length !== 9 || !/^\d{9}$/.test(workOrderNumber)) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ في رقم أمر العمل!',
                text: 'يجب إدخال رقم أمر العمل مكون من 9 أرقام بالضبط',
                confirmButtonText: 'موافق'
            });
            $('#work_order_number').focus();
            return;
        }

        const formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');

        // Log form data for debugging
        console.log('Form submission started');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }

        // تعطيل الزر أثناء الإرسال
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...');
        
        $.ajax({
            url: 'create-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // إظهار رسالة نجاح
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح!',
                        text: 'تم إضافة أمر العمل بنجاح',
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        // الانتقال إلى صفحة القائمة مع تمييز أمر العمل الجديد
                        if (response.data && response.data.id) {
                            window.location.href = 'index.php?new_work_order=' + response.data.id;
                        } else {
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    // إظهار رسالة خطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: response.message || 'حدث خطأ أثناء إضافة أمر العمل',
                        confirmButtonText: 'موافق'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });

                let errorMessage = 'حدث خطأ أثناء الاتصال بالخادم';

                if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        console.error('Failed to parse error response:', e);
                        errorMessage += '\nتفاصيل الخطأ: ' + xhr.responseText.substring(0, 200);
                    }
                }

                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال!',
                    text: errorMessage,
                    confirmButtonText: 'موافق'
                });
            },
            complete: function() {
                // إعادة تفعيل الزر
                submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>حفظ أمر العمل');
            }
        });
    });

    // تحديد التاريخ الحالي كافتراضي لتاريخ التكليف
    const today = new Date().toISOString().split('T')[0];
    $('#assignment_date').val(today);
});
</script>
