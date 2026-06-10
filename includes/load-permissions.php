<?php
/**
 * تحميل صلاحيات المستخدم في الجلسة
 */

// التأكد من وجود جلسة نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * تحميل صلاحيات المستخدم من قاعدة البيانات
 */
function loadUserPermissions($userId) {
    try {
        $db = getDB();

        // جلب صلاحيات المستخدم من جدول role_permissions (من خلال الدور)
        $stmt = $db->prepare("
            SELECT DISTINCT p.name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");

        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // إذا لم توجد صلاحيات من جدول user_roles، جرب من جدول users مباشرة
        if (empty($permissions)) {
            $stmt = $db->prepare("
                SELECT DISTINCT p.name
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = (SELECT role_id FROM users WHERE id = ?)
            ");

            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // أضف الصلاحيات المباشرة للمستخدم من جدول user_permissions
        $stmt = $db->prepare("
            SELECT DISTINCT p.name
            FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");

        $stmt->execute([$userId]);
        $directPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // دمج الصلاحيات من الدور والصلاحيات المباشرة
        $permissions = array_unique(array_merge($permissions, $directPermissions));

        // حفظ الصلاحيات في الجلسة
        $_SESSION['permissions'] = $permissions;

        return $permissions;

    } catch (Exception $e) {
        error_log("خطأ في تحميل الصلاحيات: " . $e->getMessage());
        $_SESSION['permissions'] = [];
        return [];
    }
}

/**
 * تحديث صلاحيات المستخدم الحالي
 */
function refreshUserPermissions() {
    if (isset($_SESSION['user_id'])) {
        return loadUserPermissions($_SESSION['user_id']);
    }
    return [];
}

/**
 * التحقق من وجود صلاحية معينة
 */
function checkPermission($permission) {
    // إذا لم تكن الصلاحيات محملة، حاول تحميلها
    if (!isset($_SESSION['permissions']) && isset($_SESSION['user_id'])) {
        loadUserPermissions($_SESSION['user_id']);
    }
    
    return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}

/**
 * منح صلاحية للمستخدم
 */
function grantPermission($userId, $permissionName, $grantedBy) {
    try {
        $db = getDB();
        
        // البحث عن الصلاحية
        $permStmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $permStmt->execute([$permissionName]);
        $permissionId = $permStmt->fetchColumn();
        
        if (!$permissionId) {
            throw new Exception("الصلاحية غير موجودة: $permissionName");
        }
        
        // منح الصلاحية
        $grantStmt = $db->prepare("
            INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) 
            VALUES (?, ?, ?)
        ");
        
        $grantStmt->execute([$userId, $permissionId, $grantedBy]);
        
        // تحديث الصلاحيات في الجلسة إذا كان المستخدم الحالي
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            refreshUserPermissions();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("خطأ في منح الصلاحية: " . $e->getMessage());
        return false;
    }
}

/**
 * إلغاء صلاحية من المستخدم
 */
function revokePermission($userId, $permissionName) {
    try {
        $db = getDB();
        
        // البحث عن الصلاحية
        $permStmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $permStmt->execute([$permissionName]);
        $permissionId = $permStmt->fetchColumn();
        
        if (!$permissionId) {
            throw new Exception("الصلاحية غير موجودة: $permissionName");
        }
        
        // إلغاء الصلاحية
        $revokeStmt = $db->prepare("
            DELETE FROM user_permissions 
            WHERE user_id = ? AND permission_id = ?
        ");
        
        $revokeStmt->execute([$userId, $permissionId]);
        
        // تحديث الصلاحيات في الجلسة إذا كان المستخدم الحالي
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            refreshUserPermissions();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("خطأ في إلغاء الصلاحية: " . $e->getMessage());
        return false;
    }
}

/**
 * جلب جميع صلاحيات المستخدم
 */
function getUserPermissions($userId) {
    try {
        $db = getDB();

        // جلب صلاحيات الدور والصلاحيات المباشرة
        $stmt = $db->prepare("
            SELECT DISTINCT
                p.name,
                p.description,
                p.category,
                COALESCE(up.granted_at, rp.created_at) as granted_at,
                CASE WHEN up.user_id IS NOT NULL THEN 'direct' ELSE 'role' END as permission_type
            FROM permissions p
            LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ?
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN user_roles ur ON rp.role_id = ur.role_id AND ur.user_id = ?
            WHERE up.user_id = ? OR ur.user_id = ?
            ORDER BY p.category, p.name
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("خطأ في جلب صلاحيات المستخدم: " . $e->getMessage());
        return [];
    }
}

/**
 * التحقق من صلاحيات متعددة (يجب أن تكون جميعها موجودة)
 */
function hasAllPermissions($permissions) {
    foreach ($permissions as $permission) {
        if (!checkPermission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * التحقق من صلاحيات متعددة (يكفي وجود واحدة منها)
 */
function hasAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (checkPermission($permission)) {
            return true;
        }
    }
    return false;
}

// تحميل الصلاحيات تلقائياً إذا كان المستخدم مسجل دخول
if (isset($_SESSION['user_id']) && !isset($_SESSION['permissions'])) {
    loadUserPermissions($_SESSION['user_id']);
}
?>
