<?php
/**
 * تحديث بيانات المستخدم - AJAX
 * Update User Data - AJAX Handler
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// التحقق من البيانات المطلوبة
if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
    exit;
}

$userId = (int)$_POST['user_id'];

// التحقق من الصلاحيات
if (!hasPermission('manage_users') && $_SESSION['user_id'] != $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل هذا المستخدم']);
    exit;
}

$requiredFields = ['username', 'full_name'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    echo json_encode([
        'success' => false, 
        'message' => 'البيانات المطلوبة مفقودة: ' . implode(', ', $missingFields)
    ]);
    exit;
}

// التحقق من تطابق كلمات المرور (إذا تم إدخالها)
$password = trim($_POST['password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if ($password || $confirmPassword) {
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'كلمات المرور غير متطابقة']);
        exit;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل']);
        exit;
    }
}

// تنظيف البيانات
$userData = [
    'username' => trim($_POST['username']),
    'full_name' => trim($_POST['full_name']),
    'email' => trim($_POST['email'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'department' => trim($_POST['department'] ?? ''),
    'position' => trim($_POST['position'] ?? ''),
    'branch_id' => !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null,
    'status' => $_POST['status'] ?? 'active'
];

// التحقق من صحة البيانات
if (!preg_match('/^[a-zA-Z0-9_]+$/', $userData['username'])) {
    echo json_encode(['success' => false, 'message' => 'اسم المستخدم يجب أن يحتوي على أحرف وأرقام فقط']);
    exit;
}

if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) {
    echo json_encode(['success' => false, 'message' => 'اسم المستخدم يجب أن يكون بين 3 و 50 حرف']);
    exit;
}

if (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
    exit;
}

if (!in_array($userData['status'], ['active', 'inactive', 'suspended'])) {
    echo json_encode(['success' => false, 'message' => 'حالة المستخدم غير صحيحة']);
    exit;
}

// معالجة الأدوار المحددة
$selectedRoles = [];
if (!empty($_POST['selected_roles'])) {
    $rolesData = json_decode($_POST['selected_roles'], true);
    if (is_array($rolesData)) {
        $selectedRoles = array_map('intval', $rolesData);
    }
}

try {
    $db = getDB();
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // جلب بيانات المستخدم الحالية
    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }
    
    // التحقق من عدم وجود اسم المستخدم (إذا تم تغييره)
    if ($userData['username'] !== $currentUser['username']) {
        // منع المستخدم من تغيير اسم المستخدم الخاص به
        if ($_SESSION['user_id'] == $userId) {
            $userData['username'] = $currentUser['username']; // الاحتفاظ بالاسم القديم
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$userData['username'], $userId]);
            if ($stmt->fetch()) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود بالفعل']);
                exit;
            }
        }
    }
    
    // التحقق من عدم وجود البريد الإلكتروني (إذا تم إدخاله وتغييره)
    if (!empty($userData['email']) && $userData['email'] !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$userData['email'], $userId]);
        if ($stmt->fetch()) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني موجود بالفعل']);
            exit;
        }
    }
    
    // منع المستخدم من تغيير حالة حسابه الخاص
    if ($_SESSION['user_id'] == $userId) {
        $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData['status'] = $stmt->fetchColumn();
    }
    
    // تحديث بيانات المستخدم
    $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, department = ?, position = ?, branch_id = ?, status = ?, updated_at = NOW()";
    $params = [
        $userData['username'],
        $userData['full_name'],
        $userData['email'] ?: null,
        $userData['phone'] ?: null,
        $userData['department'] ?: null,
        $userData['position'] ?: null,
        $userData['branch_id'],
        $userData['status']
    ];
    
    // إضافة كلمة المرور إذا تم تغييرها
    if ($password) {
        $sql .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $userId;
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث بيانات المستخدم']);
        exit;
    }
    
    // تحديث الأدوار (فقط إذا كان لديه صلاحية إدارة المستخدمين وليس المستخدم نفسه)
    if (hasPermission('manage_users') && $_SESSION['user_id'] != $userId) {
        // تحديد الدور الأساسي (أول دور محدد أو الدور الحالي)
        $primaryRoleId = null;
        if (!empty($selectedRoles)) {
            $firstRole = (int)$selectedRoles[0];
            if ($firstRole > 0) {
                // التحقق من صحة الدور
                $checkStmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
                $checkStmt->execute([$firstRole]);
                if ($checkStmt->fetch()) {
                    $primaryRoleId = $firstRole;
                }
            }
        }

        // تحديث الدور الأساسي في جدول users إذا تم تحديد دور جديد
        if ($primaryRoleId) {
            $updateRoleStmt = $db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $updateRoleStmt->execute([$primaryRoleId, $userId]);
        }

        // حذف الأدوار الحالية
        $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);

        // إضافة الأدوار الجديدة
        if (!empty($selectedRoles)) {
            $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");

            foreach ($selectedRoles as $roleId) {
                // التحقق من وجود الدور
                $checkStmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
                $checkStmt->execute([$roleId]);

                if ($checkStmt->fetch()) {
                    $stmt->execute([$userId, $roleId, $_SESSION['user_id']]);
                }
            }
        }
    }
    
    // تسجيل العملية
    logActivity($_SESSION['user_id'], 'update_user', 
        "تم تحديث بيانات المستخدم: {$userData['username']} ({$userData['full_name']})");
    
    // تأكيد المعاملة
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث بيانات المستخدم بنجاح',
        'user_id' => $userId,
        'username' => $userData['username']
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    error_log("Error updating user: " . $e->getMessage());
    
    // رسالة خطأ مخصصة حسب نوع الخطأ
    $errorMessage = 'حدث خطأ في النظام';
    
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        if (strpos($e->getMessage(), 'username') !== false) {
            $errorMessage = 'اسم المستخدم موجود بالفعل';
        } elseif (strpos($e->getMessage(), 'email') !== false) {
            $errorMessage = 'البريد الإلكتروني موجود بالفعل';
        } else {
            $errorMessage = 'البيانات مكررة';
        }
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'debug' => $e->getMessage() // يمكن إزالة هذا في الإنتاج
    ]);
}
?>
