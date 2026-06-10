<?php

declare(strict_types=1);

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
    
    echo "🔄 إضافة حقل الجهة الحالية إلى جدول أوامر العمل...\n";
    
    // إضافة حقل current_entity_id إذا لم يكن موجوداً
    try {
        $pdo->exec("
            ALTER TABLE work_orders 
            ADD COLUMN current_entity_id INT NULL 
            COMMENT 'معرف الجهة الحالية' 
            AFTER department
        ");
        echo "✅ تم إضافة حقل current_entity_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️ حقل current_entity_id موجود مسبقاً\n";
        } else {
            throw $e;
        }
    }
    
    // إضافة المفتاح الخارجي
    try {
        $pdo->exec("
            ALTER TABLE work_orders 
            ADD CONSTRAINT fk_work_orders_current_entity 
            FOREIGN KEY (current_entity_id) 
            REFERENCES current_entities(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        echo "✅ تم إضافة المفتاح الخارجي للجهة الحالية\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint name') !== false) {
            echo "⚠️ المفتاح الخارجي موجود مسبقاً\n";
        } else {
            throw $e;
        }
    }
    
    // إضافة فهرس
    try {
        $pdo->exec("ALTER TABLE work_orders ADD INDEX idx_current_entity (current_entity_id)");
        echo "✅ تم إضافة فهرس current_entity_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ فهرس current_entity_id موجود مسبقاً\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ تم إضافة حقل الجهة الحالية بنجاح!\n";
    
    // عرض البنية المحدثة
    echo "\n📋 بنية جدول work_orders المحدثة:\n";
    $columns = $pdo->query("DESCRIBE work_orders");
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
    
    // تحديث أوامر العمل الموجودة بجهة افتراضية
    echo "\n🔄 تحديث أوامر العمل الموجودة...\n";
    $defaultEntityId = $pdo->query("SELECT id FROM current_entities WHERE code = 'SEC' LIMIT 1")->fetchColumn();
    
    if ($defaultEntityId) {
        $updateCount = $pdo->exec("UPDATE work_orders SET current_entity_id = $defaultEntityId WHERE current_entity_id IS NULL");
        echo "✅ تم تحديث $updateCount أمر عمل بالجهة الافتراضية (شركة الكهرباء السعودية)\n";
    }
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
