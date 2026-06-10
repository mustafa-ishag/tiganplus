<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين القيم الافتراضية
$pageTitle = $pageTitle ?? 'نظام تِقان';
$currentPage = $currentPage ?? '';
$breadcrumbs = $breadcrumbs ?? [];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// جلب معلومات المستخدم
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ' . path('auth/login.php'));
    exit();
}

if (!function_exists('hasAnyMenuPermission')) {
    function hasAnyMenuPermission($permissions)
    {
        foreach ($permissions as $perm) {
            if (hasPermission($perm))
                return true;
        }
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - نظام تِقان</title>

    <!-- Bootstrap CSS RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #1e3d72;
            --accent-color: #f8f9fa;
            --text-color: #333;
            --border-color: #dee2e6;
            --sidebar-width: 280px;
        }

        * {
            font-family: 'Cairo', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: var(--text-color);
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            transform: translateX(100%);
        }

        .sidebar-brand {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar-brand h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.4rem;
        }

        .sidebar-brand small {
            opacity: 0.8;
            font-size: 0.85rem;
        }

        .sidebar-nav {
            padding: 1.5rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.5rem;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            font-weight: 500;
            border-radius: 0;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(-5px);
        }

        .nav-link.active,
        .nav-dropdown.open>.nav-link {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
            font-weight: 700;
        }

        .nav-link:hover::before {
            transform: scaleY(1);
        }

        .nav-link.active::before,
        .nav-dropdown.open>.nav-link::before {
            transform: scaleY(1);
            background: #ffc107;
            width: 4px;
        }

        .nav-link.active i,
        .nav-dropdown.open>.nav-link i {
            color: #ffc107;
        }

        .nav-link i {
            width: 20px;
            margin-left: 0.875rem;
            font-size: 1rem;
            transition: transform 0.3s ease;
        }

        /* تخصيص أيقونات القوائم المنسدلة */
        .nav-dropdown-toggle i:not(.dropdown-arrow) {
            width: 20px;
            margin-left: 0.875rem;
            font-size: 1rem;
        }

        .nav-link:hover i {
            transform: scale(1.1);
        }

        /* Dropdown Menu Styles */
        .nav-dropdown {
            position: relative;
        }

        .nav-dropdown-toggle {
            justify-content: flex-start;
            cursor: pointer;
            position: relative;
            padding-left: 3rem !important;
            /* إضافة مساحة للسهم */
        }

        .nav-dropdown-toggle span {
            flex: 1;
            text-align: right;
        }

        .nav-dropdown-toggle .dropdown-arrow {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
            font-size: 0.75rem;
            width: 12px;
            text-align: center;
            z-index: 1;
        }

        .nav-dropdown.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .nav-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            background: rgba(0, 0, 0, 0.2);
            border-right: 2px solid rgba(255, 193, 7, 0.3);
        }

        .nav-dropdown.open .nav-dropdown-menu {
            max-height: 1000px;
            opacity: 1;
        }

        .nav-dropdown-item {
            padding: 0.625rem 1.5rem 0.625rem 3rem;
            color: rgba(255, 255, 255, 0.75);
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            position: relative;
        }

        .nav-dropdown-item::before {
            content: '';
            position: absolute;
            right: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .nav-dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(-3px);
        }

        .nav-dropdown-item.active {
            color: #ffc107;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(-3px);
            font-weight: 700;
        }

        .nav-dropdown-item:hover::before {
            background: white;
            transform: translateY(-50%) scale(1.5);
        }

        .nav-dropdown-item.active::before {
            background: #ffc107;
            transform: translateY(-50%) scale(1.5);
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }

        .nav-dropdown-item.active i {
            color: #ffc107;
        }

        .nav-dropdown-item i {
            width: 16px;
            margin-left: 0.5rem;
            font-size: 0.85rem;
        }

        .main-content {
            margin-right: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-right 0.3s ease;
        }

        .main-content.expanded {
            margin-right: 0;
        }

        .sidebar-toggle {
            position: fixed;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 15px 12px;
            border-radius: 15px 0 0 15px;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
            z-index: 1050;
            transition: all 0.3s ease;
            font-size: 18px;
            cursor: pointer;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: linear-gradient(135deg, #1e3d72 0%, #2c5aa0 100%);
            transform: translateY(-50%) translateX(-5px);
            box-shadow: -4px 0 15px rgba(0, 0, 0, 0.3);
        }

        .sidebar-toggle:active {
            transform: translateY(-50%) translateX(-2px) scale(0.95);
        }

        .sidebar-toggle i {
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover i {
            transform: scale(1.1) rotate(5deg);
        }

        .sidebar-toggle.animating i {
            animation: togglePulse 0.3s ease;
        }

        @keyframes togglePulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .sidebar.collapsed~.sidebar-toggle {
            right: 10px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .sidebar.collapsed~.sidebar-toggle:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .content {
            padding: 2rem;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .border-primary {
            border-color: var(--primary-color) !important;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        /* Scrollbar Styling for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(100%);
                width: 100%;
                max-width: 320px;
                z-index: 1050;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar.collapsed {
                transform: translateX(100%);
            }

            /* إخفاء الشريط الجانبي افتراضياً في الهاتف */
            .sidebar:not(.show) {
                transform: translateX(100%);
            }

            .main-content {
                margin-right: 0 !important;
            }

            .mobile-toggle {
                display: inline-block !important;
                z-index: 1051;
            }

            .sidebar-toggle {
                display: none !important;
            }

            .nav-link {
                padding: 1rem 1.5rem;
            }

            .nav-dropdown-item {
                padding: 0.75rem 1.5rem 0.75rem 3rem;
            }

            /* إضافة overlay للخلفية في الهاتف */
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1049;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }
        }

        @media (min-width: 769px) {
            .sidebar-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(0);
            }

            .sidebar.collapsed {
                transform: translateX(100%);
            }
        }

        .mobile-toggle {
            display: none;
        }

        /* توحيد أزرار القائمة الجانبية */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: inline-block !important;
            }

            .sidebar-toggle {
                display: none !important;
            }
        }

        @media (min-width: 769px) {
            .mobile-toggle {
                display: none !important;
            }

            .sidebar-toggle {
                display: block !important;
            }
        }

        /* Animation for smooth transitions */
        .nav-link,
        .nav-dropdown-item,
        .nav-dropdown-menu {
            will-change: transform, opacity, max-height;
        }

        /* Focus states for accessibility */
        .nav-link:focus,
        .nav-dropdown-item:focus {
            outline: 2px solid rgba(255, 255, 255, 0.5);
            outline-offset: -2px;
        }

        /* Loading animation for dropdown menus */
        .nav-dropdown-menu.loading {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Smooth scrolling for sidebar */
        .sidebar {
            scroll-behavior: smooth;
        }

        /* Enhanced visual feedback */
        .nav-dropdown-toggle:hover .dropdown-arrow {
            transform: translateY(-50%) scale(1.1);
        }

        .nav-dropdown.open .nav-dropdown-toggle:hover .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg) scale(1.1);
        }

        /* Better spacing for nested items */
        .nav-dropdown-item {
            border-left: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .nav-dropdown-item:hover,
        .nav-dropdown-item.active {
            border-left-color: rgba(255, 255, 255, 0.3);
        }

        /* Improved mobile experience */
        @media (max-width: 480px) {
            .sidebar-brand {
                padding: 1.5rem 1rem;
            }

            .sidebar-brand h4 {
                font-size: 1.2rem;
            }

            .nav-link {
                padding: 1rem 1rem;
                font-size: 0.95rem;
            }

            .nav-dropdown-toggle {
                padding-left: 3rem !important;
            }

            .nav-dropdown-item {
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <!-- Overlay للأجهزة المحمولة -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

    <!-- زر إخفاء/إظهار القائمة الجانبية -->
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-building me-2"></i>نظام تِقان</h4>
            <small>إدارة المقاولات</small>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <!-- لوحة التحكم -->
                <?php if (hasPermission('menu_dashboard')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
                            href="<?= path('dashboard.php') ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>لوحة التحكم</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- أوامر العمل -->
                <?php if (hasAnyMenuPermission(['menu_work_orders_main', 'menu_work_orders_list', 'menu_work_orders_create', 'menu_work_orders_types', 'menu_work_orders_reports'])): ?>
                    <li class="nav-item nav-dropdown" id="workOrdersDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('workOrdersDropdown')">
                            <i class="fas fa-clipboard-list"></i>
                            <span>أوامر العمل</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <?php if (hasPermission('menu_work_orders_list')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'work-orders' ? 'active' : '' ?>"
                                    href="<?= path('work-orders/index.php') ?>">
                                    <i class="fas fa-list"></i>
                                    <span>عرض أوامر العمل</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_work_orders_create')): ?>
                                <a class="nav-dropdown-item" href="<?= path('work-orders/create.php') ?>">
                                    <i class="fas fa-plus"></i>
                                    <span>إنشاء أمر عمل جديد</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_work_orders_types')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'work-order-types' ? 'active' : '' ?>"
                                    href="<?= path('work-order-types/index.php') ?>">
                                    <i class="fas fa-tags"></i>
                                    <span>أنواع أوامر العمل</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_work_orders_reports')): ?>
                                <a class="nav-dropdown-item" href="<?= path('work-orders/reports.php') ?>">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>تقارير أوامر العمل</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- المستخلصات -->
                <?php if (hasAnyMenuPermission(['menu_extracts_main', 'menu_extracts_all', 'menu_extracts_partial', 'menu_extracts_final_regular', 'menu_extracts_final_partial', 'menu_extracts_create_partial', 'menu_extracts_create_final_reg', 'menu_extracts_create_final_part'])): ?>
                    <li class="nav-item nav-dropdown" id="extractsDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('extractsDropdown')">
                            <i class="fas fa-file-invoice"></i>
                            <span>المستخلصات</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <?php if (hasPermission('menu_extracts_all')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts' ? 'active' : '' ?>"
                                    href="<?= path('extracts/index.php') ?>">
                                    <i class="fas fa-list"></i>
                                    <span>عرض جميع المستخلصات</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_partial')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts-partial' ? 'active' : '' ?>"
                                    href="<?= path('extracts/partial/index.php') ?>">
                                    <i class="fas fa-file-alt"></i>
                                    <span>المستخلصات الجزئية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_final_regular')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts-final-regular' ? 'active' : '' ?>"
                                    href="<?= path('extracts/final-regular/index.php') ?>">
                                    <i class="fas fa-file-check"></i>
                                    <span>المستخلصات النهائية العادية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_final_partial')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts-final-for-partial' ? 'active' : '' ?>"
                                    href="<?= path('extracts/final-for-partial/index.php') ?>">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>المستخلصات النهائية للجزئية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_create_partial')): ?>
                                <a class="nav-dropdown-item" href="<?= path('extracts/partial/create.php') ?>">
                                    <i class="fas fa-plus"></i>
                                    <span>إنشاء مستخلص جزئي</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_create_final_reg')): ?>
                                <a class="nav-dropdown-item" href="<?= path('extracts/final-regular/create.php') ?>">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>إنشاء مستخلص نهائي عادي</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_create_final_part')): ?>
                                <a class="nav-dropdown-item" href="<?= path('extracts/final-for-partial/create.php') ?>">
                                    <i class="fas fa-plus-square"></i>
                                    <span>إنشاء مستخلص نهائي للجزئية</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- إدارة المخزون -->
                <?php if (hasAnyMenuPermission(['menu_inventory_main', 'menu_inventory_dashboard', 'menu_inventory_materials', 'menu_inventory_transactions', 'menu_inventory_requests', 'menu_inventory_inactive', 'menu_inventory_import_export', 'menu_inventory_catalog', 'menu_inventory_work_items', 'menu_inventory_analysis', 'menu_inventory_removed', 'menu_inventory_removed_analysis', 'menu_inventory_clients', 'menu_inventory_loans', 'menu_inventory_stocktaking'])): ?>
                    <li class="nav-item nav-dropdown" id="inventoryDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('inventoryDropdown')">
                            <i class="fas fa-warehouse"></i>
                            <span>إدارة المخزون</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">

                            <?php if (hasPermission('menu_inventory_dashboard')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'inventory' ? 'active' : '' ?>"
                                    href="<?= path('inventory/index.php') ?>">
                                    <i class="fas fa-warehouse"></i>
                                    <span>لوحة تحكم المخزون</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_materials')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'materials' ? 'active' : '' ?>"
                                    href="<?= path('inventory/materials/index.php') ?>">
                                    <i class="fas fa-boxes"></i>
                                    <span>المخزون</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_transactions')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'inventory-transactions' ? 'active' : '' ?>"
                                    href="<?= path('inventory/transactions/index.php') ?>">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>معاملات المخزون</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_requests')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'material-requests' ? 'active' : '' ?>"
                                    href="<?= path('inventory/material-requests/index.php') ?>">
                                    <i class="fas fa-clipboard-list"></i>
                                    <span>طلبات الصرف</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_inactive')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'materials-inactive' ? 'active' : '' ?>"
                                    href="<?= path('inventory/materials/inactive.php') ?>">
                                    <i class="fas fa-ban"></i>
                                    <span>المواد غير النشطة</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_import_export')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'materials-import-export' ? 'active' : '' ?>"
                                    href="<?= path('inventory/materials/import-export.php') ?>">
                                    <i class="fas fa-file-import"></i>
                                    <span>استيراد وتصدير المواد</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_catalog')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'material-catalog' ? 'active' : '' ?>"
                                    href="<?= path('inventory/material-catalog/index.php') ?>">
                                    <i class="fas fa-book-open"></i>
                                    <span>كتالوج المواد</span>
                                </a>
                            <?php endif; ?>


                            <?php if (hasPermission('menu_inventory_work_items')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'material-work-items' ? 'active' : '' ?>"
                                    href="<?= path('inventory/material-work-items/index.php') ?>">
                                    <i class="fas fa-link"></i>
                                    <span>ربط المواد ببنود الأعمال</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_analysis')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'material-analysis' ? 'active' : '' ?>"
                                    href="<?= path('inventory/material-analysis/index.php') ?>">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>تحليل المواد</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_removed')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'removed-materials' ? 'active' : '' ?>"
                                    href="<?= path('inventory/removed-materials/index.php') ?>">
                                    <i class="fas fa-recycle"></i>
                                    <span>المواد المزالة</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_removed_analysis')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'removed-materials-analysis' ? 'active' : '' ?>"
                                    href="<?= path('inventory/removed-materials/analysis.php') ?>">
                                    <i class="fas fa-chart-pie"></i>
                                    <span>تحليل المواد المزالة</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_clients')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'inventory_clients' ? 'active' : '' ?>"
                                    href="<?= path('inventory/clients/index.php') ?>">
                                    <i class="fas fa-users"></i>
                                    <span>العملاء والمقاولين</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_loans')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'inventory_loans' ? 'active' : '' ?>"
                                    href="<?= path('inventory/loans/index.php') ?>">
                                    <i class="fas fa-handshake"></i>
                                    <span>إدارة السلف</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_inventory_stocktaking')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'stocktaking' ? 'active' : '' ?>"
                                    href="<?= path('inventory/stocktaking/index.php') ?>">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>الجرد</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- شهادات الإنجاز -->
                <?php if (hasAnyMenuPermission(['menu_certificates_main', 'menu_cert_list', 'menu_cert_create', 'menu_cert_reports', 'menu_cert_import'])): ?>
                    <li class="nav-item nav-dropdown" id="certificatesDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('certificatesDropdown')">
                            <i class="fas fa-certificate"></i>
                            <span>شهادات الإنجاز</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <?php if (hasPermission('menu_cert_list')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'completion-certificates' ? 'active' : '' ?>"
                                    href="<?= path('inventory/completion-certificates/index.php') ?>">
                                    <i class="fas fa-list"></i>
                                    <span>عرض الشهادات</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_cert_create')): ?>
                                <a class="nav-dropdown-item" href="<?= path('inventory/completion-certificates/create.php') ?>">
                                    <i class="fas fa-plus"></i>
                                    <span>إنشاء شهادة جديدة</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_cert_reports')): ?>
                                <a class="nav-dropdown-item"
                                    href="<?= path('inventory/completion-certificates/reports.php') ?>">
                                    <i class="fas fa-chart-pie"></i>
                                    <span>تقارير الشهادات</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_cert_import')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'import-boq' ? 'active' : '' ?>"
                                    href="<?= path('inventory/completion-certificates/import-boq.php') ?>">
                                    <i class="fas fa-camera"></i>
                                    <span>استيراد مقايسة (OCR)</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- نظام الإنتاجية -->
                <?php if (hasAnyMenuPermission(['menu_productivity_main', 'menu_prod_dashboard', 'menu_prod_work_orders', 'menu_prod_work_items', 'menu_prod_daily_logs', 'menu_prod_approvals', 'menu_prod_approvers', 'menu_prod_reports'])): ?>
                    <li class="nav-item nav-dropdown" id="productivityDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('productivityDropdown')">
                            <i class="fas fa-chart-line"></i>
                            <span>نظام الإنتاجية</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <?php if (hasPermission('menu_prod_dashboard')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity' && !isset($_GET['section']) ? 'active' : '' ?>"
                                    href="<?= path('productivity/index.php') ?>">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>لوحة التحكم</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_work_orders')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-work-orders' ? 'active' : '' ?>"
                                    href="<?= path('productivity/work-orders/index.php') ?>">
                                    <i class="fas fa-clipboard-list"></i>
                                    <span>أوامر العمل</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_work_items')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-work-items' ? 'active' : '' ?>"
                                    href="<?= path('productivity/work-items/index.php') ?>">
                                    <i class="fas fa-tasks"></i>
                                    <span>بنود الإنتاجية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_daily_logs')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-daily-logs' ? 'active' : '' ?>"
                                    href="<?= path('productivity/daily-logs/index.php') ?>">
                                    <i class="fas fa-clipboard-list"></i>
                                    <span>السجلات اليومية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_approvals')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-approvals' ? 'active' : '' ?>"
                                    href="<?= path('productivity/approvals/index.php') ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>الاعتمادات</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_approvers')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-approvers' ? 'active' : '' ?>"
                                    href="<?= path('productivity/approvers/index.php') ?>">
                                    <i class="fas fa-user-check"></i>
                                    <span>إدارة المعتمدين</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_prod_reports')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'productivity-reports' ? 'active' : '' ?>"
                                    href="<?= path('productivity/reports/index.php') ?>">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>تقارير الإنتاجية</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- إدارة الموقع -->
                <?php if (hasAnyMenuPermission(['menu_site_management_main', 'menu_site_users', 'menu_site_roles', 'menu_site_branches', 'menu_site_reference', 'menu_site_work_items', 'menu_site_admin', 'menu_site_settings', 'menu_site_invoice', 'menu_site_notifications'])): ?>
                    <li class="nav-item nav-dropdown" id="siteManagementDropdown">
                        <a class="nav-link nav-dropdown-toggle" href="#" onclick="toggleDropdown('siteManagementDropdown')">
                            <i class="fas fa-cogs"></i>
                            <span>إدارة الموقع</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <?php if (hasPermission('menu_site_users')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'users' ? 'active' : '' ?>"
                                    href="<?= path('users/index.php') ?>">
                                    <i class="fas fa-users"></i>
                                    <span>إدارة المستخدمين</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_roles')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'roles' ? 'active' : '' ?>"
                                    href="<?= path('roles/index.php') ?>">
                                    <i class="fas fa-user-shield"></i>
                                    <span>إدارة الأدوار والصلاحيات</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_branches')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'branches' ? 'active' : '' ?>"
                                    href="<?= path('branches/index.php') ?>">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>إدارة الفروع</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_reference')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'reference-data' ? 'active' : '' ?>"
                                    href="<?= path('reference-data/index.php') ?>">
                                    <i class="fas fa-database"></i>
                                    <span>البيانات المرجعية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_work_items')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'work-items' ? 'active' : '' ?>"
                                    href="<?= path('admin/work-items/index.php') ?>">
                                    <i class="fas fa-tools"></i>
                                    <span>إدارة بنود الأعمال</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_admin')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'admin' ? 'active' : '' ?>"
                                    href="<?= path('admin/index.php') ?>">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>الإدارة العامة</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_settings')): ?>
                                <a class="nav-dropdown-item" href="<?= path('settings/index.php') ?>">
                                    <i class="fas fa-sliders-h"></i>
                                    <span>إعدادات النظام</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_invoice')): ?>
                                <a class="nav-dropdown-item" href="<?= path('settings/invoice-settings.php') ?>">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>إعدادات الفواتير الضريبية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_site_notifications')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'notification-settings' ? 'active' : '' ?>"
                                    href="<?= path('settings/notifications/index.php') ?>">
                                    <i class="fas fa-bell"></i>
                                    <span>إدارة إشعارات النظام</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-primary mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>

                    <h1 class="h4 mb-0 d-inline-block ms-3"><?= htmlspecialchars($pageTitle) ?></h1>

                    <?php if (!empty($breadcrumbs)): ?>
                        <nav aria-label="breadcrumb" class="d-inline-block ms-3">
                            <ol class="breadcrumb mb-0">
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <?php if ($index === count($breadcrumbs) - 1): ?>
                                        <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['title']) ?></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?= path($crumb['url']) ?>"><?= htmlspecialchars($crumb['title']) ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i>
                        <?= htmlspecialchars($user['full_name'] ?? 'مستخدم') ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= path('profile.php') ?>">
                                <i class="fas fa-user me-2"></i>الملف الشخصي
                            </a></li>
                        <li><a class="dropdown-item" href="<?= path('settings.php') ?>">
                                <i class="fas fa-cog me-2"></i>الإعدادات
                            </a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="<?= path('logout.php') ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                            </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($content)): ?>
                <?= $content ?>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (إذا كان مطلوباً) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables JavaScript (إذا كان مطلوباً) -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- CountUp.js - للأنميشن على الأرقام -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // تفعيل tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // تفعيل popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // إغلاق التنبيهات تلقائياً
        setTimeout(function () {
            var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // دالة إخفاء/إظهار القائمة الجانبية
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleBtn = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                // للأجهزة المحمولة
                if (sidebar.classList.contains('show')) {
                    // إخفاء القائمة
                    sidebar.classList.remove('show');
                    if (overlay) overlay.classList.remove('show');
                } else {
                    // إظهار القائمة
                    sidebar.classList.add('show');
                    if (overlay) overlay.classList.add('show');
                }
            } else {
                // للشاشات الكبيرة
                const toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;

                // إضافة تأثير الحركة للزر
                if (toggleBtn) {
                    toggleBtn.classList.add('animating');
                    setTimeout(() => toggleBtn.classList.remove('animating'), 300);
                }

                if (sidebar.classList.contains('collapsed')) {
                    // إظهار القائمة الجانبية
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    if (toggleIcon) toggleIcon.className = 'fas fa-bars';

                    // حفظ الحالة
                    localStorage.setItem('sidebarCollapsed', 'false');

                    // تحديث tooltip
                    if (toggleBtn) toggleBtn.setAttribute('data-bs-original-title', 'إخفاء القائمة الجانبية');
                } else {
                    // إخفاء القائمة الجانبية
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    if (toggleIcon) toggleIcon.className = 'fas fa-chevron-left';

                    // حفظ الحالة
                    localStorage.setItem('sidebarCollapsed', 'true');

                    // تحديث tooltip
                    if (toggleBtn) toggleBtn.setAttribute('data-bs-original-title', 'إظهار القائمة الجانبية');
                }
            }
        }

        // دالة إغلاق القائمة في الهاتف
        function closeMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
        }

        // دالة فتح/إغلاق القوائم المنسدلة
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const allDropdowns = document.querySelectorAll('.nav-dropdown');

            // إغلاق جميع القوائم الأخرى
            allDropdowns.forEach(item => {
                if (item.id !== dropdownId && item.classList.contains('open')) {
                    item.classList.remove('open');
                    // حفظ حالة الإغلاق
                    localStorage.setItem(`dropdown_${item.id}`, 'false');
                }
            });

            // تبديل حالة القائمة المحددة
            if (dropdown) {
                dropdown.classList.toggle('open');

                // حفظ الحالة في localStorage
                const isOpen = dropdown.classList.contains('open');
                localStorage.setItem(`dropdown_${dropdownId}`, isOpen.toString());

                // تأثير صوتي بصري
                if (isOpen) {
                    // إضافة تأثير عند الفتح
                    const menu = dropdown.querySelector('.nav-dropdown-menu');
                    if (menu) {
                        menu.style.transform = 'translateY(-10px)';
                        menu.style.opacity = '0';
                        setTimeout(() => {
                            menu.style.transform = 'translateY(0)';
                            menu.style.opacity = '1';
                        }, 50);
                    }
                }
            }
        }

        // دالة تحديد القائمة النشطة بناءً على الصفحة الحالية
        function setActiveDropdown() {
            const currentPage = '<?= $currentPage ?? '' ?>';
            const dropdownMappings = {
                'work-orders': 'workOrdersDropdown',
                'work-order-types': 'workOrdersDropdown',
                'extracts': 'extractsDropdown',
                'extracts-partial': 'extractsDropdown',
                'extracts-final-regular': 'extractsDropdown',
                'extracts-final-for-partial': 'extractsDropdown',
                'inventory': 'inventoryDropdown',
                'materials': 'inventoryDropdown',
                'inventory-transactions': 'inventoryDropdown',
                'material-requests': 'inventoryDropdown',

                'materials-inactive': 'inventoryDropdown',
                'materials-import-export': 'inventoryDropdown',
                'material-work-items': 'inventoryDropdown',
                'removed-materials': 'inventoryDropdown',
                'removed-materials-analysis': 'inventoryDropdown',
                'completion-certificates': 'certificatesDropdown',
                'users': 'siteManagementDropdown',
                'roles': 'siteManagementDropdown',
                'branches': 'siteManagementDropdown',
                'reference-data': 'siteManagementDropdown',
                'work-items': 'siteManagementDropdown',
                'admin': 'siteManagementDropdown'
            };

            const targetDropdown = dropdownMappings[currentPage];
            if (targetDropdown) {
                const dropdown = document.getElementById(targetDropdown);
                if (dropdown) {
                    dropdown.classList.add('open');
                    localStorage.setItem(`dropdown_${targetDropdown}`, 'true');
                }
            }
        }

        // استعادة حالة القائمة الجانبية والقوائم المنسدلة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleBtn = document.getElementById('sidebarToggle');
            const isMobile = window.innerWidth <= 768;

            if (!isMobile && toggleBtn) {
                // للشاشات الكبيرة - استعادة حالة الشريط الجانبي
                const toggleIcon = toggleBtn.querySelector('i');
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    if (toggleIcon) toggleIcon.className = 'fas fa-chevron-left';
                    toggleBtn.setAttribute('data-bs-original-title', 'إظهار القائمة الجانبية');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    if (toggleIcon) toggleIcon.className = 'fas fa-bars';
                    toggleBtn.setAttribute('data-bs-original-title', 'إخفاء القائمة الجانبية');
                }
            } else {
                // للأجهزة المحمولة - التأكد من إخفاء القائمة افتراضياً
                sidebar.classList.remove('show');
                const overlay = document.getElementById('sidebarOverlay');
                if (overlay) overlay.classList.remove('show');
            }

            // استعادة حالة القوائم المنسدلة
            const dropdowns = ['workOrdersDropdown', 'extractsDropdown', 'inventoryDropdown', 'certificatesDropdown', 'productivityDropdown', 'siteManagementDropdown'];
            dropdowns.forEach(dropdownId => {
                const dropdown = document.getElementById(dropdownId);
                if (dropdown) {
                    const isOpen = localStorage.getItem(`dropdown_${dropdownId}`) === 'true';
                    if (isOpen) {
                        dropdown.classList.add('open');
                    }
                }
            });

            // تحديد القائمة النشطة بناءً على الصفحة الحالية
            setActiveDropdown();

            // تفعيل tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // إضافة تأثيرات hover للقوائم المنسدلة
            const navLinks = document.querySelectorAll('.nav-link, .nav-dropdown-item');
            navLinks.forEach(link => {
                link.addEventListener('mouseenter', function () {
                    this.style.transform = 'translateX(-3px)';
                });

                link.addEventListener('mouseleave', function () {
                    this.style.transform = 'translateX(0)';
                });
            });

            // منع إغلاق القائمة عند النقر على عنصر فرعي
            const dropdownItems = document.querySelectorAll('.nav-dropdown-item');
            dropdownItems.forEach(item => {
                item.addEventListener('click', function (e) {
                    // السماح بالانتقال للرابط
                    // لا نحتاج لمنع الحدث الافتراضي هنا
                });
            });
        });

        // معالجة تغيير حجم الشاشة
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                // في الجوال، إخفاء القائمة الجانبية عند تغيير الحجم
                sidebar.classList.add('collapsed');
            }
        });

        // إغلاق القائمة الجانبية عند النقر خارجها في الجوال
        document.addEventListener('click', function (event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const mobileToggle = document.querySelector('.mobile-toggle');
            const overlay = document.getElementById('sidebarOverlay');
            const isMobile = window.innerWidth <= 768;

            if (isMobile &&
                !sidebar.contains(event.target) &&
                !toggleBtn?.contains(event.target) &&
                !mobileToggle?.contains(event.target) &&
                sidebar.classList.contains('show')) {

                closeMobileSidebar();
            }
        });

        // إضافة تأثيرات لمس للأجهزة المحمولة
        if ('ontouchstart' in window) {
            const navLinks = document.querySelectorAll('.nav-link, .nav-dropdown-item');
            navLinks.forEach(link => {
                link.addEventListener('touchstart', function () {
                    this.style.backgroundColor = 'rgba(255,255,255,0.15)';
                });

                link.addEventListener('touchend', function () {
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 150);
                });
            });
        }

        // تحسين الأداء للقوائم المنسدلة
        function optimizeDropdownPerformance() {
            const dropdowns = document.querySelectorAll('.nav-dropdown-menu');
            dropdowns.forEach(menu => {
                // استخدام transform بدلاً من height للأداء الأفضل
                menu.style.willChange = 'transform, opacity';
            });
        }

        // استدعاء تحسين الأداء
        optimizeDropdownPerformance();

        // معالج تغيير حجم الشاشة
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isMobile = window.innerWidth <= 768;

            if (!isMobile) {
                // عند التبديل للشاشة الكبيرة
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');

                // استعادة حالة الشريط الجانبي للشاشة الكبيرة
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            } else {
                // عند التبديل للهاتف
                sidebar.classList.remove('collapsed');
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
        });

        // دالة لتهيئة الرسوم البيانية في لوحة التحكم
        function initializeDashboardCharts() {
            // رسم بياني لحالة أوامر العمل
            const workOrdersStatusCtx = document.getElementById('workOrdersStatusChart');
            if (workOrdersStatusCtx) {
                const workOrdersStatusData = <?= $workOrdersStatusData ?? 'null' ?>;
                if (workOrdersStatusData && workOrdersStatusData.length > 0) {
                    const labels = workOrdersStatusData.map(item => {
                        const statusMap = {
                            'active': 'نشط',
                            'completed': 'مكتمل',
                            'inactive': 'غير نشط',
                            'cancelled': 'ملغى'
                        };
                        return statusMap[item.status] || item.status;
                    });
                    const data = workOrdersStatusData.map(item => item.count);
                    const colors = ['#28a745', '#17a2b8', '#6c757d', '#dc3545'];

                    new Chart(workOrdersStatusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: colors,
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: { family: "'Cairo', sans-serif" },
                                        padding: 15
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // رسم بياني للمستخلصات
            const extractsCtx = document.getElementById('extractsChart');
            if (extractsCtx) {
                const extractsData = <?= $extractsChartData ?? 'null' ?>;
                if (extractsData && extractsData.length > 0) {
                    const labels = extractsData.map(item => item.name);
                    const data = extractsData.map(item => item.count);
                    const colors = ['#ffc107', '#007bff', '#6f42c1'];

                    new Chart(extractsCtx, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: colors,
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: { family: "'Cairo', sans-serif" },
                                        padding: 15
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // رسم بياني لأوامر العمل حسب الفرع
            const branchesCtx = document.getElementById('branchesChart');
            if (branchesCtx) {
                const branchesData = <?= $branchesChartData ?? 'null' ?>;
                if (branchesData && branchesData.length > 0) {
                    const labels = branchesData.map(item => item.name || 'بدون فرع');
                    const data = branchesData.map(item => item.count);

                    new Chart(branchesCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'عدد أوامر العمل',
                                data: data,
                                backgroundColor: '#2c5aa0',
                                borderColor: '#1e3d72',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            indexAxis: 'y',
                            plugins: {
                                legend: {
                                    labels: {
                                        font: { family: "'Cairo', sans-serif" }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }

        // دالة تحريك الأرقام (CountUp Animation)
        function animateNumbers() {
            const numberElements = document.querySelectorAll('[data-animate-number]');

            numberElements.forEach(element => {
                const finalValue = parseFloat(element.getAttribute('data-animate-number'));

                // تحديد مدة الأنميشن بناءً على حجم الرقم
                let duration = 2;
                if (finalValue < 10) {
                    duration = 1; // أرقام صغيرة جداً
                } else if (finalValue < 100) {
                    duration = 1.2; // أرقام صغيرة
                } else if (finalValue < 1000) {
                    duration = 1.5; // أرقام متوسطة
                }

                // إنشاء كائن CountUp
                const counter = new countUp.CountUp(element, finalValue, {
                    duration: duration,
                    separator: ',',
                    decimal: '.',
                    useEasing: true,
                    easingFn: (t) => {
                        // دالة التسريع (ease-out)
                        return 1 - Math.pow(1 - t, 3);
                    }
                });

                // بدء الأنميشن
                if (!counter.error) {
                    counter.start();
                } else {
                    // في حالة الخطأ، عرض القيمة مباشرة
                    element.textContent = finalValue.toLocaleString('ar-SA');
                }
            });
        }

        // تهيئة الرسوم البيانية عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function () {
            initializeDashboardCharts();
            // تشغيل أنميشن الأرقام بعد تحميل الصفحة
            setTimeout(animateNumbers, 300);
        });
    </script>

</body>

</html>