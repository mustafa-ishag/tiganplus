<?php
/**
 * إضافة مستخدم جديد - AJAX
 * Create New User - AJAX
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإضافة مستخدمين']);
    exit();
}

// التحقق من صحة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

try {
    $db = getDB();
    
    // استلام البيانات وتنظيفها
    $username = sanitizeInput($_POST['username'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $department = sanitizeInput($_POST['department'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // التحقق من البيانات المطلوبة
    if (empty($username)) {
        throw new Exception('اسم المستخدم مطلوب');
    }
    
    if (empty($full_name)) {
        throw new Exception('الاسم الكامل مطلوب');
    }
    
    if (empty($password)) {
        throw new Exception('كلمة المرور مطلوبة');
    }
    
    if ($password !== $confirm_password) {
        throw new Exception('كلمات المرور غير متطابقة');
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new Exception('كلمة المرور يجب أن تكون ' . PASSWORD_MIN_LENGTH . ' أحرف على الأقل');
    }
    
    // التحقق من صحة البريد الإلكتروني
    if (!empty($email) && !validateEmail($email)) {
        throw new Exception('البريد الإلكتروني غير صحيح');
    }
    
    // التحقق من عدم وجود اسم المستخدم مسبقاً
    $checkUsername = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkUsername->execute([$username]);
    if ($checkUsername->fetch()) {
        throw new Exception('اسم المستخدم موجود مسبقاً');
    }
    
    // التحقق من عدم وجود البريد الإلكتروني مسبقاً
    if (!empty($email)) {
        $checkEmail = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            throw new Exception('البريد الإلكتروني موجود مسبقاً');
        }
    }
    
    // التحقق من صحة الفرع
    if ($branch_id !== null) {
        $checkBranch = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
        $checkBranch->execute([$branch_id]);
        if (!$checkBranch->fetch()) {
            throw new Exception('الفرع المحدد غير صحيح');
        }
    }
    
    // تشفير كلمة المرور
    $hashedPassword = hashPassword($password);

    // تحديد الدور الأساسي (أول دور محدد أو دور افتراضي)
    $primaryRoleId = 1; // دور افتراضي
    if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
        $firstRole = (int)$_POST['roles'][0];
        if ($firstRole > 0) {
            // التحقق من صحة الدور
            $checkRole = $db->prepare("SELECT id FROM roles WHERE id = ? AND status = 'active'");
            $checkRole->execute([$firstRole]);
            if ($checkRole->fetch()) {
                $primaryRoleId = $firstRole;
            }
        }
    }

    // إدراج المستخدم الجديد
    $insertSql = "
        INSERT INTO users (
            username, full_name, email, phone, password,
            department, position, branch_id, role_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmt = $db->prepare($insertSql);
    $result = $stmt->execute([
        $username,
        $full_name,
        $email ?: null,
        $phone ?: null,
        $hashedPassword,
        $department ?: null,
        $position ?: null,
        $branch_id,
        $primaryRoleId,
        $status
    ]);
    
    if ($result) {
        $userId = $db->lastInsertId();

        // إضافة الأدوار للمستخدم
        if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
            $insertRoleSql = "INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())";
            $roleStmt = $db->prepare($insertRoleSql);

            foreach ($_POST['roles'] as $roleId) {
                $roleId = (int)$roleId;
                if ($roleId > 0) {
                    // التحقق من صحة الدور
                    $checkRole = $db->prepare("SELECT id FROM roles WHERE id = ? AND status = 'active'");
                    $checkRole->execute([$roleId]);
                    if ($checkRole->fetch()) {
                        $roleStmt->execute([$userId, $roleId, $_SESSION['user_id']]);
                    }
                }
            }
        }

        // تسجيل العملية
        logActivity($_SESSION['user_id'], 'create_user', "تم إضافة المستخدم: $username (ID: $userId)");

        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة المستخدم بنجاح',
            'user_id' => $userId
        ]);
    } else {
        throw new Exception('فشل في إضافة المستخدم');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
