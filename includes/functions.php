<?php
/**
 * ملف الدوال المساعدة
 * Helper Functions File
 */

// تضمين ملف تحميل الصلاحيات
require_once __DIR__ . '/load-permissions.php';

// منع الوصول المباشر
if (!defined('TIQAN_SYSTEM')) {
    die('Access denied');
}

/**
 * دوال الأمان
 * Security Functions
 */

// تنظيف البيانات المدخلة
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// التحقق من صحة البريد الإلكتروني
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// تشفير كلمة المرور
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// التحقق من كلمة المرور
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// توليد رمز عشوائي
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

// التحقق من صحة رقم الهاتف السعودي
function validateSaudiPhone($phone)
{
    $pattern = '/^(05|5)[0-9]{8}$/';
    return preg_match($pattern, $phone);
}

/**
 * دوال التاريخ والوقت
 * Date and Time Functions
 */

// تنسيق التاريخ للعرض
function formatDate($date, $format = DISPLAY_DATE_FORMAT)
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date($format, strtotime($date));
}

// تنسيق التاريخ والوقت للعرض
function formatDateTime($datetime, $format = DISPLAY_DATETIME_FORMAT)
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($datetime));
}

// الحصول على التاريخ الحالي
function getCurrentDate()
{
    return date(DATE_FORMAT);
}

// الحصول على التاريخ والوقت الحالي
function getCurrentDateTime()
{
    return date(DATETIME_FORMAT);
}

/**
 * دوال الملفات
 * File Functions
 */

// التحقق من نوع الملف المسموح
function isAllowedFileType($filename)
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_FILE_TYPES);
}

// الحصول على حجم الملف بصيغة قابلة للقراءة
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// رفع ملف
function uploadFile($file, $targetDir = UPLOAD_PATH)
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'فشل في رفع الملف'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً'];
    }

    if (!isAllowedFileType($file['name'])) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $targetPath = $targetDir . $filename;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }

    return ['success' => false, 'message' => 'فشل في حفظ الملف'];
}

/**
 * دوال الإحصائيات
 * Statistics Functions
 */

// الحصول على عدد الفروع النشطة
function getActiveBranchesCount()
{
    try {
        return fetchColumn("SELECT COUNT(*) FROM branches WHERE status = 'active'");
    } catch (Exception $e) {
        return 0;
    }
}

// الحصول على عدد أوامر العمل النشطة
function getActiveWorkOrdersCount()
{
    try {
        return fetchColumn("SELECT COUNT(*) FROM work_orders WHERE status = 'active'");
    } catch (Exception $e) {
        return 0;
    }
}

// الحصول على عدد المستخلصات قيد المراجعة
function getPendingExtractsCount()
{
    try {
        return fetchColumn("SELECT COUNT(*) FROM extracts WHERE status = 'pending'");
    } catch (Exception $e) {
        return 0;
    }
}

// الحصول على إجمالي القيمة
function getTotalValue()
{
    try {
        return fetchColumn("SELECT SUM(actual_value) FROM work_orders WHERE status = 'active'") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * دوال المساعدة العامة
 * General Helper Functions
 */

// إعادة التوجيه
function redirect($url)
{
    header("Location: $url");
    exit();
}

// عرض رسالة تنبيه
function setAlert($message, $type = 'info')
{
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// الحصول على رسالة التنبيه وحذفها
function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// التحقق من الصلاحية
function hasPermission($permission)
{
    return checkPermission($permission);
}

// التحقق من صلاحية الفرع
function canAccessBranch($branchId)
{
    // إذا كان المستخدم مدير عام، يمكنه الوصول لجميع الفروع
    if (hasPermission('view_all_branches')) {
        return true;
    }

    // إذا كان المستخدم مقيد بفرع معين
    if (isset($_SESSION['user_branch_id'])) {
        return $_SESSION['user_branch_id'] == $branchId;
    }

    return false;
}

/**
 * دوال قاعدة البيانات
 * Database Functions
 */

// الحصول على اتصال قاعدة البيانات
function getDB()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            // Try to use database.php config first, fallback to config.php
            if (file_exists(__DIR__ . '/../config/database.php')) {
                $config = require __DIR__ . '/../config/database.php';
                $dbConfig = $config['connections']['mysql'];

                $host = $dbConfig['host'];
                $port = $dbConfig['port'];
                $database = $dbConfig['database'];
                $username = $dbConfig['username'];
                $password = $dbConfig['password'];
                $charset = $dbConfig['charset'];
                $options = $dbConfig['options'];
            } else {
                // Fallback to config.php constants
                $host = DB_HOST;
                $port = '3306';
                $database = DB_NAME;
                $username = DB_USER;
                $password = DB_PASS;
                $charset = DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
                ];
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("فشل في الاتصال بقاعدة البيانات: " . $e->getMessage());
        }
    }

    return $pdo;
}

// تسجيل العمليات — يقبل نمطين:
//   logActivity($userId, $action, $description)  ← الشكل الصحيح
//   logActivity($action, $description)            ← الشكل القديم (للتوافق)
function logActivity($userIdOrAction, $actionOrDescription = '', $description = '', $ipAddress = null, $userAgent = null)
{
    try {
        // الكشف التلقائي عن نمط الاستدعاء
        if (is_numeric($userIdOrAction)) {
            // النمط الجديد: (userId, action, description)
            $userId = (int) $userIdOrAction;
            $action = $actionOrDescription;
            $desc = $description;
        } else {
            // النمط القديم: (action, description) — استخدم user_id من الجلسة
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $action = $userIdOrAction;
            $desc = $actionOrDescription;
        }

        if ($userId <= 0) {
            return false; // لا نسجل بدون user_id حقيقي
        }

        $db = getDB();

        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        }

        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $desc, $ipAddress, $userAgent]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// تنسيق الأرقام
function formatNumber($number, $decimals = 2)
{
    // التحقق من أن الرقم ليس null أو فارغ
    if ($number === null || $number === '') {
        return '0';
    }

    // تحويل إلى رقم إذا كان نص
    if (!is_numeric($number)) {
        return '0';
    }

    return number_format((float) $number, $decimals, '.', ',');
}

// تنسيق العملة
function formatCurrency($amount)
{
    // التحقق من أن المبلغ ليس null أو فارغ
    if ($amount === null || $amount === '') {
        return '0.00 ريال';
    }

    return formatNumber($amount, 2) . ' ريال';
}

// التحقق من وجود جدول في قاعدة البيانات
function tableExists($tableName)
{
    try {
        $db = getDB();
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * دوال المسارات والروابط
 * Path and URL Functions
 *
 * ملاحظة: دوال المسارات الرئيسية موجودة في path-helper.php
 */

// بناء رابط كامل
function url($relativePath = '')
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // استخدام مسار مطلق بسيط
    $basePath = '/tiqanplus/public/';
    $fullPath = $basePath . ltrim($relativePath, '/');

    return $protocol . '://' . $host . $fullPath;
}

// الحصول على المسار الحالي
function getCurrentPath()
{
    return $_SERVER['REQUEST_URI'];
}

// التحقق من المسار النشط
function isActivePath($path)
{
    $currentPath = getCurrentPath();
    return strpos($currentPath, $path) !== false;
}

/**
 * دوال نظام الموافقات الديناميكي
 * Dynamic Approval System Functions
 */

// التحقق من صلاحية المستخدم للموافقة بواسطة step_id
function canApproveRequestByStep($stepId, $branchId = null, $workOrderId = null, $userId = null)
{
    try {
        require_once __DIR__ . '/../models/ApprovalAssignment.php';

        $userId = $userId ?: $_SESSION['user_id'];
        $approvalModel = new ApprovalAssignment();

        return $approvalModel->canUserApproveStep($userId, $stepId, $branchId, $workOrderId);

    } catch (Exception $e) {
        error_log("Error in canApproveRequestByStep: " . $e->getMessage());
        return false;
    }
}

// التحقق من صلاحية المستخدم للموافقة (بواسطة step_key — للتوافقية)
function canApproveRequest($approvalType, $branchId = null, $workOrderId = null, $userId = null)
{
    try {
        require_once __DIR__ . '/../models/ApprovalAssignment.php';

        $userId = $userId ?: $_SESSION['user_id'];
        $approvalModel = new ApprovalAssignment();

        return $approvalModel->canUserApprove($userId, $approvalType, $branchId, $workOrderId);

    } catch (Exception $e) {
        error_log("Error in canApproveRequest: " . $e->getMessage());
        return false;
    }
}

// الحصول على خطوات الاعتماد الفعالة
function getActiveApprovalSteps()
{
    try {
        require_once __DIR__ . '/../models/ApprovalAssignment.php';

        $approvalModel = new ApprovalAssignment();
        return $approvalModel->getAllSteps(true);

    } catch (Exception $e) {
        error_log("Error in getActiveApprovalSteps: " . $e->getMessage());
        return [];
    }
}

// الحصول على اسم الحالة الديناميكية
function getStatusLabel($status)
{
    $staticLabels = [
        'draft' => ['مسودة', 'secondary'],
        'submitted' => ['مرسل', 'info'],
        'approved' => ['معتمد نهائياً', 'success'],
        'rejected' => ['مرفوض', 'danger'],
        'cancelled' => ['ملغي', 'warning'],
        'revision_requested' => ['طلب تعديل', 'info'],
        // للتوافق مع البيانات القديمة
        'warehouse_approved' => ['موافقة المستودع', 'primary'],
        'project_approved' => ['معتمد نهائياً', 'success'],
        'branch_approved' => ['معتمد نهائياً', 'success'],
    ];

    if (isset($staticLabels[$status])) {
        return $staticLabels[$status];
    }

    // حالات ديناميكية: pending_step_X
    if (preg_match('/^pending_step_(\d+)$/', $status, $matches)) {
        try {
            require_once __DIR__ . '/../models/ApprovalAssignment.php';
            $approvalModel = new ApprovalAssignment();
            $db = getDB();
            $step = $db->prepare("SELECT step_name FROM approval_steps WHERE step_order = ? AND is_active = 1");
            $step->execute([(int) $matches[1]]);
            $stepData = $step->fetch(PDO::FETCH_ASSOC);
            if ($stepData) {
                return ['بانتظار ' . $stepData['step_name'], 'warning'];
            }
        } catch (Exception $e) {
            error_log("Error in getStatusLabel: " . $e->getMessage());
        }
        return ['بانتظار الاعتماد', 'warning'];
    }

    return ['غير معروف', 'secondary'];
}
?>