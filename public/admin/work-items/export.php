<?php
/**
 * تصدير بنود الأعمال إلى Excel
 * Export Work Items to Excel
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

try {
    $db = getDB();
    
    // جلب جميع بنود الأعمال
    $query = "SELECT 
                item_number,
                description,
                unit,
                category,
                subcategory,
                standard_price,
                notes,
                is_active,
                created_at,
                updated_at
              FROM work_items 
              ORDER BY item_number";
    
    $workItems = $db->query($query)->fetchAll();
    
    // إعداد headers للتحميل
    $filename = 'work_items_' . date('Y-m-d_H-i-s') . '.csv';
    
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
        'رقم البند',
        'وصف العمل',
        'وحدة القياس',
        'الفئة',
        'الفئة الفرعية',
        'السعر المعياري',
        'ملاحظات',
        'الحالة',
        'تاريخ الإنشاء',
        'تاريخ التحديث'
    ];
    
    fputcsv($output, $headers);
    
    // كتابة البيانات
    foreach ($workItems as $item) {
        $row = [
            $item['item_number'],
            $item['description'],
            $item['unit'],
            $item['category'] ?? '',
            $item['subcategory'] ?? '',
            number_format($item['standard_price'], 2),
            $item['notes'] ?? '',
            $item['is_active'] ? 'نشط' : 'غير نشط',
            date('Y-m-d H:i:s', strtotime($item['created_at'])),
            date('Y-m-d H:i:s', strtotime($item['updated_at']))
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
