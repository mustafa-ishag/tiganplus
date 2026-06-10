<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    notification_type ENUM('whatsapp_personal', 'whatsapp_group', 'email') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "Table 'notification_settings' created successfully.\n";

    // Insert some default data if empty
    $stmt = $db->query("SELECT COUNT(*) FROM notification_settings");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO notification_settings (event_name, notification_type, recipient, name, is_active) VALUES 
            ('material_request_submit', 'whatsapp_group', '120363293762794243@g.us', 'مجموعة المستودع', 1),
            ('material_request_submit', 'whatsapp_personal', '966565541160', 'مدير النظام (مصطفى)', 1)");
        echo "Inserted default records.\n";
    }
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
