<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إجبار المتصفح على استخدام ترميز UTF-8 لحل مشكلة اللغة الغريبة في الإنتاج
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
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
    <link rel="icon" type="image/png" href="<?= path('assets/images/tigan-logo.png') ?>">

    <!-- Bootstrap CSS RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts - Tajawal -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap"
        rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- HTMX Configuration & Event Listeners -->
    <script>
        document.addEventListener('htmx:beforeSwap', function (evt) {
            // حفظ موضع التمرير للقائمة الجانبية
            const sidebarNav = document.querySelector('.sidebar-nav');
            if (sidebarNav) {
                sessionStorage.setItem('sidebarScrollTop', sidebarNav.scrollTop);
            }
        });

        document.addEventListener('htmx:afterSwap', function (evt) {
            // استعادة موضع التمرير قبل أن يقوم المتصفح بإعادة الرسم لتجنب الوميض
            const sidebarNav = document.querySelector('.sidebar-nav');
            const savedScroll = sessionStorage.getItem('sidebarScrollTop');
            if (sidebarNav && savedScroll) {
                sidebarNav.scrollTop = parseInt(savedScroll, 10);
            }
        });

        document.addEventListener('htmx:beforeSwap', function (evt) {
            // استبدال const/let بـ var في السكربتات المضمنة لمنع خطأ إعادة التعريف عند التنقل عبر HTMX
            if (evt.detail && evt.detail.serverResponse) {
                evt.detail.serverResponse = evt.detail.serverResponse.replace(
                    /(<script(?:\s[^>]*)?>)([\s\S]*?)(<\/script>)/gi,
                    function (match, openTag, content, closeTag) {
                        // استبدال const و let في بداية الأسطر (النطاق العام فقط) بـ var
                        var fixed = content.replace(/^(\s*)const\s+/gm, '$1var ');
                        fixed = fixed.replace(/^(\s*)let\s+/gm, '$1var ');
                        return openTag + fixed + closeTag;
                    }
                );
            }
        });

        document.addEventListener('htmx:afterSettle', function (evt) {
            // تأخير بسيط لضمان تقييم السكربتات المضمنة قبل إطلاق الأحداث
            setTimeout(function () {
                // إعادة تهيئة أحداث DOM و DataTables
                window.dispatchEvent(new Event('DOMContentLoaded'));
                if (typeof jQuery !== 'undefined') {
                    jQuery(document).trigger('ready');
                }
            }, 50);
        });
    </script>

    <style>
        :root {
            /* الألوان الرئيسية - مطابقة لنظام kn */
            --primary-color: #4338ca;
            --secondary-color: #3730a3;
            --accent-color: #F0F2F5;
            --text-color: #2C3E50;
            --border-color: #E8ECF1;
            --sidebar-width: 280px;

            /* ألوان إضافية */
            --gold: #4338ca;
            --gold-light: #6366f1;
            --gold-dark: #312e81;
            --gold-gradient: linear-gradient(135deg, #4338ca 0%, #6366f1 50%, #4338ca 100%);

            --navy: #0e2942;
            --navy-light: #16426a;
            --navy-dark: #071522;

            /* ألوان الحالة */
            --success-color: #22ae82;
            --warning-color: #F39C12;
            --danger-color: #E74C3C;
            --info-color: #3498DB;

            /* ألوان الخلفية */
            --bg-body: #F0F2F5;
            --bg-card: #FFFFFF;
            --bg-sidebar: #4338ca;
            --bg-header: #FFFFFF;
            --bg-input: #F8F9FA;
            --bg-hover: #F0F2F5;

            /* ألوان النص */
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --text-light: #95A5A6;

            /* الظلال */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
            --shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.1);

            /* الانتقالات */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;

            /* الحدود */
            --border-radius: 12px;
            --border-radius-sm: 8px;
        }

        * {
            font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body,
        html {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-color);
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            color: white;
            z-index: 1000;
            overflow: hidden;
            transition: transform 0.3s ease;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        /* Premium UI Classes */
        .dash-card {
            border: none;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .dash-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .icon-circle {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-primary-soft {
            background-color: rgba(67, 56, 202, 0.1);
            color: #4338ca;
        }

        .bg-success-soft {
            background-color: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .bg-warning-soft {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .bg-info-soft {
            background-color: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
        }

        .bg-danger-soft {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .bg-secondary-soft {
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .bg-dark-soft {
            background-color: rgba(31, 41, 55, 0.1);
            color: #1f2937;
        }

        /* Welcome Banner */
        .welcome-banner {
            background:
                radial-gradient(500px circle at 100% 0%, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%),
                radial-gradient(400px circle at 0% 100%, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0) 100%),
                linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 24px;
            position: relative;
            color: white;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(67, 56, 202, 0.2);
        }

        .welcome-title {
            font-weight: 800;
            font-size: 2rem;
            position: relative;
            z-index: 1;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Quick Actions Grid */
        .quick-action-card {
            border: 1px solid #f3f4f6;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            background: #fff;
            display: block;
            text-decoration: none;
            color: #4b5563;
        }

        .quick-action-card:hover {
            background: var(--primary-color);
            color: #fff;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(67, 56, 202, 0.2);
            border-color: var(--primary-color);
        }

        .quick-action-card .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
            transition: color 0.3s;
        }

        .quick-action-card:hover .action-icon {
            color: #fff;
        }

        .quick-action-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .quick-action-desc {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        /* رمز الريال السعودي */
        .sar-icon {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-left: 3px;
            vertical-align: middle;
        }

        .sar-icon-lg {
            display: inline-block;
            width: 18px;
            height: 18px;
            margin-left: 4px;
            vertical-align: middle;
        }

        .sar-icon svg,
        .sar-icon-lg svg {
            width: 100%;
            height: 100%;
        }

        .micro-dot {
            font-size: 8px;
        }

        .sidebar.collapsed {
            transform: translateX(100%);
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            min-height: 64px;
        }

        .sidebar-brand img.sidebar-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: var(--border-radius-sm);
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-brand h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.25rem;
            color: white;
            line-height: 1.3;
        }

        .sidebar-brand small {
            opacity: 0.6;
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-link span,
        .nav-dropdown-item span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: var(--transition-fast);
            position: relative;
            font-weight: 600;
            border-radius: 0;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .nav-link::before {
            display: none;
        }

        .nav-link:hover {
            color: white;
            background: rgba(0, 0, 0, 0.1);
            transform: none;
        }

        .nav-link.active,
        .nav-dropdown.open>.nav-link {
            color: white;
            background: rgba(0, 0, 0, 0.15);
            transform: none;
            font-weight: 700;
        }

        .nav-link:hover::before {
            display: none;
        }

        .nav-link.active::before,
        .nav-dropdown.open>.nav-link::before {
            display: none;
        }

        .nav-link.active i,
        .nav-dropdown.open>.nav-link i {
            color: white;
        }

        .nav-link i {
            width: 20px;
            margin-left: 0.875rem;
            font-size: 1.05rem;
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
            background: rgba(0, 0, 0, 0.05);
            border-right: 2px solid rgba(255, 255, 255, 0.2);
        }

        .nav-dropdown.open .nav-dropdown-menu {
            max-height: 1000px;
            opacity: 1;
        }

        .nav-dropdown-item {
            padding: 0.65rem 1.5rem 0.65rem 3rem;
            color: rgba(255, 255, 255, 0.75);
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            position: relative;
            white-space: nowrap;
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
            color: white;
            background: rgba(0, 0, 0, 0.15);
            transform: translateX(-3px);
            font-weight: 600;
        }

        .nav-dropdown-item:hover::before {
            background: white;
            transform: translateY(-50%) scale(1.5);
        }

        .nav-dropdown-item.active::before {
            background: white;
            transform: translateY(-50%) scale(1.5);
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.4);
        }

        .nav-dropdown-item.active i {
            color: white;
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

        /* Sidebar Toggle - داخل القائمة الجانبية أعلى اليسار */
        .btn-sidebar-toggle {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            width: 32px;
            height: 32px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-right: auto;
        }

        .btn-sidebar-toggle:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-toggle-wrapper button:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
        }



        .header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 0.75rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.02);
            position: sticky;
            top: 0;
            z-index: 999;
            transition: all 0.3s ease;
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
            transition: color 0.3s ease;
        }

        .breadcrumb-item a:hover {
            color: var(--secondary-color);
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .card {
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            border-radius: var(--border-radius);
            background: var(--bg-card);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow);
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
        .sidebar {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 3px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
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

            .header {
                padding: 0.5rem 1rem !important;
            }

            .header .h4 {
                font-size: 1.1rem !important;
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

            .sidebar.collapsed~.main-content .mobile-toggle {
                display: inline-block !important;
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

        /* Premium Table Design (Shared Component) */
        .premium-table {
            border-collapse: separate !important;
            border-spacing: 0 4px !important;
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        .premium-table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.75rem 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            border-top: none;
            white-space: nowrap;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .premium-table tbody tr {
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: all 0.25s ease;
            border-radius: 8px;
        }

        .premium-table tbody td {
            padding: 0.5rem 0.5rem;
            vertical-align: middle;
            color: #334155;
            font-size: 0.85rem;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            background-color: inherit;
            transition: all 0.2s ease;
        }

        .premium-table tbody td:first-child {
            border-right: 1px solid #f1f5f9;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .premium-table tbody td:last-child {
            border-left: 1px solid #f1f5f9;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .premium-table tbody td:not(:last-child),
        .premium-table thead th:not(:last-child) {
            border-left: 1px dashed #f1f5f9;
        }

        .premium-table tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -2px rgba(50, 50, 93, 0.08), 0 3px 7px -3px rgba(0, 0, 0, 0.06);
            position: relative;
            z-index: 10;
        }

        .premium-table tbody tr:hover td {
            border-color: #e2e8f0;
            color: #0f172a;
        }

        .premium-table tbody tr.table-success {
            background-color: #f0fdf4 !important;
        }

        .premium-table tbody tr.table-success td {
            border-color: #dcfce7;
        }

        .premium-table tbody tr.table-success:hover {
            background-color: #dcfce7 !important;
        }

        /* Premium Dash Cards (Shared Component) */
        .dash-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dash-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }

        .dash-card:hover .icon-circle {
            transform: scale(1.1) rotate(5deg);
        }

        /* Soft Background Colors */
        .bg-primary-soft {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }

        .bg-success-soft {
            background-color: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .bg-warning-soft {
            background-color: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .bg-info-soft {
            background-color: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .bg-danger-soft {
            background-color: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .bg-secondary-soft {
            background-color: rgba(100, 116, 139, 0.1);
            color: #475569;
        }

        /* Icons */
        .sar-icon {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-left: 3px;
            vertical-align: middle;
        }

        .sar-icon-lg {
            display: inline-block;
            width: 18px;
            height: 18px;
            margin-left: 4px;
            vertical-align: middle;
        }

        .sar-icon svg,
        .sar-icon-lg svg {
            width: 100%;
            height: 100%;
        }

        .department-badge {
            font-weight: 500;
        }

        /* DataTables Search Hiding */
        .dataTables_filter {
            display: none !important;
        }

        /* Premium Action Buttons (Shared Component) */
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 50rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
        }

        .action-btn i {
            font-size: 1rem;
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }

        .action-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .action-btn-success {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .action-btn-success:hover {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }

        .action-btn-info {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .action-btn-info:hover {
            background: rgba(6, 182, 212, 0.15);
            color: #0e7490;
        }

        .action-btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .action-btn-warning:hover {
            background: rgba(245, 158, 11, 0.25);
            color: #b45309;
        }

        .action-btn-light {
            background: #ffffff;
            color: #64748b;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .action-btn-light:hover {
            background: #f8fafc;
            color: #475569;
        }
    </style>
</head>

<body hx-boost="true">
    <!-- تعريف رمز الريال السعودي SVG عالمياً -->
    <svg style="display: none;">
        <symbol id="sar-symbol" viewBox="0 0 1124.14 1256.39">
            <path
                d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z" />
            <path
                d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z" />
        </symbol>
    </svg>

    <!-- Overlay للأجهزة المحمولة -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="<?= path('assets/images/tigan-logo.png') ?>" alt="شعار تِقان" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <h4 class="mb-0" style="font-size: 1.9rem; font-weight: 800;">تِقان</h4>
            </div>
            <!-- زر طي القائمة -->
            <button id="sidebarToggle" onclick="toggleSidebar()" title="طي/توسيع القائمة" class="btn-sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('workOrdersDropdown')">
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('extractsDropdown')">
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

                            <?php if (hasPermission('menu_extracts_final_partial')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts-final-for-partial' ? 'active' : '' ?>"
                                    href="<?= path('extracts/final-for-partial/index.php') ?>">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>المستخلصات النهائية للجزئية</span>
                                </a>
                            <?php endif; ?>

                            <?php if (hasPermission('menu_extracts_final_regular')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'extracts-final-regular' ? 'active' : '' ?>"
                                    href="<?= path('extracts/final-regular/index.php') ?>">
                                    <i class="fas fa-file-check"></i>
                                    <span>المستخلصات النهائية العادية</span>
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('inventoryDropdown')">
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('certificatesDropdown')">
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('productivityDropdown')">
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
                        <a class="nav-link nav-dropdown-toggle" href="javascript:void(0)"
                            onclick="toggleDropdown('siteManagementDropdown')">
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

                            <a class="nav-dropdown-item <?= $currentPage === 'contracts' ? 'active' : '' ?>"
                                href="<?= path('contracts/index.php') ?>">
                                <i class="fas fa-file-contract"></i>
                                <span>إدارة العقود</span>
                            </a>

                            <?php if (hasPermission('menu_site_reference')): ?>
                                <a class="nav-dropdown-item <?= $currentPage === 'reference-data' ? 'active' : '' ?>"
                                    href="<?= path('reference-data/index.php') ?>">
                                    <i class="fas fa-database"></i>
                                    <span>البيانات المرجعية</span>
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
                                <a class="nav-dropdown-item <?= $currentPage === 'qoyod-settings' ? 'active' : '' ?>"
                                    href="<?= path('settings/qoyod_settings.php') ?>">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>إعدادات ربط قيود</span>
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
        <div class="wrapper">
            <div class="header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button
                            class="btn btn-light text-secondary rounded-circle shadow-sm border-0 mobile-toggle p-2 flex-shrink-0"
                            onclick="toggleSidebar()" style="width: 40px; height: 40px; line-height: 1;">
                            <i class="fas fa-bars"></i>
                        </button>

                        <h1 class="h4 mb-0 fw-bold text-dark text-truncate"><?= htmlspecialchars($pageTitle) ?></h1>

                        <?php if (!empty($breadcrumbs)): ?>
                            <div class="mx-3 d-none d-md-block"
                                style="width: 1px; height: 24px; background-color: #e2e8f0;"></div>
                            <nav aria-label="breadcrumb" class="d-none d-md-block">
                                <ol class="breadcrumb mb-0" style="font-size: 0.85rem; font-weight: 500;">
                                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                        <?php if ($index === count($breadcrumbs) - 1): ?>
                                            <li class="breadcrumb-item active text-secondary" aria-current="page">
                                                <?= htmlspecialchars($crumb['title']) ?>
                                            </li>
                                        <?php else: ?>
                                            <li class="breadcrumb-item">
                                                <a href="<?= path($crumb['url']) ?>"
                                                    class="text-primary text-decoration-none fw-bold"><?= htmlspecialchars($crumb['title']) ?></a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </nav>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown">
                        <button
                            class="btn btn-light rounded-pill shadow-sm border-0 px-2 px-sm-3 py-2 d-flex align-items-center gap-2 dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            style="background-color: #fff;">
                            <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center"
                                style="width: 32px; height: 32px; font-size: 14px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="fw-bold text-dark d-none d-sm-inline-block"
                                style="font-size: 0.95rem;"><?= htmlspecialchars($user['full_name'] ?? 'مستخدم') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2"
                            style="border-radius: 16px; min-width: 220px; overflow: hidden;">
                            <li>
                                <div class="px-4 py-3 border-bottom mb-2 text-center bg-light">
                                    <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center mx-auto mb-2 shadow-sm"
                                        style="width: 48px; height: 48px; font-size: 20px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h6 class="mb-0 fw-bold text-dark">
                                        <?= htmlspecialchars($user['full_name'] ?? 'مستخدم') ?>
                                    </h6>
                                    <small class="text-muted">نظام تِقان</small>
                                </div>
                            </li>
                            <li><a class="dropdown-item py-2 px-4 d-flex align-items-center gap-3"
                                    href="<?= path('profile.php') ?>">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 text-primary d-flex align-items-center justify-content-center"
                                        style="width: 32px; height: 32px;"><i class="fas fa-user-circle"></i></div>
                                    <span class="fw-medium">الملف الشخصي</span>
                                </a></li>
                            <li><a class="dropdown-item py-2 px-4 d-flex align-items-center gap-3"
                                    href="<?= path('settings.php') ?>">
                                    <div class="bg-secondary bg-opacity-10 rounded p-2 text-secondary d-flex align-items-center justify-content-center"
                                        style="width: 32px; height: 32px;"><i class="fas fa-cog"></i></div>
                                    <span class="fw-medium">الإعدادات</span>
                                </a></li>
                            <li>
                                <hr class="dropdown-divider my-2">
                            </li>
                            <li><a class="dropdown-item py-2 px-4 d-flex align-items-center gap-3 text-danger"
                                    href="<?= path('logout.php') ?>" hx-boost="false">
                                    <div class="bg-danger bg-opacity-10 rounded p-2 text-danger d-flex align-items-center justify-content-center"
                                        style="width: 32px; height: 32px;"><i class="fas fa-sign-out-alt"></i></div>
                                    <span class="fw-bold">تسجيل الخروج</span>
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
                const overlay = document.getElementById('sidebarOverlay');
                const isMobile = window.innerWidth <= 768;

                if (isMobile) {
                    // للأجهزة المحمولة
                    if (sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                        if (overlay) overlay.classList.remove('show');
                    } else {
                        sidebar.classList.add('show');
                        if (overlay) overlay.classList.add('show');
                    }
                } else {
                    // للشاشات الكبيرة
                    if (sidebar.classList.contains('collapsed')) {
                        // إظهار القائمة الجانبية
                        sidebar.classList.remove('collapsed');
                        mainContent.classList.remove('expanded');
                        localStorage.setItem('sidebarCollapsed', 'false');
                    } else {
                        // إخفاء القائمة الجانبية
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        localStorage.setItem('sidebarCollapsed', 'true');
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
            (function () {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                const isMobile = window.innerWidth <= 768;

                if (!isMobile) {
                    // للشاشات الكبيرة - استعادة حالة الشريط الجانبي
                    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                    } else {
                        sidebar.classList.remove('collapsed');
                        mainContent.classList.remove('expanded');
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
            })();

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
                const mobileToggle = document.querySelector('.mobile-toggle');
                const isMobile = window.innerWidth <= 768;

                if (isMobile &&
                    !sidebar.contains(event.target) &&
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
                                            font: { family: "'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif" },
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
                                            font: { family: "'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif" },
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
                                    backgroundColor: '#176cb4',
                                    borderColor: '#0f4e85',
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
                                            font: { family: "'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif" }
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
            (function () {
                initializeDashboardCharts();
                // تشغيل أنميشن الأرقام بعد تحميل الصفحة
                setTimeout(animateNumbers, 300);
            })();
        </script>

        <!-- تثبيت رأس الجدول وشريط التمرير للمستخلصات -->
        <style>
            .sticky-table-header {
                position: fixed;
                top: 0;
                z-index: 1020;
                display: none;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
                overflow-x: scroll;
                overflow-y: hidden;
                pointer-events: none;
                scrollbar-width: none;
                -ms-overflow-style: none;
                transition: width 0.3s ease, left 0.3s ease;
            }

            .sticky-table-header::-webkit-scrollbar {
                display: none;
            }

            .sticky-table-header table {
                margin-bottom: 0;
            }

            .sticky-table-scrollbar {
                position: fixed;
                bottom: 0;
                z-index: 1020;
                display: none;
                overflow-x: auto;
                overflow-y: hidden;
                background: #f0f0f0;
                border-top: 1px solid #ccc;
                transition: width 0.3s ease, left 0.3s ease;
            }

            .sticky-table-scrollbar .scroll-inner {
                height: 1px;
            }
        </style>

        <script>
            // دالة عامة لتثبيت رأس أي جدول + شريط تمرير أفقي
            function initStickyTable(tableId) {
                var $table = $('#' + tableId);
                if (!$table.length) return;

                var $scrollParent = $table.closest('.table-responsive');
                if (!$scrollParent.length) return;

                // تحديد لون الرأس من الجدول الأصلي
                var $origThead = $table.find('thead');
                var headBg = '#212529';
                var headColor = '#fff';
                if ($origThead.hasClass('table-dark')) {
                    headBg = '#212529'; headColor = '#fff';
                } else if ($origThead.hasClass('table-primary')) {
                    headBg = '#0d6efd'; headColor = '#fff';
                } else {
                    // قراءة اللون من العنصر
                    var computedBg = window.getComputedStyle($origThead[0]).backgroundColor;
                    if (computedBg && computedBg !== 'rgba(0, 0, 0, 0)') headBg = computedBg;
                }

                // إنشاء العناصر
                var $stickyWrap = $('<div class="sticky-table-header" id="' + tableId + '-sticky-hdr"></div>');
                var $stickyScrollbar = $('<div class="sticky-table-scrollbar" id="' + tableId + '-sticky-scroll"><div class="scroll-inner"></div></div>');
                $('body').append($stickyWrap).append($stickyScrollbar);

                var syncing = false;

                function buildHeader() {
                    var $thead = $table.find('thead');
                    if (!$thead.length) return;

                    var $cloneTable = $('<table class="table"></table>').css('margin-bottom', 0);
                    var $cloneThead = $thead.clone();
                    $cloneTable.append($cloneThead);
                    $stickyWrap.empty().append($cloneTable);

                    // مزامنة عرض الأعمدة
                    var $origThs = $thead.find('th');
                    var $cloneThs = $cloneThead.find('th');
                    $origThs.each(function (i) {
                        var w = $(this).outerWidth();
                        $cloneThs.eq(i).css({ 'width': w + 'px', 'min-width': w + 'px', 'background': headBg, 'color': headColor });
                    });

                    $cloneTable.css('width', $table.outerWidth() + 'px');
                    $stickyWrap.css('background', headBg);
                    syncPos();
                }

                function syncPos() {
                    var rect = $scrollParent[0].getBoundingClientRect();
                    var sl = $scrollParent.scrollLeft();
                    $stickyWrap.css({ 'width': rect.width + 'px', 'left': rect.left + 'px' });
                    $stickyWrap.scrollLeft(sl);
                    $stickyScrollbar.css({ 'width': rect.width + 'px', 'left': rect.left + 'px' });
                    $stickyScrollbar.find('.scroll-inner').css('width', $table.outerWidth() + 'px');
                }

                function handleHeader() {
                    var $thead = $table.find('thead');
                    if (!$thead.length) return;
                    var theadTop = $thead.offset().top;
                    var tableBottom = $table.offset().top + $table.outerHeight() - $thead.outerHeight();
                    var scrollTop = $(window).scrollTop();

                    if (scrollTop > theadTop && scrollTop < tableBottom) {
                        if ($stickyWrap.css('display') === 'none') buildHeader();
                        syncPos();
                        $stickyWrap.show();
                    } else {
                        $stickyWrap.hide();
                    }
                }

                function handleScrollbar() {
                    var rect = $scrollParent[0].getBoundingClientRect();
                    var vh = $(window).height();
                    var wider = $table.outerWidth() > $scrollParent.outerWidth();
                    var bottomVisible = rect.bottom <= vh;

                    if (wider && !bottomVisible && rect.top < vh) {
                        syncPos();
                        $stickyScrollbar.show();
                    } else {
                        $stickyScrollbar.hide();
                    }
                }

                // مزامنة التمرير
                $stickyScrollbar.on('scroll', function () {
                    if (syncing) return; syncing = true;
                    $scrollParent.scrollLeft($stickyScrollbar.scrollLeft());
                    if ($stickyWrap.is(':visible')) $stickyWrap.scrollLeft($stickyScrollbar.scrollLeft());
                    syncing = false;
                });

                $scrollParent.on('scroll', function () {
                    if (syncing) return; syncing = true;
                    if ($stickyWrap.is(':visible')) $stickyWrap.scrollLeft($scrollParent.scrollLeft());
                    if ($stickyScrollbar.is(':visible')) $stickyScrollbar.scrollLeft($scrollParent.scrollLeft());
                    syncing = false;
                });

                $(window).on('scroll', function () { handleHeader(); handleScrollbar(); });
                $(window).on('resize', function () {
                    if ($stickyWrap.is(':visible')) buildHeader();
                    handleScrollbar();
                });

                $table.on('draw.dt', function () {
                    if ($stickyWrap.is(':visible')) buildHeader();
                    handleScrollbar();
                });

                // مزامنة سلسة مع القائمة الجانبية
                var $toggle = $('#sidebarToggle');
                if ($toggle.length) {
                    $toggle.on('click', function () {
                        var start = Date.now();
                        function anim() {
                            syncPos();
                            if (Date.now() - start < 350) {
                                requestAnimationFrame(anim);
                            } else {
                                if ($stickyWrap.is(':visible')) buildHeader();
                                handleScrollbar();
                            }
                        }
                        requestAnimationFrame(anim);
                    });
                }
            }

            // تطبيق على جدول المستخلصات
            $(document).ready(function () {
                if ($('#extractsTable').length) {
                    initStickyTable('extractsTable');
                }
            });
        </script>
</body>

</html>