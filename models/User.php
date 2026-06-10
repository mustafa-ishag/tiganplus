<?php
/**
 * نموذج المستخدم
 * User Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    
    /**
     * البحث عن مستخدم بواسطة اسم المستخدم
     */
    public function findByUsername($username) {
        return $this->findOneWhere('username = ?', [$username]);
    }
    
    /**
     * البحث عن مستخدم بواسطة البريد الإلكتروني
     */
    public function findByEmail($email) {
        return $this->findOneWhere('email = ?', [$email]);
    }
    
    /**
     * البحث عن مستخدم بواسطة remember token
     */
    public function findByRememberToken($token) {
        return $this->findOneWhere('remember_token = ? AND status = ?', [$token, 'active']);
    }
    
    /**
     * إنشاء مستخدم جديد
     */
    public function createUser($data) {
        // التحقق من عدم وجود اسم المستخدم
        if ($this->findByUsername($data['username'])) {
            return ['success' => false, 'message' => 'اسم المستخدم موجود بالفعل'];
        }
        
        // التحقق من عدم وجود البريد الإلكتروني
        if (!empty($data['email']) && $this->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'البريد الإلكتروني موجود بالفعل'];
        }
        
        // تشفير كلمة المرور
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = getCurrentDateTime();
        
        try {
            $userId = $this->insert($data);
            logActivity('create_user', "تم إنشاء مستخدم جديد: {$data['username']}");
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إنشاء المستخدم'];
        }
    }
    
    /**
     * تحديث بيانات المستخدم
     */
    public function updateUser($id, $data) {
        // إزالة كلمة المرور إذا كانت فارغة
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $data['updated_at'] = getCurrentDateTime();
        
        try {
            $result = $this->update($id, $data);
            if ($result) {
                logActivity('update_user', "تم تحديث بيانات المستخدم ID: $id");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        }
    }
    
    /**
     * حذف مستخدم
     */
    public function deleteUser($id) {
        // منع حذف مدير النظام
        if ($id == 1) {
            return ['success' => false, 'message' => 'لا يمكن حذف مدير النظام'];
        }
        
        try {
            $user = $this->findById($id);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            $result = $this->delete($id);
            if ($result) {
                logActivity('delete_user', "تم حذف المستخدم: {$user['username']}");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في حذف المستخدم'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حذف المستخدم'];
        }
    }
    
    /**
     * الحصول على جميع المستخدمين مع بيانات الأدوار والفروع
     */
    public function getAllUsersWithDetails() {
        $sql = "
            SELECT u.*, r.name as role_name, b.name as branch_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN branches b ON u.branch_id = b.id 
            ORDER BY u.created_at DESC
        ";
        return fetchAll($sql);
    }
    
    /**
     * الحصول على مستخدم مع التفاصيل
     */
    public function getUserWithDetails($id) {
        $sql = "
            SELECT u.*, r.name as role_name, b.name as branch_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN branches b ON u.branch_id = b.id 
            WHERE u.id = ?
        ";
        return fetchOne($sql, [$id]);
    }
    
    /**
     * الحصول على صلاحيات المستخدم
     */
    public function getUserPermissions($userId) {
        $sql = "
            SELECT p.name 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            JOIN users u ON u.role_id = rp.role_id 
            WHERE u.id = ?
        ";
        return fetchAll($sql, [$userId]);
    }
    
    /**
     * تحديث آخر تسجيل دخول
     */
    public function updateLastLogin($id) {
        return $this->update($id, ['last_login' => getCurrentDateTime()]);
    }
    
    /**
     * تحديث remember token
     */
    public function updateRememberToken($id, $token) {
        return $this->update($id, ['remember_token' => $token]);
    }
    
    /**
     * تغيير كلمة المرور
     */
    public function changePassword($id, $oldPassword, $newPassword) {
        $user = $this->findById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }
        
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $this->update($id, ['password' => $hashedPassword, 'updated_at' => getCurrentDateTime()]);
        
        if ($result) {
            logActivity('change_password', "تم تغيير كلمة المرور للمستخدم ID: $id");
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'فشل في تغيير كلمة المرور'];
    }
    
    /**
     * تفعيل/إلغاء تفعيل المستخدم
     */
    public function toggleUserStatus($id) {
        $user = $this->findById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }
        
        // منع تعطيل مدير النظام
        if ($id == 1) {
            return ['success' => false, 'message' => 'لا يمكن تعطيل مدير النظام'];
        }
        
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $result = $this->update($id, ['status' => $newStatus, 'updated_at' => getCurrentDateTime()]);
        
        if ($result) {
            $action = $newStatus === 'active' ? 'تفعيل' : 'إلغاء تفعيل';
            logActivity('toggle_user_status', "$action المستخدم: {$user['username']}");
            return ['success' => true, 'status' => $newStatus];
        }
        
        return ['success' => false, 'message' => 'فشل في تغيير حالة المستخدم'];
    }
    
    /**
     * البحث في المستخدمين
     */
    public function searchUsers($searchTerm, $branchId = null, $roleId = null, $status = null) {
        $conditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $conditions[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }
        
        if ($branchId) {
            $conditions[] = "u.branch_id = ?";
            $params[] = $branchId;
        }
        
        if ($roleId) {
            $conditions[] = "u.role_id = ?";
            $params[] = $roleId;
        }
        
        if ($status) {
            $conditions[] = "u.status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "
            SELECT u.*, r.name as role_name, b.name as branch_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN branches b ON u.branch_id = b.id 
            $whereClause
            ORDER BY u.created_at DESC
        ";
        
        return fetchAll($sql, $params);
    }
    
    /**
     * إحصائيات المستخدمين
     */
    public function getUserStats() {
        return [
            'total' => $this->count(),
            'active' => $this->count('status = ?', ['active']),
            'inactive' => $this->count('status = ?', ['inactive']),
            'suspended' => $this->count('status = ?', ['suspended'])
        ];
    }
}
?>
