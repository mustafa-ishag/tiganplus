<?php
/**
 * إضافة مرحلة "مسودة" إلى جدول مراحل الاعتماد
 * Add "Draft" stage to approval_stages table
 */

echo "🔄 إضافة مرحلة المسودة إلى جدول مراحل الاعتماد...\n\n";

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
    
    echo "✅ تم الاتصال بقاعدة البيانات بنجاح\n\n";
    
    // التحقق من وجود جدول approval_stages
    $tableExists = $pdo->query("SHOW TABLES LIKE 'approval_stages'")->rowCount() > 0;
    if (!$tableExists) {
        echo "❌ جدول approval_stages غير موجود. يرجى تشغيل create_approval_stages_table.php أولاً\n";
        exit(1);
    }
    
    // التحقق من وجود مرحلة المسودة مسبقاً
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_key = 'draft'");
    $stmt->execute();
    $draftExists = $stmt->fetchColumn() > 0;
    
    if ($draftExists) {
        echo "⚠️ مرحلة المسودة موجودة مسبقاً في الجدول\n";
        
        // عرض تفاصيل المرحلة الموجودة
        $stmt = $pdo->prepare("SELECT * FROM approval_stages WHERE stage_key = 'draft'");
        $stmt->execute();
        $existingDraft = $stmt->fetch();
        
        echo "📋 تفاصيل المرحلة الموجودة:\n";
        echo "   - المفتاح: {$existingDraft['stage_key']}\n";
        echo "   - الاسم: {$existingDraft['stage_name']}\n";
        echo "   - الترتيب: {$existingDraft['stage_order']}\n";
        echo "   - اللون: {$existingDraft['stage_color']}\n";
        echo "   - نشطة: " . ($existingDraft['is_active'] ? 'نعم' : 'لا') . "\n";
        echo "   - نهائية: " . ($existingDraft['is_final'] ? 'نعم' : 'لا') . "\n\n";
        
    } else {
        // إضافة مرحلة المسودة
        echo "🔄 إضافة مرحلة المسودة...\n";
        
        $insertSql = "
            INSERT INTO approval_stages 
            (stage_key, stage_name, stage_description, stage_order, stage_color, is_active, is_final, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $pdo->prepare($insertSql);
        $result = $stmt->execute([
            'draft',
            'مسودة',
            'مرحلة المسودة - المستخلص لم يتم تقديمه بعد ويمكن تعديله',
            0, // ترتيب 0 ليكون في البداية
            'secondary',
            1, // نشط
            0  // ليس نهائي
        ]);
        
        if ($result) {
            echo "✅ تم إضافة مرحلة المسودة بنجاح!\n\n";
        } else {
            echo "❌ فشل في إضافة مرحلة المسودة\n";
            exit(1);
        }
    }
    
    // تحديث ترتيب المراحل الأخرى لتبدأ من 1
    echo "🔄 تحديث ترتيب المراحل الأخرى...\n";
    
    $updateOrderSql = "
        UPDATE approval_stages 
        SET stage_order = stage_order + 1, updated_at = NOW()
        WHERE stage_key != 'draft' AND stage_order >= 0
    ";
    
    $pdo->exec($updateOrderSql);
    echo "✅ تم تحديث ترتيب المراحل\n\n";
    
    // عرض جميع المراحل بعد التحديث
    echo "📊 مراحل الاعتماد بعد التحديث:\n";
    $stages = $pdo->query("SELECT * FROM approval_stages ORDER BY stage_order")->fetchAll();
    
    foreach ($stages as $stage) {
        $status = $stage['is_active'] ? '✅ نشط' : '❌ غير نشط';
        $final = $stage['is_final'] ? ' (نهائي)' : '';
        echo "- {$stage['stage_order']}. {$stage['stage_name']} ({$stage['stage_key']}) - {$stage['stage_color']} {$status}{$final}\n";
    }
    
    echo "\n✅ تم إضافة مرحلة المسودة وتحديث ترتيب المراحل بنجاح!\n";
    echo "\n📝 ملاحظات:\n";
    echo "- مرحلة المسودة لها ترتيب 0 وتظهر في البداية\n";
    echo "- يمكن تعديل المستخلصات في مرحلة المسودة\n";
    echo "- عند الانتقال من المسودة إلى مرحلة أخرى، يتم تحديد تاريخ التقديم\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
