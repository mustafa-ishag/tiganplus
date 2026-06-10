<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../bootstrap/app.php';

use EtganERP\Infrastructure\Database\DatabaseConnection;

try {
    // حذف البيانات الموجودة
    $deleteQuery = "DELETE FROM work_order_types";
    DatabaseConnection::execute($deleteQuery);
    
    echo "تم حذف البيانات الموجودة...\n";
    
    // إعادة تعيين AUTO_INCREMENT
    $resetQuery = "ALTER TABLE work_order_types AUTO_INCREMENT = 1";
    DatabaseConnection::execute($resetQuery);
    
    echo "تم إعادة تعيين AUTO_INCREMENT...\n";
    
    // البيانات الافتراضية لأنواع أوامر العمل
    $workOrderTypes = [
        [
            'type_code' => 'MNT',
            'description' => 'أعمال الصيانة الدورية والوقائية للمعدات والمرافق',
            'status' => 'active'
        ],
        [
            'type_code' => 'EMR',
            'description' => 'أعمال الإصلاح الطارئة للأعطال المفاجئة',
            'status' => 'active'
        ],
        [
            'type_code' => 'DEV',
            'description' => 'أعمال التطوير والتحسين للمرافق والخدمات',
            'status' => 'active'
        ],
        [
            'type_code' => 'CON',
            'description' => 'أعمال البناء والإنشاءات الجديدة',
            'status' => 'active'
        ],
        [
            'type_code' => 'ELE',
            'description' => 'أعمال الصيانة والتركيب الكهربائي',
            'status' => 'active'
        ],
        [
            'type_code' => 'PLB',
            'description' => 'أعمال الصيانة والتركيب للأنظمة الصحية',
            'status' => 'active'
        ],
        [
            'type_code' => 'HVAC',
            'description' => 'أعمال صيانة وتركيب أنظمة التكييف والتهوية',
            'status' => 'active'
        ],
        [
            'type_code' => 'CLN',
            'description' => 'أعمال النظافة والتعقيم للمرافق',
            'status' => 'active'
        ]
    ];
    
    // إدراج البيانات
    $insertQuery = "INSERT INTO work_order_types (type_code, description, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";

    $insertedCount = 0;
    foreach ($workOrderTypes as $type) {
        try {
            DatabaseConnection::execute($insertQuery, [
                $type['type_code'],
                $type['description'],
                $type['status']
            ]);
            $insertedCount++;
            echo "تم إدراج: {$type['type_code']}\n";
        } catch (Exception $e) {
            echo "خطأ في إدراج {$type['type_code']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== تم الانتهاء ===\n";
    echo "تم إدراج {$insertedCount} نوع أمر عمل بنجاح\n";
    
    // عرض البيانات المدرجة للتأكد
    echo "\n=== البيانات المدرجة ===\n";
    $selectQuery = "SELECT id, type_code, description, status FROM work_order_types ORDER BY id";
    $results = DatabaseConnection::fetchAll($selectQuery);

    foreach ($results as $row) {
        echo "ID: {$row['id']} | الكود: {$row['type_code']} | الوصف: {$row['description']} | الحالة: {$row['status']}\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nتم تنفيذ العملية بنجاح!\n";
?>
