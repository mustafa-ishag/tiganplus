-- نظام تِقان - قاعدة البيانات
-- Tiqan ERP System Database Schema

USE tiqan_erp;

-- جدول الأدوار
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الفروع
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول أنواع أوامر العمل
CREATE TABLE IF NOT EXISTS work_order_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المستخدمين
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    branch_id INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج البيانات الافتراضية

-- إدراج الأدوار
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'مدير النظام', 'مدير النظام الرئيسي - صلاحيات كاملة'),
(2, 'مدير الفرع', 'مدير الفرع - صلاحيات إدارة الفرع'),
(3, 'موظف الفرع', 'موظف الفرع - صلاحيات محدودة');

-- إدراج الفروع
INSERT IGNORE INTO branches (id, code, name, description) VALUES
(1, 'TAF', 'فرع الطائف', 'الفرع الرئيسي في الطائف'),
(2, 'RAN', 'فرع رنية', 'فرع رنية');

-- إدراج مستخدم مدير النظام
INSERT IGNORE INTO users (id, username, full_name, email, password, role_id, status) VALUES
(1, 'admin', 'مدير النظام', 'admin@etgan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active');

-- إدراج البيانات الافتراضية لأنواع أوامر العمل
INSERT IGNORE INTO work_order_types (id, name, description, status) VALUES
(1, 'صيانة دورية', 'أعمال الصيانة الدورية والوقائية للمعدات والمرافق', 'active'),
(2, 'إصلاح طارئ', 'أعمال الإصلاح الطارئة للأعطال المفاجئة', 'active'),
(3, 'تطوير وتحسين', 'أعمال التطوير والتحسين للمرافق والخدمات', 'active'),
(4, 'أعمال إنشائية', 'أعمال البناء والإنشاءات الجديدة', 'active'),
(5, 'أعمال كهربائية', 'أعمال الصيانة والتركيب الكهربائي', 'active');

-- تحديث AUTO_INCREMENT
ALTER TABLE roles AUTO_INCREMENT = 4;
ALTER TABLE branches AUTO_INCREMENT = 3;
ALTER TABLE users AUTO_INCREMENT = 2;
