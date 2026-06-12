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
    <meta name="author" content="Tiqan ERP System">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/tigan-logo.png">

    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts - Arabic -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-radius: 12px;
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* خلفية متحركة */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            animation: backgroundMove 20s linear infinite;
        }

        @keyframes backgroundMove {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-10px) translateY(-10px); }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            margin: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 3rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
            animation: pulse 2s infinite;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .system-title {
            color: var(--dark-color);
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .system-subtitle {
            color: var(--secondary-color);
            font-size: 1rem;
            font-weight: 400;
        }

        .form-floating {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 2px solid #e2e8f0;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
            background: white;
        }

        .form-floating .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            z-index: 5;
            transition: var(--transition);
        }

        .form-floating:focus-within .form-icon {
            color: var(--primary-color);
        }

        .form-floating label {
            padding-right: 3rem;
            color: var(--secondary-color);
        }

        .remember-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .form-check {
            display: flex;
            align-items: center;
        }

        .form-check-input {
            margin-left: 0.5rem;
            border-radius: 4px;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            width: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .system-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .system-info a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .system-info a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-spinner {
            display: none;
            margin-right: 0.5rem;
        }

        /* تحسينات للشاشات الصغيرة */
        @media (max-width: 576px) {
            .login-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .system-title {
                font-size: 1.5rem;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
            }

            .logo-icon i {
                font-size: 2rem;
            }
        }

        /* تأثيرات إضافية */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            width: 40px;
            height: 40px;
            top: 80%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.5;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.8;
            }
        }
    </style>
</head>
<body>
    <!-- عناصر متحركة في الخلفية -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- شعار النظام -->
            <div class="logo-section">
                <div class="logo-icon">
                    <img src="../assets/images/tigan-logo.png" alt="شعار تِقان">
                </div>
                <h1 class="system-title">نظام تِقان ERP</h1>
                <p class="system-subtitle">إدارة موارد المؤسسة للمقاولات والإنشاءات</p>
            </div>

            <!-- رسائل التنبيه -->
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- نموذج تسجيل الدخول -->
            <form method="POST" id="loginForm" novalidate>
                <div class="form-floating">
                    <i class="fas fa-user form-icon"></i>
                    <input type="text"
                           class="form-control"
                           id="username"
                           name="username"
                           placeholder="اسم المستخدم"
                           required
                           autocomplete="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label for="username">اسم المستخدم</label>
                </div>

                <div class="form-floating">
                    <i class="fas fa-lock form-icon"></i>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="كلمة المرور"
                           required
                           autocomplete="current-password">
                    <label for="password">كلمة المرور</label>
                </div>

                <div class="remember-section">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="remember_me"
                               name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            تذكرني لمدة 30 يوماً
                        </label>
                    </div>
                    <a href="#" class="forgot-password" onclick="showForgotPassword()">
                        نسيت كلمة المرور؟
                    </a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-spinner fa-spin loading-spinner"></i>
                    <i class="fas fa-sign-in-alt me-2"></i>
                    تسجيل الدخول
                </button>
            </form>

            <!-- معلومات النظام -->
            <div class="system-info">
                <p class="mb-2">
                    <i class="fas fa-shield-alt me-1"></i>
                    نظام آمن ومحمي
                </p>
                <p class="mb-0">
                    <a href="../dashboard.php">
                        <i class="fas fa-home me-1"></i>
                        العودة للصفحة الرئيسية
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {
            // تركيز على حقل اسم المستخدم
            $('#username').focus();

            // تحسين تجربة المستخدم
            $('.form-control').on('focus', function() {
                $(this).parent().addClass('focused');
            }).on('blur', function() {
                if (!$(this).val()) {
                    $(this).parent().removeClass('focused');
                }
            });

            // معالجة إرسال النموذج
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();

                const username = $('#username').val().trim();
                const password = $('#password').val();
                const loginBtn = $('#loginBtn');
                const spinner = $('.loading-spinner');

                // التحقق من صحة البيانات
                if (!username || !password) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'يرجى إدخال اسم المستخدم وكلمة المرور',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                // إظهار حالة التحميل
                loginBtn.prop('disabled', true);
                spinner.show();
                loginBtn.find('.fa-sign-in-alt').hide();

                // إرسال النموذج
                setTimeout(() => {
                    this.submit();
                }, 500);
            });

            // تحسين رسائل التنبيه
            <?php if ($error): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في تسجيل الدخول',
                    text: '<?php echo addslashes($error); ?>',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#dc2626'
                });
            <?php endif; ?>

            <?php if ($success): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'تم بنجاح',
                    text: '<?php echo addslashes($success); ?>',
                    timer: 2000,
                    showConfirmButton: false,
                    allowOutsideClick: false
                });
            <?php endif; ?>

            // تأثيرات بصرية إضافية
            $('.login-card').hover(
                function() {
                    $(this).addClass('shadow-lg');
                },
                function() {
                    $(this).removeClass('shadow-lg');
                }
            );

            // كشف caps lock
            $('#password').on('keypress', function(e) {
                const capsLock = e.originalEvent.getModifierState('CapsLock');
                if (capsLock) {
                    if (!$('.caps-warning').length) {
                        $(this).after('<small class="caps-warning text-warning mt-1 d-block"><i class="fas fa-exclamation-triangle me-1"></i>تم تفعيل Caps Lock</small>');
                    }
                } else {
                    $('.caps-warning').remove();
                }
            });

            // إخفاء تحذير caps lock عند فقدان التركيز
            $('#password').on('blur', function() {
                $('.caps-warning').remove();
            });
        });

        // وظيفة نسيان كلمة المرور
        function showForgotPassword() {
            Swal.fire({
                icon: 'info',
                title: 'استعادة كلمة المرور',
                html: `
                    <p>للحصول على مساعدة في استعادة كلمة المرور، يرجى التواصل مع:</p>
                    <div class="text-start mt-3">
                        <p><i class="fas fa-envelope text-primary me-2"></i> admin@etgan.com</p>
                        <p><i class="fas fa-phone text-success me-2"></i> +966 12 345 6789</p>
                        <p><i class="fas fa-clock text-info me-2"></i> من 8 صباحاً إلى 5 مساءً</p>
                    </div>
                `,
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#2563eb'
            });
        }

        // تحسين الأداء - تحميل الخطوط مسبقاً
        const fontLink = document.createElement('link');
        fontLink.rel = 'preload';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap';
        fontLink.as = 'style';
        document.head.appendChild(fontLink);

        // تحسين إمكانية الوصول
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                $('#loginForm').submit();
            }
        });

        // حماية من الهجمات
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
