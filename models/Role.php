<?php
/**
 * نموذج الأدوار
 * Role Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/BaseModel.php';

class Role extends BaseModel {
    protected $table = 'roles';
    
    /**
     * البحث عن دور بواسطة الاسم
     */
    public function findByName($name) {
        return $this->findOneWhere('name = ?', [$name]);
    }
    
    /**
     * إنشاء دور جديد
     */
    public function createRole($data) {
        // التحقق من عدم وجود الاسم
        if ($this->findByName($data['name'])) {
            return ['success' => false, 'message' => 'اسم الدور موجود بالفعل'];
        }
        
        $data['created_at'] = getCurrentDateTime();
        
        try {
            $roleId = $this->insert($data);
            logActivity('create_role', "تم إنشاء دور جديد: {$data['name']}");
            return ['success' => true, 'role_id' => $roleId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إنشاء الدور'];
        }
    }
    
    /**
     * تحديث بيانات الدور
     */
    public function updateRole($id, $data) {
        $data['updated_at'] = getCurrentDateTime();
        
        try {
            $result = $this->update($id, $data);
            if ($result) {
                logActivity('update_role', "تم تحديث الدور ID: $id");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        }
    }
    
    /**
     * حذف دور
     */
    public function deleteRole($id) {
        // التحقق من عدم وجود مستخدمين مرتبطين بهذا الدور
        $userCount = fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
        if ($userCount > 0) {
            return ['success' => false, 'message' => 'لا يمكن حذف الدور لوجود مستخدمين مرتبطين به'];
        }
        
        try {
            $role = $this->findById($id);
            if (!$role) {
                return ['success' => false, 'message' => 'الدور غير موجود'];
            }
            
            // حذف صلاحيات الدور
            executeQuery("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
            
            $result = $this->delete($id);
            if ($result) {
                logActivity('delete_role', "تم حذف الدور: {$role['name']}");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في حذف الدور'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حذف الدور'];
        }
    }
    
    /**
     * الحصول على جميع الأدوار النشطة
     */
    public function getActiveRoles() {
        return $this->findWhere('status = ?', ['active']);
    }
    
    /**
     * الحصول على صلاحيات الدور
     */
    public function getRolePermissions($roleId) {
        $sql = "
            SELECT p.* 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            WHERE rp.role_id = ?
            ORDER BY p.module, p.name
        ";
        return fetchAll($sql, [$roleId]);
    }
    
    /**
     * تحديث صلاحيات الدور
     */
    public function updateRolePermissions($roleId, $permissionIds) {
        try {
            $this->db->beginTransaction();
            
            // حذف الصلاحيات الحالية
            executeQuery("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            
            // إضافة الصلاحيات الجديدة
            if (!empty($permissionIds)) {
                $stmt = $this->db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([$roleId, $permissionId]);
                }
            }
            
            $this->db->commit();
            
            $role = $this->findById($roleId);
            logActivity('update_role_permissions', "تم تحديث صلاحيات الدور: {$role['name']}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'فشل في تحديث الصلاحيات'];
        }
    }
    
    /**
     * الحصول على دور مع عدد المستخدمين
     */
    public function getRoleWithUserCount($id) {
        $sql = "
            SELECT r.*, COUNT(u.id) as user_count 
            FROM roles r 
            LEFT JOIN users u ON r.id = u.role_id 
            WHERE r.id = ? 
            GROUP BY r.id
        ";
        return fetchOne($sql, [$id]);
    }
    
    /**
     * الحصول على جميع الأدوار مع عدد المستخدمين
     */
    public function getAllRolesWithUserCount() {
        $sql = "
            SELECT r.*, COUNT(u.id) as user_count 
            FROM roles r 
            LEFT JOIN users u ON r.id = u.role_id 
            GROUP BY r.id 
            ORDER BY r.created_at DESC
        ";
        return fetchAll($sql);
    }
    
    /**
     * تفعيل/إلغاء تفعيل الدور
     */
    public function toggleRoleStatus($id) {
        $role = $this->findById($id);
        if (!$role) {
            return ['success' => false, 'message' => 'الدور غير موجود'];
        }
        
        $newStatus = $role['status'] === 'active' ? 'inactive' : 'active';
        $result = $this->update($id, ['status' => $newStatus, 'updated_at' => getCurrentDateTime()]);
        
        if ($result) {
            $action = $newStatus === 'active' ? 'تفعيل' : 'إلغاء تفعيل';
            logActivity('toggle_role_status', "$action الدور: {$role['name']}");
            return ['success' => true, 'status' => $newStatus];
        }
        
        return ['success' => false, 'message' => 'فشل في تغيير حالة الدور'];
    }
    
    /**
     * البحث في الأدوار
     */
    public function searchRoles($searchTerm, $status = null) {
        $conditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $conditions[] = "(name LIKE ? OR description LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }
        
        if ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "
            SELECT r.*, COUNT(u.id) as user_count 
            FROM roles r 
            LEFT JOIN users u ON r.id = u.role_id 
            $whereClause
            GROUP BY r.id 
            ORDER BY r.created_at DESC
        ";
        
        return fetchAll($sql, $params);
    }
    
    /**
     * إحصائيات الأدوار
     */
    public function getRoleStats() {
        return [
            'total' => $this->count(),
            'active' => $this->count('status = ?', ['active']),
            'inactive' => $this->count('status = ?', ['inactive'])
        ];
    }
    
    /**
     * التحقق من وجود صلاحية في الدور
     */
    public function hasPermission($roleId, $permissionName) {
        $sql = "
            SELECT COUNT(*) 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ? AND p.name = ?
        ";
        return fetchColumn($sql, [$roleId, $permissionName]) > 0;
    }
    
    /**
     * نسخ صلاحيات من دور إلى آخر
     */
    public function copyPermissions($fromRoleId, $toRoleId) {
        try {
            $this->db->beginTransaction();
            
            // حذف الصلاحيات الحالية للدور المستهدف
            executeQuery("DELETE FROM role_permissions WHERE role_id = ?", [$toRoleId]);
            
            // نسخ الصلاحيات
            $sql = "
                INSERT INTO role_permissions (role_id, permission_id) 
                SELECT ?, permission_id 
                FROM role_permissions 
                WHERE role_id = ?
            ";
            executeQuery($sql, [$toRoleId, $fromRoleId]);
            
            $this->db->commit();
            
            $fromRole = $this->findById($fromRoleId);
            $toRole = $this->findById($toRoleId);
            logActivity('copy_role_permissions', "تم نسخ صلاحيات من {$fromRole['name']} إلى {$toRole['name']}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'فشل في نسخ الصلاحيات'];
        }
    }
}
?>
