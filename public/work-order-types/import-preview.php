<?php

declare(strict_types=1);

/**
 * معاينة استيراد أنواع أوامر العمل
 * Work Order Types Import Preview
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'معاينة استيراد أنواع أوامر العمل';
$currentPage = 'work-order-types';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أنواع أوامر العمل', 'url' => 'work-order-types/index.php'],
    ['title' => 'استيراد البيانات', 'url' => 'work-order-types/import.php'],
    ['title' => 'معاينة البيانات', 'url' => 'work-order-types/import-preview.php']
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
            INSERT INTO work_order_type_import_export_logs 
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
                $upsertStmt = $db->prepare("
                    INSERT INTO work_order_types (type_code, description, status, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        status = VALUES(status),
                        updated_at = NOW()
                ");

                if ($upsertStmt->execute([
                    $record['type_code'],
                    $record['description'],
                    $record['status']
                ])) {
                    $successCount++;
                } else {
                    $errors[] = "خطأ في معالجة النوع: {$record['type_code']}";
                    $errorCount++;
                }
            }
            
            $db->commit();
            
            // تحديث سجل العملية
            $updateLogStmt = $db->prepare("
                UPDATE work_order_type_import_export_logs 
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
            
            $_SESSION['success'] = "تم استيراد {$successCount} نوع أمر عمل بنجاح";
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
                    UPDATE work_order_type_import_export_logs 
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

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>
            معاينة استيراد أنواع أوامر العمل
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
                <div class="text-muted">سجلات جديدة</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="h4 text-warning"><?= count($preview['update_records']) ?></div>
                <div class="text-muted">سجلات للتحديث</div>
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
                سجلات جديدة (<?= count($preview['new_records']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>كود النوع</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview['new_records'], 0, 10) as $record): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($record['type_code']) ?></code></td>
                                <td><?= htmlspecialchars($record['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $record['status'] === 'active' ? 'success' : 'warning' ?>">
                                        <?= $record['status'] === 'active' ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($preview['new_records']) > 10): ?>
                            <tr>
                                <td colspan="3" class="text-muted text-center">
                                    ... و <?= count($preview['new_records']) - 10 ?> سجل آخر
                                </td>
                            </tr>
                        <?php endif; ?>
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
                سجلات للتحديث (<?= count($preview['update_records']) ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>كود النوع</th>
                            <th>الوصف الجديد</th>
                            <th>الحالة الجديدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview['update_records'], 0, 10) as $record): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($record['type_code']) ?></code></td>
                                <td><?= htmlspecialchars($record['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $record['status'] === 'active' ? 'success' : 'warning' ?>">
                                        <?= $record['status'] === 'active' ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($preview['update_records']) > 10): ?>
                            <tr>
                                <td colspan="3" class="text-muted text-center">
                                    ... و <?= count($preview['update_records']) - 10 ?> سجل آخر
                                </td>
                            </tr>
                        <?php endif; ?>
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
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>رقم الصف</th>
                            <th>البيانات</th>
                            <th>سبب الخطأ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview['error_records'], 0, 10) as $record): ?>
                            <tr>
                                <td><?= $record['row_number'] ?></td>
                                <td><small><?= htmlspecialchars(json_encode($record['data'], JSON_UNESCAPED_UNICODE)) ?></small></td>
                                <td><span class="text-danger"><?= htmlspecialchars($record['error']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($preview['error_records']) > 10): ?>
                            <tr>
                                <td colspan="3" class="text-muted text-center">
                                    ... و <?= count($preview['error_records']) - 10 ?> خطأ آخر
                                </td>
                            </tr>
                        <?php endif; ?>
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
                    سيتم استيراد <?= count($preview['valid_records']) ?> سجل صالح.
                    <?php if (!empty($preview['error_records'])): ?>
                        سيتم تجاهل <?= count($preview['error_records']) ?> سجل خاطئ.
                    <?php endif; ?>
                </p>
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
                                onclick="return confirm('هل أنت متأكد من تأكيد الاستيراد؟')">
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

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
