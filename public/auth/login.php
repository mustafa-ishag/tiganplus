<?php
/**
 * صفحة تسجيل الدخول - نظام تِقان ERP
 * Login Page - Tiqan ERP System
 */

// بدء الجلسة
session_start();

// تضمين ملفات التكوين
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// إعادة توجيه إذا كان المستخدم مسجل دخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
} elseif (isset($_COOKIE['remember_token'])) {
    $db = getDB();
    $token = $_COOKIE['remember_token'];
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, b.name as branch_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        LEFT JOIN branches b ON u.branch_id = b.id 
        WHERE u.remember_token = ? AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['login_time'] = time();

        $permissionsStmt = $db->prepare("
            SELECT DISTINCT p.name FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $permissionsStmt->execute([$user['id']]);
        $permissions = $permissionsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($permissions) && !empty($user['role_id'])) {
            $permissionsStmt = $db->prepare("
                SELECT DISTINCT p.name FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ");
            $permissionsStmt->execute([$user['role_id']]);
            $permissions = $permissionsStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $directPermStmt = $db->prepare("
            SELECT DISTINCT p.name FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");
        $directPermStmt->execute([$user['id']]);
        $directPermissions = $directPermStmt->fetchAll(PDO::FETCH_COLUMN);

        $_SESSION['permissions'] = array_unique(array_merge($permissions, $directPermissions));

        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        logActivity($user['id'], 'login', "تسجيل دخول تلقائي (تذكرني) - المستخدم: {$user['username']}");

        header('Location: ../dashboard.php');
        exit();
    }
}

$error = '';
$success = '';

// معالجة رسالة تسجيل الخروج
if (isset($_GET['message'])) {
    $success = htmlspecialchars($_GET['message']);
}

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        // التحقق من صحة البيانات
        if (empty($username) || empty($password)) {
            throw new Exception('يرجى إدخال اسم المستخدم وكلمة المرور');
        }

        // الاتصال بقاعدة البيانات
        $db = getDB();

        // البحث عن المستخدم
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, b.name as branch_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('اسم المستخدم غير صحيح أو الحساب غير مفعل');
        }

        // التحقق من كلمة المرور
        if (!password_verify($password, $user['password'])) {
            throw new Exception('كلمة المرور غير صحيحة');
        }

        // حفظ بيانات الجلسة
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['login_time'] = time();

        // تحميل صلاحيات المستخدم
        // محاولة من جدول user_roles أولاً
        $permissionsStmt = $db->prepare("
            SELECT DISTINCT p.name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $permissionsStmt->execute([$user['id']]);
        $permissions = $permissionsStmt->fetchAll(PDO::FETCH_COLUMN);

        // إذا لم توجد صلاحيات، جرب من جدول users مباشرة
        if (empty($permissions) && !empty($user['role_id'])) {
            $permissionsStmt = $db->prepare("
                SELECT DISTINCT p.name
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ");
            $permissionsStmt->execute([$user['role_id']]);
            $permissions = $permissionsStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // أضف الصلاحيات المباشرة للمستخدم من جدول user_permissions
        $directPermStmt = $db->prepare("
            SELECT DISTINCT p.name
            FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");
        $directPermStmt->execute([$user['id']]);
        $directPermissions = $directPermStmt->fetchAll(PDO::FETCH_COLUMN);

        // دمج الصلاحيات من الدور والصلاحيات المباشرة
        $permissions = array_unique(array_merge($permissions, $directPermissions));

        $_SESSION['permissions'] = $permissions;

        // تشخيص الصلاحيات (يمكن حذف هذا لاحقاً)
        error_log("User {$user['id']} permissions: " . implode(', ', $permissions));

        // تحديث آخر تسجيل دخول
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // تسجيل نشاط تسجيل الدخول
        logActivity($user['id'], 'login', "تسجيل دخول للنظام - المستخدم: {$user['username']}");

        // معالجة تذكرني
        if ($remember_me) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); // 30 يوم

            // حفظ التوكن في قاعدة البيانات
            $tokenStmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $tokenStmt->execute([$token, $user['id']]);

            // حفظ التوكن في الكوكيز
            setcookie('remember_token', $token, $expires, '/', '', false, true);
        }

        $success = 'تم تسجيل الدخول بنجاح! جاري إعادة التوجيه...';

        // إعادة توجيه بعد ثانيتين
        header('refresh:2;url=../dashboard.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام تِقان ERP</title>
    <meta name="description" content="نظام إدارة موارد المؤسسة للمقاولات والإنشاءات">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/tigan-logo.png">
    
    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Tajawal -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">


    <style>
        :root {
            --primary-color: #4338ca;
            --primary-dark: #3730a3;
            --secondary-color: #64748b;
            --accent-color: #F0F2F5;
            --text-color: #2C3E50;
            --bg-color: #f8f9fa;
            --border-radius-lg: 24px;
            --border-radius-md: 12px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(67, 56, 202, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(55, 48, 163, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 2rem;
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: #ffffff;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0,0,0,0.05);
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.02);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #818cf8);
        }

        .brand-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .brand-logo {
            width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-smooth);
        }

        .brand-logo:hover {
            transform: translateY(-3px) scale(1.02);
        }

        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1e1e2d;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.95rem;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
            margin-bottom: 0.5rem;
            display: block;
        }

        .input-icon-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            transition: var(--transition-smooth);
            z-index: 10;
        }

        .form-control {
            background-color: #f8fafc;
            border: 2px solid transparent;
            border-radius: var(--border-radius-md);
            padding: 0.85rem 3rem 0.85rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: #1e293b;
            transition: var(--transition-smooth);
            box-shadow: none !important;
        }

        .form-control::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.1) !important;
        }

        .form-control:focus + .input-icon,
        .input-icon-wrapper:focus-within .input-icon {
            color: var(--primary-color);
        }

        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .form-check-input {
            width: 1.1rem;
            height: 1.1rem;
            border: 2px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: #64748b;
            cursor: pointer;
            font-weight: 500;
            padding-top: 2px;
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-smooth);
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-md);
            width: 100%;
            padding: 0.85rem;
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
        }

        .btn-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 56, 202, 0.3);
            color: white;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .alert-custom {
            border-radius: var(--border-radius-md);
            border: none;
            padding: 1rem;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            animation: fadeUp 0.3s ease-out;
        }

        .alert-danger-custom {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .alert-success-custom {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        /* Copyright Footer */
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    
    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="brand-section">
                <div class="brand-logo">
                    <img src="../assets/images/tigan-logo.png" alt="شعار تِقان">
                </div>
                <h1 class="brand-title">تِقان</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert-custom alert-danger-custom">
                    <i class="fas fa-exclamation-circle fs-5"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="fas fa-check-circle fs-5"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div id="jsErrorAlert" class="alert-custom alert-danger-custom d-none">
                <i class="fas fa-exclamation-circle fs-5"></i>
                <span id="jsErrorText"></span>
            </div>

            <form method="POST" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="username" class="form-label">اسم المستخدم</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username" placeholder="أدخل اسم المستخدم" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">كلمة المرور</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                    </div>
                </div>

                <div class="options-row">
                    <div class="form-check d-flex align-items-center mb-0">
                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1">
                        <label class="form-check-label" for="remember_me">
                            تذكرني
                        </label>
                    </div>
                    <a href="javascript:void(0)" class="forgot-password" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                        نسيت كلمة المرور؟
                    </a>
                </div>

                <button type="submit" class="btn-submit" id="loginBtn">
                    <i class="fas fa-spinner fa-spin d-none" id="loadingSpinner"></i>
                    <i class="fas fa-sign-in-alt" id="loginIcon"></i>
                    <span>تسجيل الدخول</span>
                </button>
            </form>
            
        </div>
        
        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> جميع الحقوق محفوظة لنظام تِقان ERP
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="forgotPasswordModalLabel">استعادة كلمة المرور</h5>
                    <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-info-circle text-primary mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-4">يرجى التواصل مع مسؤول النظام لاستعادة بيانات الدخول:</p>
                    <div class="p-3 bg-light rounded-3 mb-2 border text-end">
                        <i class="fas fa-envelope text-primary ms-2"></i> <strong>البريد:</strong> admin@tiqan.com
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-2 border text-end">
                        <i class="fas fa-phone text-success ms-2"></i> <strong>الهاتف:</strong> +966 12 345 6789
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <button type="button" class="btn btn-primary px-4 rounded-3" data-bs-dismiss="modal" style="background-color: var(--primary-color); border-color: var(--primary-color);">حسناً، فهمت</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {

            // Auto focus
            $('#username').focus();

            // Form Submit Handling
            $('#loginForm').on('submit', function(e) {
                const username = $('#username').val().trim();
                const password = $('#password').val();

                if (!username || !password) {
                    e.preventDefault();
                    $('#jsErrorText').text('يرجى إدخال اسم المستخدم وكلمة المرور');
                    $('#jsErrorAlert').removeClass('d-none');
                    return;
                }
                
                $('#jsErrorAlert').addClass('d-none');

                // Show loading state slightly delayed to ensure form submits correctly in all browsers
                const btn = $('#loginBtn');
                setTimeout(() => {
                    btn.prop('disabled', true);
                    $('#loginIcon').addClass('d-none');
                    $('#loadingSpinner').removeClass('d-none');
                    btn.find('span').text('جاري الدخول...');
                }, 10);
            });

            // Caps Lock Warning
            $('#password').on('keypress', function(e) {
                if (e.originalEvent.getModifierState('CapsLock')) {
                    if ($('#capsWarning').length === 0) {
                        $(this).parent().after('<small id="capsWarning" class="text-warning mt-1 d-block fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> زر Caps Lock قيد التشغيل</small>');
                    }
                } else {
                    $('#capsWarning').remove();
                }
            }).on('blur', function() {
                $('#capsWarning').remove();
            });
        });
    </script>
</body>
</html>
