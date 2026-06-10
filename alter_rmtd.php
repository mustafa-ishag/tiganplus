<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = getDB();
    
    $sql = "ALTER TABLE removed_material_transaction_details
            ADD COLUMN item_type VARCHAR(50) DEFAULT 'تشغيلي' AFTER material_id,
            ADD COLUMN status VARCHAR(50) DEFAULT 'تخريد' AFTER item_type,
            ADD COLUMN disposal_reason VARCHAR(255) NULL AFTER status,
            ADD COLUMN material_condition VARCHAR(255) NULL AFTER disposal_reason,
            ADD COLUMN remarks TEXT NULL AFTER material_condition,
            ADD COLUMN functional_location VARCHAR(255) NULL AFTER remarks,
            ADD COLUMN equipment VARCHAR(255) NULL AFTER functional_location,
            ADD COLUMN capacity_kva VARCHAR(255) NULL AFTER equipment,
            ADD COLUMN manufacturer VARCHAR(255) NULL AFTER capacity_kva,
            ADD COLUMN prim_sec_volt VARCHAR(255) NULL AFTER manufacturer,
            ADD COLUMN manufacture_year INT NULL AFTER prim_sec_volt,
            ADD COLUMN serial_number VARCHAR(255) NULL AFTER manufacture_year,
            ADD COLUMN images TEXT NULL AFTER serial_number;";
            
    $db->exec($sql);
    echo "Columns added successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
