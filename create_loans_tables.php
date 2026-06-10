<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();

try {
    $db->beginTransaction();

    // 1. Clients/Contractors table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `inventory_clients` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `type` ENUM('contractor', 'company', 'other') DEFAULT 'contractor',
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(100) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Loans table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `inventory_loans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `loan_number` VARCHAR(50) UNIQUE NOT NULL,
            `type` ENUM('borrow', 'lend') NOT NULL,
            `client_id` INT NOT NULL,
            `receiver_name` VARCHAR(255) NULL,
            `receiver_identity` VARCHAR(100) NULL,
            `status` ENUM('active', 'settled') DEFAULT 'active',
            `notes` TEXT NULL,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `settled_at` TIMESTAMP NULL,
            FOREIGN KEY (`client_id`) REFERENCES `inventory_clients`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Loan Details table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `inventory_loan_details` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `loan_id` INT NOT NULL,
            `material_id` INT NULL,
            `item_number` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (`loan_id`) REFERENCES `inventory_loans`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $db->commit();
    echo "Tables created successfully.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
