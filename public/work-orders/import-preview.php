<?php

declare(strict_types=1);

/**
 * معاينة استيراد أوامر العمل
 * Work Orders Import Preview
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'معاينة استيراد أوامر العمل';
$currentPage = 'work-orders';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أوامر العمل', 'url' => 'work-orders/index.php'],
    ['title' => 'استيراد البيانات', 'url' => 'work-orders/import.php'],
    ['title' => 'معاينة البيانات', 'url' => 'work-orders/import-preview.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من وجود بيانات المعاينة
if (!isset($_SESSION['import_preview']) || !isset($_SESSION['import_filename'])) {
    $_SESSION['error'] = 'لا توجد بيانات للمعاينة. يرجى رفع ملف جديد.';
    header('Location: import.php');
    exit();
}

$db = getDB();
$userId = $_SESSION['user_id'];
$preview = $_SESSION['import_preview'];
$filename = $_SESSION['import_filename'];

$error = '';
$success = '';

// معالجة تأكيد الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    try {
        // تسجيل بداية عملية الاستيراد
        $logStmt = $db->prepare("
            INSERT INTO work_order_import_export_logs 
            (operation_type, file_name, file_format, total_records, operation_status, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            'import',
            $filename,
            'csv',
            $preview['total_records'],
            'processing',
            $userId
        ]);
        $logId = $db->lastInsertId();
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $db->beginTransaction();
        
        try {
            // معالجة جميع السجلات باستخدام INSERT ... ON DUPLICATE KEY UPDATE
            foreach ($preview['valid_records'] as $record) {
                // البحث عن العقد المطابق لتاريخ التكليف
                $contractId = null;
                if (!empty($record['assignment_date'])) {
                    $contractStmt = $db->prepare("SELECT id FROM contracts WHERE ? BETWEEN start_date AND end_date LIMIT 1");
                    $contractStmt->execute([$record['assignment_date']]);
                    $contractRow = $contractStmt->fetch();
                    if ($contractRow) {
                        $contractId = $contractRow['id'];
                    }
                }

                $upsertStmt = $db->prepare("
                    INSERT INTO work_orders (
                        work_order_number, work_order_type_id, department, current_entity_id, 
                        branch_id, location, assignment_date, receipt_date, estimated_value, 
                        actual_value, disbursement_status, status, notes, contract_id, created_at, updated_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        work_order_type_id = VALUES(work_order_type_id),
                        department = VALUES(department),
                        current_entity_id = VALUES(current_entity_id),
                        branch_id = VALUES(branch_id),
                        location = VALUES(location),
                        assignment_date = VALUES(assignment_date),
                        receipt_date = VALUES(receipt_date),
                        estimated_value = VALUES(estimated_value),
                        actual_value = VALUES(actual_value),
                        disbursement_status = VALUES(disbursement_status),
                        status = VALUES(status),
                        notes = VALUES(notes),
                        contract_id = VALUES(contract_id),
                        updated_at = NOW()
                ");

                if ($upsertStmt->execute([
                    $record['work_order_number'],
                    $record['work_order_type_id'],
                    $record['department'],
                    $record['current_entity_id'],
                    $record['branch_id'],
                    $record['location'],
                    $record['assignment_date'],
                    $record['receipt_date'],
                    $record['estimated_value'],
                    $record['actual_value'],
                    $record['disbursement_status'],
                    $record['status'],
                    $record['notes'],
                    $contractId
                ])) {
                    // الحصول على معرف أمر العمل
                    $workOrderId = $db->lastInsertId();
                    if (!$workOrderId) {
                        // إذا كان التحديث، نحصل على المعرف من قاعدة البيانات
                        // يجب البحث باستخدام رقم أمر العمل ونوع أمر العمل معاً
                        $getIdStmt = $db->prepare("SELECT id FROM work_orders WHERE work_order_number = ? AND work_order_type_id = ?");
                        $getIdStmt->execute([$record['work_order_number'], $record['work_order_type_id']]);
                        $workOrderId = $getIdStmt->fetchColumn();
                    }

                    // معالجة النماذج المرفقة إذا كانت موجودة
                    if (!empty($record['attachments']) && $workOrderId) {
                        foreach ($record['attachments'] as $formType => $attachmentData) {
                            // حذف النموذج الموجود أولاً
                            $deleteStmt = $db->prepare("DELETE FROM work_order_attachments WHERE work_order_id = ? AND form_type = ?");
                            $deleteStmt->execute([$workOrderId, $formType]);

                            // إدراج النموذج الجديد
                            $insertAttachmentStmt = $db->prepare("
                                INSERT INTO work_order_attachments (
                                    work_order_id, form_type, status, completion_certificate_confirmation,
                                    certificate_attached_date, certificate_confirmed_date, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ");

                            $confirmation = ($formType === 'completion_certificate') ? ($attachmentData['confirmation'] ?? null) : null;
                            $certificateAttachedDate = ($formType === 'completion_certificate') ? ($attachmentData['certificate_attached_date'] ?? null) : null;
                            $certificateConfirmedDate = ($formType === 'completion_certificate') ? ($attachmentData['certificate_confirmed_date'] ?? null) : null;

                            $insertAttachmentStmt->execute([
                                $workOrderId,
                                $formType,
                                $attachmentData['status'],
                                $confirmation,
                                $certificateAttachedDate,
                                $certificateConfirmedDate
                            ]);
                        }
                    }

                    $successCount++;
                } else {
                    $errors[] = "خطأ في معالجة أمر العمل: {$record['work_order_number']}";
                    $errorCount++;
                }
            }
            
            $db->commit();
            
            // تحديث سجل العملية
            $updateLogStmt = $db->prepare("
                UPDATE work_order_import_export_logs 
                SET successful_records = ?, failed_records = ?, operation_status = ?, 
                    error_message = ?, completed_at = NOW()
                WHERE id = ?
            ");
            
            $errorMessage = !empty($errors) ? implode('; ', array_slice($errors, 0, 5)) : null;
            
            $updateLogStmt->execute([
                $successCount,
                $errorCount,
                'completed',
                $errorMessage,
                $logId
            ]);
            
            // مسح بيانات المعاينة من الجلسة
            unset($_SESSION['import_preview']);
            unset($_SESSION['import_filename']);
            
            $_SESSION['success'] = "تم استيراد {$successCount} أمر عمل بنجاح";
            if ($errorCount > 0) {
                $_SESSION['success'] .= " مع {$errorCount} خطأ";
            }
            
            header('Location: index.php');
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = 'خطأ في تنفيذ الاستيراد: ' . $e->getMessage();
        
        // تحديث سجل العملية في حالة الخطأ
        if (isset($logId)) {
            try {
                $updateLogStmt = $db->prepare("
                    UPDATE work_order_import_export_logs 
                    SET operation_status = 'failed', error_message = ?, completed_at = NOW()
                    WHERE id = ?
                ");
                $updateLogStmt->execute([$error, $logId]);
            } catch (Exception $logError) {
                // تجاهل أخطاء التسجيل
            }
        }
    }
}

// معالجة إلغاء الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_import'])) {
    unset($_SESSION['import_preview']);
    unset($_SESSION['import_filename']);
    header('Location: import.php');
    exit();
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>
            معاينة استيراد أوامر العمل
        </h1>
        <p class="text-muted mb-0">مراجعة البيانات قبل تأكيد الاستيراد</p>
    </div>
    <div>
        <a href="import.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للاستيراد
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- File Info -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-file me-2"></i>
            معلومات الملف
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <strong>اسم الملف:</strong> <?= htmlspecialchars($filename) ?>
            </div>
            <div class="col-md-6">
                <strong>إجمالي السجلات:</strong> <?= $preview['total_records'] ?>
            </div>
        </div>
    </div>
</div>

<!-- Preview Summary -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <div class="h4 text-success"><?= count($preview['new_records']) ?></div>
                <div class="text-muted">أوامر عمل جديدة</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="h4 text-warning"><?= count($preview['update_records']) ?></div>
                <div class="text-muted">أوامر للتحديث</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <div class="h4 text-danger"><?= count($preview['error_records']) ?></div>
                <div class="text-muted">سجلات خاطئة</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <div class="h4 text-info"><?= count($preview['valid_records']) ?></div>
                <div class="text-muted">سجلات صالحة</div>
            </div>
        </div>
    </div>
</div>

<!-- New Records -->
<?php if (!empty($preview['new_records'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0 text-success">
                <i class="fas fa-plus-circle me-2"></i>
                أوامر عمل جديدة (<?= count($preview['new_records']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="newRecordsTable" class="table table-sm">
                    <thead>
                        <tr>
                            <th>رقم أمر العمل</th>
                            <th>نوع الأمر</th>
                            <th>القسم</th>
                            <th>الفرع</th>
                            <th>القيمة المقدرة</th>
                            <th>القيمة الفعلية</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview['new_records'] as $record): ?>
                            <?php
                            // الحصول على اسم نوع أمر العمل
                            $workOrderTypeName = 'غير محدد';
                            if (!empty($record['work_order_type_id'])) {
                                $typeStmt = $db->prepare("SELECT type_code FROM work_order_types WHERE id = ?");
                                $typeStmt->execute([$record['work_order_type_id']]);
                                $typeResult = $typeStmt->fetch();
                                if ($typeResult) {
                                    $workOrderTypeName = $typeResult['type_code'];
                                }
                            }

                            // الحصول على اسم الفرع
                            $branchName = 'غير محدد';
                            if (!empty($record['branch_id'])) {
                                $branchStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
                                $branchStmt->execute([$record['branch_id']]);
                                $branchResult = $branchStmt->fetch();
                                if ($branchResult) {
                                    $branchName = $branchResult['name'];
                                }
                            }
                            ?>
                            <tr>
                                <td><code><?= htmlspecialchars($record['work_order_number']) ?></code></td>
                                <td><?= htmlspecialchars($workOrderTypeName) ?></td>
                                <td>
                                    <span class="badge bg-<?= $record['department'] === 'connections' ? 'primary' : 'success' ?>">
                                        <?= $record['department'] === 'connections' ? 'التوصيلات' : 'المشاريع' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($branchName) ?></td>
                                <td class="text-end">
                                    <strong><?= number_format((float)($record['estimated_value'] ?? 0), 2) ?></strong> ريال
                                </td>
                                <td class="text-end">
                                    <strong><?= number_format((float)($record['actual_value'] ?? 0), 2) ?></strong> ريال
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = '';
                                    $statusText = '';
                                    $currentStatus = $record['status'] ?? '';

                                    switch ($currentStatus) {
                                        case 'active':
                                            $statusBadge = 'success';
                                            $statusText = 'نشط';
                                            break;
                                        case 'inactive':
                                            $statusBadge = 'secondary';
                                            $statusText = 'غير نشط';
                                            break;
                                        case 'completed':
                                            $statusBadge = 'info';
                                            $statusText = 'مكتمل';
                                            break;
                                        case 'cancelled':
                                            $statusBadge = 'danger';
                                            $statusText = 'ملغي';
                                            break;
                                        default:
                                            $statusBadge = 'warning';
                                            $statusText = 'غير معروف: ' . $currentStatus;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>">
                                        <?= htmlspecialchars($statusText) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Update Records -->
<?php if (!empty($preview['update_records'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0 text-warning">
                <i class="fas fa-edit me-2"></i>
                أوامر عمل للتحديث (<?= count($preview['update_records']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>تنبيه:</strong> هذه أوامر العمل موجودة مسبقاً وسيتم تحديث بياناتها.
            </div>
            <div class="table-responsive">
                <table id="updateRecordsTable" class="table table-sm">
                    <thead>
                        <tr>
                            <th>رقم أمر العمل</th>
                            <th>نوع الأمر الجديد</th>
                            <th>القسم الجديد</th>
                            <th>الفرع الجديد</th>
                            <th>القيمة المقدرة الجديدة</th>
                            <th>القيمة الفعلية الجديدة</th>
                            <th>الحالة الجديدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview['update_records'] as $record): ?>
                            <?php
                            // الحصول على اسم نوع أمر العمل
                            $workOrderTypeName = 'غير محدد';
                            if (!empty($record['work_order_type_id'])) {
                                $typeStmt = $db->prepare("SELECT type_code FROM work_order_types WHERE id = ?");
                                $typeStmt->execute([$record['work_order_type_id']]);
                                $typeResult = $typeStmt->fetch();
                                if ($typeResult) {
                                    $workOrderTypeName = $typeResult['type_code'];
                                }
                            }

                            // الحصول على اسم الفرع
                            $branchName = 'غير محدد';
                            if (!empty($record['branch_id'])) {
                                $branchStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
                                $branchStmt->execute([$record['branch_id']]);
                                $branchResult = $branchStmt->fetch();
                                if ($branchResult) {
                                    $branchName = $branchResult['name'];
                                }
                            }
                            ?>
                            <tr>
                                <td><code><?= htmlspecialchars($record['work_order_number']) ?></code></td>
                                <td><?= htmlspecialchars($workOrderTypeName) ?></td>
                                <td>
                                    <span class="badge bg-<?= $record['department'] === 'connections' ? 'primary' : 'success' ?>">
                                        <?= $record['department'] === 'connections' ? 'التوصيلات' : 'المشاريع' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($branchName) ?></td>
                                <td class="text-end">
                                    <strong><?= number_format((float)($record['estimated_value'] ?? 0), 2) ?></strong> ريال
                                </td>
                                <td class="text-end">
                                    <strong><?= number_format((float)($record['actual_value'] ?? 0), 2) ?></strong> ريال
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = '';
                                    $statusText = '';
                                    $currentStatus = $record['status'] ?? '';

                                    switch ($currentStatus) {
                                        case 'active':
                                            $statusBadge = 'success';
                                            $statusText = 'نشط';
                                            break;
                                        case 'inactive':
                                            $statusBadge = 'secondary';
                                            $statusText = 'غير نشط';
                                            break;
                                        case 'completed':
                                            $statusBadge = 'info';
                                            $statusText = 'مكتمل';
                                            break;
                                        case 'cancelled':
                                            $statusBadge = 'danger';
                                            $statusText = 'ملغي';
                                            break;
                                        default:
                                            $statusBadge = 'warning';
                                            $statusText = 'غير معروف: ' . $currentStatus;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>">
                                        <?= htmlspecialchars($statusText) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Error Records -->
<?php if (!empty($preview['error_records'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0 text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                سجلات خاطئة (<?= count($preview['error_records']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>تنبيه:</strong> هذه السجلات تحتوي على أخطاء ولن يتم استيرادها.
            </div>
            <div class="table-responsive">
                <table id="errorRecordsTable" class="table table-sm">
                    <thead>
                        <tr>
                            <th>رقم الصف</th>
                            <th>رقم أمر العمل</th>
                            <th>سبب الخطأ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview['error_records'] as $record): ?>
                            <tr>
                                <td><?= $record['row_number'] ?></td>
                                <td>
                                    <code><?= htmlspecialchars($record['data']['رقم أمر العمل'] ?? 'غير محدد') ?></code>
                                </td>
                                <td><span class="text-danger"><?= htmlspecialchars($record['error']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>هل تريد المتابعة مع الاستيراد؟</h6>
                <p class="text-muted">
                    سيتم استيراد <?= count($preview['valid_records']) ?> أمر عمل صالح.
                    <?php if (!empty($preview['error_records'])): ?>
                        سيتم تجاهل <?= count($preview['error_records']) ?> سجل خاطئ.
                    <?php endif; ?>
                </p>
                <div class="alert alert-info">
                    <strong>ملاحظة:</strong> سيتم تجاهل أعمدة المستخلصات فقط أثناء الاستيراد. سيتم استيراد النماذج المرفقة.
                </div>
            </div>
            <div class="col-md-6 text-end">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="cancel_import" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </button>
                </form>

                <?php if (!empty($preview['valid_records'])): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="confirm_import" class="btn btn-success"
                                onclick="return confirm('هل أنت متأكد من تأكيد الاستيراد؟\n\nسيتم تجاهل أعمدة المستخلصات فقط.')">
                            <i class="fas fa-check me-2"></i>
                            تأكيد الاستيراد
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-success" disabled>
                        <i class="fas fa-check me-2"></i>
                        لا توجد سجلات صالحة
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable للجداول
    const tableConfig = {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
        },
        pageLength: 25,
        responsive: true,
        order: [[0, 'asc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: 'نسخ',
                className: 'btn btn-secondary btn-sm'
            },
            {
                extend: 'excel',
                text: 'تصدير Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'print',
                text: 'طباعة',
                className: 'btn btn-info btn-sm'
            }
        ]
    };

    // تطبيق DataTable على الجداول
    if ($('#newRecordsTable').length) {
        $('#newRecordsTable').DataTable(tableConfig);
    }

    if ($('#updateRecordsTable').length) {
        $('#updateRecordsTable').DataTable(tableConfig);
    }

    if ($('#errorRecordsTable').length) {
        $('#errorRecordsTable').DataTable(tableConfig);
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
