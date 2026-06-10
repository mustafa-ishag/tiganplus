<?php

declare(strict_types=1);

/**
 * تصدير أنواع أوامر العمل (نفس طريقة بنود الأعمال)
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

try {
    $db = getDB();
    
    // الحصول على المعاملات
    $status = $_GET['status'] ?? 'all';
    
    // بناء الاستعلام
    $query = "SELECT type_code, description, status FROM work_order_types";
    
    if ($status !== 'all') {
        $query .= " WHERE status = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status]);
        $workOrderTypes = $stmt->fetchAll();
    } else {
        $workOrderTypes = $db->query($query)->fetchAll();
    }
    
    // إعداد headers للتحميل
    $filename = 'work_order_types_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // إضافة BOM للدعم العربي في Excel
    echo "\xEF\xBB\xBF";
    
    // فتح output stream
    $output = fopen('php://output', 'w');
    
    // كتابة العناوين
    $headers = [
        'كود النوع',
        'الوصف',
        'الحالة'
    ];
    
    fputcsv($output, $headers);
    
    // كتابة البيانات
    foreach ($workOrderTypes as $item) {
        $row = [
            $item['type_code'],
            $item['description'],
            $item['status'] === 'active' ? 'نشط' : 'غير نشط'
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    // في حالة الخطأ، إعادة توجيه مع رسالة خطأ
    $_SESSION['error'] = 'خطأ في تصدير البيانات: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
