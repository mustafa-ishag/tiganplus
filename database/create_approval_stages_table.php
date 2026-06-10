<?php
/**
 * إنشاء جدول مراحل الاعتماد
 * Create Approval Stages Table
 */

try {
    // الاتصال بقاعدة البيانات
    $host = 'localhost';
    $dbname = 'etgan_erp';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "🔄 إنشاء جدول مراحل الاعتماد...\n";
    
    // إنشاء جدول مراحل الاعتماد
    $sql = "
    CREATE TABLE IF NOT EXISTS approval_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stage_key VARCHAR(50) NOT NULL UNIQUE COMMENT 'مفتاح المرحلة (technical_support, construction, etc)',
        stage_name VARCHAR(100) NOT NULL COMMENT 'اسم المرحلة',
        stage_description TEXT COMMENT 'وصف المرحلة',
        stage_order INT NOT NULL DEFAULT 1 COMMENT 'ترتيب المرحلة',
        stage_color VARCHAR(20) DEFAULT 'primary' COMMENT 'لون المرحلة (Bootstrap colors)',
        is_active BOOLEAN DEFAULT TRUE COMMENT 'حالة النشاط',
        is_final BOOLEAN DEFAULT FALSE COMMENT 'هل هي المرحلة النهائية',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        INDEX idx_stage_key (stage_key),
        INDEX idx_stage_order (stage_order),
        INDEX idx_active (is_active),
        INDEX idx_final (is_final)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول مراحل الاعتماد';
    ";
    
    $pdo->exec($sql);
    echo "✅ تم إنشاء جدول مراحل الاعتماد بنجاح!\n";
    
    // إدراج البيانات الافتراضية
    echo "\n🔄 إدراج مراحل الاعتماد الافتراضية...\n";
    
    $defaultStages = [
        [
            'stage_key' => 'technical_support',
            'stage_name' => 'المساندة الفنية',
            'stage_description' => 'مرحلة المراجعة الفنية والتحقق من المتطلبات التقنية',
            'stage_order' => 1,
            'stage_color' => 'primary',
            'is_final' => false
        ],
        [
            'stage_key' => 'construction',
            'stage_name' => 'الإنشاءات',
            'stage_description' => 'مرحلة مراجعة الإنشاءات والتحقق من جودة التنفيذ',
            'stage_order' => 2,
            'stage_color' => 'warning',
            'is_final' => false
        ],
        [
            'stage_key' => 'department_manager',
            'stage_name' => 'مدير الدائرة',
            'stage_description' => 'مرحلة اعتماد مدير الدائرة للمستخلص',
            'stage_order' => 3,
            'stage_color' => 'info',
            'is_final' => false
        ],
        [
            'stage_key' => 'administration_manager',
            'stage_name' => 'مدير الإدارة',
            'stage_description' => 'مرحلة اعتماد مدير الإدارة النهائي',
            'stage_order' => 4,
            'stage_color' => 'secondary',
            'is_final' => false
        ],
        [
            'stage_key' => 'taif_finance',
            'stage_name' => 'مالية الطائف',
            'stage_description' => 'مرحلة المراجعة المالية النهائية',
            'stage_order' => 5,
            'stage_color' => 'success',
            'is_final' => false
        ],
        [
            'stage_key' => 'disbursed',
            'stage_name' => 'مصروف',
            'stage_description' => 'تم صرف المستخلص بالكامل',
            'stage_order' => 6,
            'stage_color' => 'dark',
            'is_final' => true
        ]
    ];
    
    $insertSql = "
        INSERT IGNORE INTO approval_stages 
        (stage_key, stage_name, stage_description, stage_order, stage_color, is_final) 
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($insertSql);
    
    foreach ($defaultStages as $stage) {
        try {
            $stmt->execute([
                $stage['stage_key'],
                $stage['stage_name'],
                $stage['stage_description'],
                $stage['stage_order'],
                $stage['stage_color'],
                $stage['is_final'] ? 1 : 0
            ]);
            echo "✅ تم إدراج المرحلة: {$stage['stage_name']}\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                echo "⚠️ المرحلة موجودة مسبقاً: {$stage['stage_name']}\n";
            } else {
                throw $e;
            }
        }
    }
    
    // عرض النتائج
    echo "\n📊 مراحل الاعتماد المتاحة:\n";
    $stages = $pdo->query("SELECT * FROM approval_stages ORDER BY stage_order")->fetchAll();
    foreach ($stages as $stage) {
        $status = $stage['is_active'] ? '✅ نشط' : '❌ غير نشط';
        $final = $stage['is_final'] ? ' (نهائي)' : '';
        echo "- {$stage['stage_order']}. {$stage['stage_name']} ({$stage['stage_key']}) - {$stage['stage_color']} {$status}{$final}\n";
    }
    
    echo "\n✅ تم إنشاء جدول مراحل الاعتماد وإدراج البيانات الافتراضية بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
