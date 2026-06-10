<?php
/**
 * إنشاء جدول إعدادات الفواتير الضريبية
 * Create Invoice Settings Table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "بدء إنشاء جدول إعدادات الفواتير الضريبية...\n";
    
    // إنشاء جدول إعدادات الفواتير
    $createInvoiceSettingsTable = "
    CREATE TABLE IF NOT EXISTS invoice_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- بيانات المورد (الشركة)
        supplier_name VARCHAR(255) NOT NULL COMMENT 'اسم الشركة المورد',
        supplier_address TEXT NOT NULL COMMENT 'عنوان الشركة',
        supplier_tax_number VARCHAR(50) NOT NULL COMMENT 'الرقم الضريبي للشركة',
        supplier_logo_path VARCHAR(500) NULL COMMENT 'مسار شعار الشركة',
        
        -- بيانات العميل
        client_name VARCHAR(255) NOT NULL COMMENT 'اسم العميل',
        client_address TEXT NOT NULL COMMENT 'عنوان العميل',
        client_tax_number VARCHAR(50) NOT NULL COMMENT 'الرقم الضريبي للعميل',
        
        -- بيانات العقد
        contract_number VARCHAR(100) NOT NULL COMMENT 'رقم العقد',
        contract_date DATE NULL COMMENT 'تاريخ العقد',
        
        -- إعدادات الفاتورة
        invoice_title VARCHAR(255) NOT NULL DEFAULT 'فاتورة ضريبة' COMMENT 'عنوان الفاتورة',
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00 COMMENT 'نسبة الضريبة',
        currency VARCHAR(10) NOT NULL DEFAULT 'ريال سعودي' COMMENT 'العملة',
        
        -- إعدادات التصميم
        header_color VARCHAR(7) DEFAULT '#2c3e50' COMMENT 'لون رأس الفاتورة',
        accent_color VARCHAR(7) DEFAULT '#3498db' COMMENT 'اللون المميز',
        
        -- معلومات النظام
        is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'هل الإعدادات نشطة',
        created_by INT NOT NULL COMMENT 'منشئ الإعدادات',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        -- Foreign Keys
        FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_is_active (is_active),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول إعدادات الفواتير الضريبية';
    ";
    
    $db->exec($createInvoiceSettingsTable);
    echo "✅ تم إنشاء جدول invoice_settings بنجاح\n";
    
    // إدراج إعدادات افتراضية
    $insertDefaultSettings = "
    INSERT IGNORE INTO invoice_settings (
        supplier_name,
        supplier_address,
        supplier_tax_number,
        client_name,
        client_address,
        client_tax_number,
        contract_number,
        invoice_title,
        tax_rate,
        currency,
        created_by
    ) VALUES (
        'شركة إتقان للمقاولات',
        'الرياض، المملكة العربية السعودية',
        '123456789012345',
        'شركة العميل',
        'عنوان العميل',
        '987654321098765',
        'CON-2024-001',
        'فاتورة ضريبة',
        15.00,
        'ريال سعودي',
        1
    )";
    
    $db->exec($insertDefaultSettings);
    echo "✅ تم إدراج الإعدادات الافتراضية بنجاح\n";
    
    echo "\n🎉 تم إنشاء جدول إعدادات الفواتير الضريبية بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ في إنشاء جدول إعدادات الفواتير: " . $e->getMessage() . "\n";
    exit(1);
}
?>
