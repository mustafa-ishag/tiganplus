<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();

$permissions = [
    ['name' => 'inventory_clients_view', 'description' => 'عرض قائمة العملاء والمقاولين', 'category' => 'inventory'],
    ['name' => 'inventory_clients_create', 'description' => 'إضافة عميل/مقاول جديد', 'category' => 'inventory'],
    ['name' => 'inventory_clients_edit', 'description' => 'تعديل بيانات عميل/مقاول', 'category' => 'inventory'],
    ['name' => 'inventory_loans_view', 'description' => 'عرض السلف وتفاصيلها', 'category' => 'inventory'],
    ['name' => 'inventory_loans_create', 'description' => 'إنشاء سلفة جديدة', 'category' => 'inventory'],
    ['name' => 'inventory_loans_edit', 'description' => 'تعديل حالة السلفة (مخالصة)', 'category' => 'inventory'],
];

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT IGNORE INTO permissions (name, description, category) VALUES (?, ?, ?)");

    foreach ($permissions as $perm) {
        $stmt->execute([$perm['name'], $perm['description'], $perm['category']]);
    }

    $db->commit();
    echo "Permissions added successfully.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error adding permissions: " . $e->getMessage() . "\n";
}
