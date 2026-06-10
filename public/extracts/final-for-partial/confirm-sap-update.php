<?php
/**
 * صفحة تأكيد تحديث SAP للمستخلصات النهائية للجزئية
 * Confirm SAP Update for Final For Partial Extracts
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

// التحقق من وجود بيانات المعاينة
if (!isset($_SESSION['sap_preview_data'])) {
    header('Location: update-sap-entry-number.php');
    exit();
}

$previewData = $_SESSION['sap_preview_data'];

// الاتصال بقاعدة البيانات
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// تنفيذ التحديث
$updatedCount = 0;
$errors = [];

$db->beginTransaction();

try {
    $updateStmt = $db->prepare("UPDATE final_for_partial_extracts SET entry_sheet_number = ?, po_number = ?, disbursement_date = ? WHERE id = ?");

    foreach ($previewData['valid_records'] as $record) {
        $updateStmt->execute([
            $record['entry_sheet_number'],
            $record['po_number'],
            $record['disbursement_date'],
            $record['extract_id']
        ]);
        $updatedCount++;
    }
    
    $db->commit();
    
    // حفظ النتائج في الجلسة
    $_SESSION['sap_update_result'] = [
        'success' => true,
        'updated_count' => $updatedCount,
        'updated_records' => $previewData['valid_records'],
        'errors' => $previewData['errors'],
        'skipped_count' => $previewData['skipped_count']
    ];
    
    // حذف بيانات المعاينة
    unset($_SESSION['sap_preview_data']);
    
    // إعادة التوجيه إلى صفحة النتائج
    header('Location: update-sap-entry-number.php?success=1');
    exit();
    
} catch (Exception $e) {
    $db->rollBack();
    
    $_SESSION['sap_update_result'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    header('Location: update-sap-entry-number.php?error=1');
    exit();
}

