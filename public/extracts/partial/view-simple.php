<?php
/**
 * صفحة عرض مبسطة لتشخيص المشكلة
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo "<div style='padding: 20px;'>";
    echo "<h2>يجب تسجيل الدخول أولاً</h2>";
    echo "<a href='/etganplus/public/auth/login.php'>تسجيل الدخول</a>";
    echo "</div>";
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_view_details')) {
    echo "<div style='padding: 20px;'>";
    echo "<h2>ليس لديك صلاحية لعرض تفاصيل المستخلصات</h2>";
    echo "<a href='index.php'>العودة للقائمة</a>";
    echo "</div>";
    exit();
}

// التحقق من وجود معرف المستخلص
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div style='padding: 20px;'>";
    echo "<h2>معرف المستخلص غير صحيح</h2>";
    echo "<a href='index.php'>العودة للقائمة</a>";
    echo "</div>";
    exit();
}

$extract_id = (int) $_GET['id'];

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>عرض المستخلص الجزئي</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body>";

echo "<div class='container mt-5'>";
echo "<h2>عرض المستخلص الجزئي - ID: $extract_id</h2>";

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    
    echo "<div class='alert alert-success'>تم تحميل ملفات التكوين بنجاح</div>";
    
    $db = getDB();
    echo "<div class='alert alert-success'>تم الاتصال بقاعدة البيانات بنجاح</div>";
    
    // جلب تفاصيل المستخلص
    $extractQuery = "
        SELECT pe.*, 
               b.name as branch_name,
               u.full_name as created_by_name
        FROM partial_extracts pe
        LEFT JOIN branches b ON pe.branch_id = b.id
        LEFT JOIN users u ON pe.created_by = u.id
        WHERE pe.id = ?
    ";
    
    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        echo "<div class='alert alert-warning'>المستخلص غير موجود</div>";
        echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
    } else {
        echo "<div class='alert alert-success'>تم جلب بيانات المستخلص بنجاح</div>";
        
        echo "<div class='card'>";
        echo "<div class='card-header'>";
        echo "<h5>تفاصيل المستخلص الجزئي</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        echo "<div class='row'>";
        echo "<div class='col-md-6'>";
        echo "<p><strong>رقم المستخلص:</strong> " . htmlspecialchars($extract['extract_number']) . "</p>";
        echo "<p><strong>الفرع:</strong> " . htmlspecialchars($extract['branch_name'] ?? 'غير محدد') . "</p>";
        echo "<p><strong>تاريخ المستخلص:</strong> " . date('Y-m-d', strtotime($extract['extract_date'])) . "</p>";
        echo "</div>";
        echo "<div class='col-md-6'>";
        echo "<p><strong>المبلغ الإجمالي:</strong> " . number_format($extract['total_amount'], 2) . " ريال</p>";
        echo "<p><strong>الصافي:</strong> " . number_format($extract['net_amount'], 2) . " ريال</p>";
        echo "<p><strong>الحالة:</strong> " . htmlspecialchars($extract['status']) . "</p>";
        echo "</div>";
        echo "</div>";
        
        if (!empty($extract['description'])) {
            echo "<hr>";
            echo "<p><strong>ملاحظات:</strong> " . htmlspecialchars($extract['description']) . "</p>";
        }
        
        echo "</div>";
        echo "</div>";
        
        // جلب أوامر العمل
        echo "<div class='card mt-3'>";
        echo "<div class='card-header'>";
        echo "<h5>أوامر العمل المرتبطة</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $workOrdersQuery = "
                SELECT pewo.*, wo.work_order_number,
                       wot.type_code,
                       -- شهادة الإنجاز
                       cc.completion_certificate_confirmation,
                       -- التخريد
                       df.status as demolition_status
                FROM partial_extract_work_orders pewo
                LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
                LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
                LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
                LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
                WHERE pewo.partial_extract_id = ?
                ORDER BY wo.work_order_number
            ";
            
            $stmt = $db->prepare($workOrdersQuery);
            $stmt->execute([$extract_id]);
            $workOrders = $stmt->fetchAll();
            
            if (empty($workOrders)) {
                echo "<p class='text-muted'>لا توجد أوامر عمل مرتبطة بهذا المستخلص</p>";
            } else {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-bordered'>";
                echo "<thead>";
                echo "<tr>";
                echo "<th>رقم أمر العمل</th>";
                echo "<th>كود النوع</th>";
                echo "<th>قيمة المستخلص</th>";
                echo "<th>تاريخ الإنجاز</th>";
                echo "<th>تأكيد شهادة الإنجاز</th>";
                echo "<th>التخريد</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($workOrders as $wo) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($wo['work_order_number']) . "</td>";
                    echo "<td><span class='badge bg-primary'>" . htmlspecialchars($wo['type_code']) . "</span></td>";
                    echo "<td>" . number_format($wo['extract_value'], 2) . " ريال</td>";
                    echo "<td>" . date('Y-m-d', strtotime($wo['completion_date'])) . "</td>";

                    // تأكيد شهادة الإنجاز
                    echo "<td>";
                    $confirmationStatus = $wo['completion_certificate_confirmation'] ?? 'empty';
                    switch ($confirmationStatus) {
                        case 'confirmed':
                            echo "<span class='badge bg-success'>مؤكد</span>";
                            break;
                        case 'accepted':
                            echo "<span class='badge bg-info'>مقبول</span>";
                            break;
                        case 'rejected':
                            echo "<span class='badge bg-danger'>مرفوض</span>";
                            break;
                        case 'empty':
                        default:
                            echo "<span class='badge bg-secondary'>فارغ</span>";
                            break;
                    }
                    echo "</td>";

                    // التخريد
                    echo "<td>";
                    $demolitionStatus = $wo['demolition_status'] ?? 'not_applicable';
                    switch ($demolitionStatus) {
                        case 'attached':
                            echo "<span class='badge bg-success'>مرفق</span>";
                            break;
                        case 'not_applicable':
                            echo "<span class='badge bg-success'>لا ينطبق</span>";
                            break;
                        case 'not_attached':
                        default:
                            echo "<span class='badge bg-warning'>غير مرفق</span>";
                            break;
                    }
                    echo "</td>";

                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-warning'>خطأ في جلب أوامر العمل: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<div class='mt-3'>";
echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
echo "<a href='create.php' class='btn btn-success ms-2'>إضافة مستخلص جديد</a>";
echo "</div>";

echo "</div>"; // container
echo "<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>";
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>";
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

echo "<script>";
echo "
$(document).ready(function() {
    // إضافة نموذج تحديث سريع
    const quickUpdateForm = `
        <div class='card mt-4 border-warning'>
            <div class='card-header bg-warning text-dark'>
                <h6 class='mb-0'>تحديث سريع للاعتماد</h6>
            </div>
            <div class='card-body'>
                <div class='row g-3'>
                    <div class='col-md-4'>
                        <select class='form-select' id='quick_approval_stage'>
                            <option value=''>اختر المرحلة</option>
                            <option value='technical_support'>المساندة الفنية</option>
                            <option value='construction'>الإنشاءات</option>
                            <option value='department_manager'>مدير الدائرة</option>
                            <option value='administration_manager'>مدير الإدارة</option>
                            <option value='taif_finance'>مالية الطائف</option>
                            <option value='disbursed'>مصروف</option>
                        </select>
                    </div>
                    <div class='col-md-3'>
                        <input type='date' class='form-control' id='quick_disbursement_date' placeholder='تاريخ الصرف'>
                    </div>
                    <div class='col-md-3'>
                        <input type='text' class='form-control' id='quick_approval_notes' placeholder='ملاحظات'>
                    </div>
                    <div class='col-md-2'>
                        <button type='button' id='quickUpdateBtn' class='btn btn-success w-100'>تحديث</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('body .container').append(quickUpdateForm);

    // معالج التحديث السريع
    $('#quickUpdateBtn').click(function() {
        const button = $(this);
        const extractId = $extract_id;

        const formData = {
            extract_id: extractId,
            approval_stage: $('#quick_approval_stage').val(),
            disbursement_date: $('#quick_disbursement_date').val(),
            approval_notes: $('#quick_approval_notes').val()
        };

        if (!formData.approval_stage) {
            Swal.fire({
                icon: 'warning',
                title: 'تحذير',
                text: 'يرجى اختيار مرحلة الاعتماد'
            });
            return;
        }

        button.prop('disabled', true);
        button.html('جاري التحديث...');

        $.ajax({
            url: 'update-approval-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'نجح التحديث',
                        text: response.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = 'حدث خطأ في التحديث';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: errorMessage
                });
            },
            complete: function() {
                button.prop('disabled', false);
                button.html('تحديث');
            }
        });
    });
});
";
echo "</script>";

echo "</body>";
echo "</html>";
?>
