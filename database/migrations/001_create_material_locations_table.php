<?php
/**
 * Migration: Create material_locations table
 * الهدف: إنشاء جدول وسيط لربط المواد بالمواقع
 * 
 * هذا الجدول يسمح بتخزين مادة واحدة في عدة مواقع مع تتبع دقيق للكميات
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🔄 بدء الترحيل: إنشاء جدول material_locations...\n\n";
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // 1. إنشاء جدول material_locations
    echo "1. إنشاء جدول material_locations...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS material_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL COMMENT 'معرف المادة',
        location_id INT NOT NULL COMMENT 'معرف الموقع',
        quantity DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'الكمية المتوفرة',
        reserved_quantity DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'الكمية المحجوزة',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
        UNIQUE KEY unique_material_location (material_id, location_id),
        INDEX idx_material (material_id),
        INDEX idx_location (location_id),
        INDEX idx_quantity (quantity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ربط المواد بالمواقع';
    ");
    echo "✅ تم إنشاء جدول material_locations\n";
    
    // 2. إضافة عمود reserved_quantity إلى جدول materials إذا لم يكن موجوداً
    echo "\n2. إضافة عمود reserved_quantity إلى جدول materials...\n";
    $checkColumn = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'materials' 
        AND COLUMN_NAME = 'reserved_quantity'
    ");
    
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("
        ALTER TABLE materials 
        ADD COLUMN reserved_quantity DECIMAL(10,3) NOT NULL DEFAULT 0 
        COMMENT 'الكمية المحجوزة' 
        AFTER current_stock
        ");
        echo "✅ تم إضافة عمود reserved_quantity\n";
    } else {
        echo "⚠️ العمود reserved_quantity موجود بالفعل\n";
    }
    
    // 3. ترحيل البيانات من materials.location إلى material_locations
    echo "\n3. ترحيل البيانات من materials.location إلى material_locations...\n";
    
    // الحصول على جميع المواد التي لها موقع
    $materials = $pdo->query("
        SELECT id, location 
        FROM materials 
        WHERE location IS NOT NULL AND location != ''
    ");
    
    $migratedCount = 0;
    while ($material = $materials->fetch(PDO::FETCH_ASSOC)) {
        // البحث عن الموقع في جدول inventory_locations
        $locationStmt = $pdo->prepare("
            SELECT id 
            FROM inventory_locations 
            WHERE location_code = ? OR location_name = ?
            LIMIT 1
        ");
        $locationStmt->execute([$material['location'], $material['location']]);
        $location = $locationStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($location) {
            // إدراج في جدول material_locations
            $insertStmt = $pdo->prepare("
                INSERT INTO material_locations (material_id, location_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity
            ");
            
            // الحصول على الكمية الحالية
            $materialData = $pdo->prepare("SELECT current_stock FROM materials WHERE id = ?");
            $materialData->execute([$material['id']]);
            $stock = $materialData->fetch(PDO::FETCH_ASSOC);
            
            $insertStmt->execute([
                $material['id'],
                $location['id'],
                $stock['current_stock'] ?? 0
            ]);
            
            $migratedCount++;
        }
    }
    
    echo "✅ تم ترحيل $migratedCount مادة\n";
    
    // 4. إنشاء جدول تاريخ المخزون (اختياري)
    echo "\n4. إنشاء جدول stock_history...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS stock_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL COMMENT 'معرف المادة',
        location_id INT COMMENT 'معرف الموقع',
        transaction_type ENUM('incoming', 'outgoing', 'transfer', 'adjustment', 'reservation', 'release') 
            NOT NULL COMMENT 'نوع العملية',
        quantity_change DECIMAL(10,3) NOT NULL COMMENT 'التغيير في الكمية',
        previous_quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية السابقة',
        new_quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية الجديدة',
        reference_id INT COMMENT 'معرف المستند المرجعي',
        reference_type VARCHAR(50) COMMENT 'نوع المستند المرجعي',
        notes TEXT COMMENT 'ملاحظات',
        created_by INT COMMENT 'منشئ العملية',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
        INDEX idx_material (material_id),
        INDEX idx_location (location_id),
        INDEX idx_transaction_type (transaction_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تاريخ حركات المخزون';
    ");
    echo "✅ تم إنشاء جدول stock_history\n";
    
    // 5. إنشاء جدول stock_reservations
    echo "\n5. إنشاء جدول stock_reservations...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS stock_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL COMMENT 'معرف المادة',
        location_id INT NOT NULL COMMENT 'معرف الموقع',
        quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية المحجوزة',
        reference_id INT NOT NULL COMMENT 'معرف الطلب أو المستند',
        reference_type VARCHAR(50) NOT NULL COMMENT 'نوع المستند (material_request, work_order)',
        status ENUM('active', 'released', 'consumed') DEFAULT 'active' COMMENT 'حالة الحجز',
        created_by INT COMMENT 'منشئ الحجز',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        released_at TIMESTAMP NULL COMMENT 'تاريخ إلغاء الحجز',
        
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
        INDEX idx_material (material_id),
        INDEX idx_location (location_id),
        INDEX idx_reference (reference_id, reference_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حجوزات المخزون';
    ");
    echo "✅ تم إنشاء جدول stock_reservations\n";
    
    // تأكيد المعاملة
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo "\n✅ تم إكمال الترحيل بنجاح!\n";
    echo "📊 الملخص:\n";
    echo "  - جدول material_locations: تم إنشاؤه\n";
    echo "  - عمود reserved_quantity: تم إضافته\n";
    echo "  - البيانات المرحلة: $migratedCount مادة\n";
    echo "  - جدول stock_history: تم إنشاؤه\n";
    echo "  - جدول stock_reservations: تم إنشاؤه\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ في الترحيل: " . $e->getMessage() . "\n";
    exit(1);
}
?>

