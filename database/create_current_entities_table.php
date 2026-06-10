<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

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
    
    // إنشاء جدول الجهات الحالية
    $sql = "
    CREATE TABLE IF NOT EXISTS current_entities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE COMMENT 'اسم الجهة',
        code VARCHAR(10) UNIQUE COMMENT 'كود الجهة (اختياري)',
        description TEXT COMMENT 'وصف الجهة',
        is_active BOOLEAN DEFAULT TRUE COMMENT 'حالة النشاط',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        INDEX idx_name (name),
        INDEX idx_code (code),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول الجهات الحالية';
    ";
    
    $pdo->exec($sql);
    echo "✅ تم إنشاء جدول الجهات الحالية بنجاح!\n";
    
    // إدراج بيانات افتراضية
    $defaultEntities = [
        ['name' => 'شركة الكهرباء السعودية', 'code' => 'SEC', 'description' => 'الشركة السعودية للكهرباء'],
        ['name' => 'شركة الاتصالات السعودية', 'code' => 'STC', 'description' => 'شركة الاتصالات السعودية'],
        ['name' => 'أرامكو السعودية', 'code' => 'ARAMCO', 'description' => 'شركة أرامكو السعودية'],
        ['name' => 'وزارة النقل', 'code' => 'MOT', 'description' => 'وزارة النقل والخدمات اللوجستية'],
        ['name' => 'أمانة منطقة مكة المكرمة', 'code' => 'MAKKAH', 'description' => 'أمانة منطقة مكة المكرمة'],
        ['name' => 'شركة المياه الوطنية', 'code' => 'NWC', 'description' => 'شركة المياه الوطنية'],
        ['name' => 'وزارة الإسكان', 'code' => 'MOH', 'description' => 'وزارة الإسكان'],
        ['name' => 'هيئة تطوير المدن الصناعية', 'code' => 'MODON', 'description' => 'هيئة تطوير المدن الصناعية ومناطق التقنية'],
        ['name' => 'شركة سابك', 'code' => 'SABIC', 'description' => 'الشركة السعودية للصناعات الأساسية'],
        ['name' => 'هيئة الطرق', 'code' => 'ROADS', 'description' => 'الهيئة العامة للطرق']
    ];
    
    $insertSql = "INSERT IGNORE INTO current_entities (name, code, description) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($insertSql);
    
    foreach ($defaultEntities as $entity) {
        try {
            $stmt->execute([$entity['name'], $entity['code'], $entity['description']]);
            echo "✅ تم إدراج الجهة: {$entity['name']}\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                echo "⚠️ الجهة موجودة مسبقاً: {$entity['name']}\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n✅ تم إنشاء جدول الجهات الحالية وإدراج البيانات الافتراضية بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
