-- نظام تِقان لإدارة أعمال شركة المقاولات
-- Tiqan ERP System Database Schema
-- Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS `tiqan_erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tiqan_erp`;

-- --------------------------------------------------------

-- جدول الفروع
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(3) NOT NULL,
  `address` text,
  `phone` varchar(20),
  `fax` varchar(20),
  `email` varchar(100),
  `manager_name` varchar(100),
  `manager_phone` varchar(20),
  `status` enum('active','inactive') DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول أنواع أوامر العمل
CREATE TABLE `work_order_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(3) NOT NULL,
  `description` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_code` (`type_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول الجهات الحالية
CREATE TABLE `current_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول أوامر العمل
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_number` varchar(9) NOT NULL,
  `work_order_type_id` int(11) NOT NULL,
  `department` enum('connections','projects') NOT NULL,
  `current_entity_id` int(11),
  `branch_id` int(11) NOT NULL,
  `assignment_date` date,
  `receipt_date` date,
  `estimated_value` decimal(15,2) DEFAULT 0.00,
  `actual_value` decimal(15,2) DEFAULT 0.00,
  `disbursement_status` enum('none','completed','disbursement','return','disbursement_return_completed') DEFAULT 'none',
  `notes` text,
  `extract_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_number` (`work_order_number`),
  KEY `fk_work_orders_type` (`work_order_type_id`),
  KEY `fk_work_orders_entity` (`current_entity_id`),
  KEY `fk_work_orders_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_department` (`department`),
  CONSTRAINT `fk_work_orders_type` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_work_orders_entity` FOREIGN KEY (`current_entity_id`) REFERENCES `current_entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_work_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول النماذج المرفقة لأوامر العمل
CREATE TABLE `work_order_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `form_type` enum('scraping','precise_drilling','demolition','f1','completion_certificate') NOT NULL,
  `status` enum('attached','not_attached','not_applicable') DEFAULT 'not_attached',
  `file_path` varchar(500),
  `original_filename` varchar(255),
  `file_size` int(11),
  `completion_certificate_status` enum('','accepted','rejected','confirmed') DEFAULT '',
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_attachments_work_order` (`work_order_id`),
  KEY `idx_form_type` (`form_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_attachments_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول الأدوار
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول الصلاحيات
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `module` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول ربط الأدوار بالصلاحيات
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول المستخدمين
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100),
  `phone` varchar(20),
  `department` varchar(100),
  `branch_id` int(11) DEFAULT NULL,
  `position` varchar(100),
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول محاولات تسجيل الدخول
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول سجل العمليات
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_audit_logs_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- إدراج البيانات الافتراضية

-- إدراج الفروع الافتراضية
INSERT INTO `branches` (`name`, `code`, `address`, `phone`, `status`) VALUES
('الطائف', 'TAF', 'الطائف، المملكة العربية السعودية', '012-7123456', 'active'),
('رنية', 'RAN', 'رنية، المملكة العربية السعودية', '012-7654321', 'active');

-- إدراج الأدوار الافتراضية
INSERT INTO `roles` (`name`, `description`) VALUES
('super_admin', 'مدير النظام'),
('admin_manager', 'مدير الإدارة'),
('department_manager', 'مدير الدائرة'),
('branch_manager', 'مدير الفرع'),
('technical_support', 'موظف المساندة الفنية'),
('construction_employee', 'موظف الإنشاءات'),
('finance_employee', 'موظف المالية'),
('regular_user', 'مستخدم عادي');

-- إدراج الصلاحيات الافتراضية
INSERT INTO `permissions` (`name`, `description`, `module`) VALUES
-- صلاحيات الفروع
('view_branches', 'عرض الفروع', 'branches'),
('add_branches', 'إضافة فروع', 'branches'),
('edit_branches', 'تعديل الفروع', 'branches'),
('delete_branches', 'حذف الفروع', 'branches'),
('view_all_branches', 'عرض جميع الفروع', 'branches'),

-- صلاحيات أوامر العمل
('view_work_orders', 'عرض أوامر العمل', 'work_orders'),
('add_work_orders', 'إضافة أوامر العمل', 'work_orders'),
('edit_work_orders', 'تعديل أوامر العمل', 'work_orders'),
('delete_work_orders', 'حذف أوامر العمل', 'work_orders'),

-- صلاحيات أنواع أوامر العمل
('view_work_order_types', 'عرض أنواع أوامر العمل', 'work_order_types'),
('add_work_order_types', 'إضافة أنواع أوامر العمل', 'work_order_types'),
('edit_work_order_types', 'تعديل أنواع أوامر العمل', 'work_order_types'),
('delete_work_order_types', 'حذف أنواع أوامر العمل', 'work_order_types'),

-- صلاحيات المستخلصات
('view_extracts', 'عرض المستخلصات', 'extracts'),
('create_extracts', 'إنشاء المستخلصات', 'extracts'),
('edit_extracts', 'تعديل المستخلصات', 'extracts'),
('delete_extracts', 'حذف المستخلصات', 'extracts'),
('approve_extracts', 'اعتماد المستخلصات', 'extracts'),

-- صلاحيات المستخدمين
('view_users', 'عرض المستخدمين', 'users'),
('add_users', 'إضافة مستخدمين', 'users'),
('edit_users', 'تعديل المستخدمين', 'users'),
('delete_users', 'حذف المستخدمين', 'users'),
('manage_permissions', 'إدارة الصلاحيات', 'users'),

-- صلاحيات التقارير
('view_reports', 'عرض التقارير', 'reports'),
('export_reports', 'تصدير التقارير', 'reports'),

-- صلاحيات الإعدادات
('view_settings', 'عرض الإعدادات', 'settings'),
('edit_settings', 'تعديل الإعدادات', 'settings'),
('backup_database', 'النسخ الاحتياطي', 'settings');

-- إنشاء مستخدم مدير النظام الافتراضي
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role_id`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@etgan.com', 1, 'active');
-- كلمة المرور الافتراضية: password

-- --------------------------------------------------------

-- جدول المستخلصات الجزئية
CREATE TABLE `extracts_partial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extract_number` varchar(10) NOT NULL,
  `extract_date` date NOT NULL,
  `invoice_number` varchar(100),
  `total_before_tax` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extract_number` (`extract_number`),
  KEY `fk_extracts_partial_user` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_extract_date` (`extract_date`),
  CONSTRAINT `fk_extracts_partial_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول المستخلصات النهائية للجزئية
CREATE TABLE `extracts_final_partial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partial_extract_id` int(11) NOT NULL,
  `extract_number` varchar(10) NOT NULL,
  `invoice_number` varchar(100),
  `total_before_tax` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `partial_tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_penalties` decimal(15,2) DEFAULT 0.00,
  `total_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extract_number` (`extract_number`),
  UNIQUE KEY `partial_extract_id` (`partial_extract_id`),
  KEY `fk_extracts_final_partial_user` (`created_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_extracts_final_partial_extract` FOREIGN KEY (`partial_extract_id`) REFERENCES `extracts_partial` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_extracts_final_partial_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول المستخلصات النهائية العادية
CREATE TABLE `extracts_final_regular` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extract_number` varchar(10) NOT NULL,
  `extract_date` date NOT NULL,
  `invoice_number` varchar(100),
  `total_before_tax` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_penalties` decimal(15,2) DEFAULT 0.00,
  `total_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extract_number` (`extract_number`),
  KEY `fk_extracts_final_regular_user` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_extract_date` (`extract_date`),
  CONSTRAINT `fk_extracts_final_regular_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول ربط المستخلصات بأوامر العمل
CREATE TABLE `extract_work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extract_type` enum('partial','final_partial','final_regular') NOT NULL,
  `extract_id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `completion_date` date NOT NULL,
  `extract_value` decimal(15,2) NOT NULL,
  `penalty_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_extract_work_orders_work_order` (`work_order_id`),
  KEY `idx_extract_type_id` (`extract_type`,`extract_id`),
  CONSTRAINT `fk_extract_work_orders_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- جدول مراحل اعتماد المستخلصات
CREATE TABLE `extract_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extract_type` enum('partial','final_partial','final_regular') NOT NULL,
  `extract_id` int(11) NOT NULL,
  `stage` enum('technical_support','construction','department_manager','administration_manager','finance','disbursed') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extract_stage` (`extract_type`,`extract_id`,`stage`),
  KEY `fk_extract_approvals_user` (`approved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_stage` (`stage`),
  CONSTRAINT `fk_extract_approvals_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- إدراج أنواع أوامر العمل الافتراضية
INSERT INTO `work_order_types` (`type_code`, `description`) VALUES
('001', 'توصيلات جديدة'),
('002', 'صيانة شبكات'),
('003', 'مشاريع إنشائية'),
('004', 'أعمال حفر'),
('005', 'أعمال كهربائية');

-- إدراج الجهات الحالية الافتراضية
INSERT INTO `current_entities` (`name`) VALUES
('شركة الكهرباء السعودية'),
('أمانة الطائف'),
('أمانة رنية'),
('وزارة الشؤون البلدية'),
('شركة المياه الوطنية');

-- ربط مدير النظام بجميع الصلاحيات
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

COMMIT;
