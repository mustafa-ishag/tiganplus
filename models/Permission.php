<?php
/**
 * نموذج الصلاحيات
 * Permission Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class Permission extends BaseModel {
    protected $table = 'permissions';
    
    /**
     * البحث عن صلاحية بواسطة الاسم
     */
    public function findByName($name) {
        return $this->findOneWhere('name = ?', [$name]);
    }
    
    /**
     * إنشاء صلاحية جديدة
     */
    public function createPermission($data) {
        // التحقق من عدم وجود الاسم
        if ($this->findByName($data['name'])) {
            return ['success' => false, 'message' => 'اسم الصلاحية موجود بالفعل'];
        }
        
        $data['created_at'] = getCurrentDateTime();
        
        try {
            $permissionId = $this->insert($data);
            logActivity('create_permission', "تم إنشاء صلاحية جديدة: {$data['name']}");
            return ['success' => true, 'permission_id' => $permissionId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إنشاء الصلاحية'];
        }
    }
    
    /**
     * تحديث بيانات الصلاحية
     */
    public function updatePermission($id, $data) {
        try {
            $result = $this->update($id, $data);
            if ($result) {
                logActivity('update_permission', "تم تحديث الصلاحية ID: $id");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        }
    }
    
    /**
     * حذف صلاحية
     */
    public function deletePermission($id) {
        // التحقق من عدم وجود أدوار مرتبطة بهذه الصلاحية
        $roleCount = fetchColumn("SELECT COUNT(*) FROM role_permissions WHERE permission_id = ?", [$id]);
        if ($roleCount > 0) {
            return ['success' => false, 'message' => 'لا يمكن حذف الصلاحية لوجود أدوار مرتبطة بها'];
        }
        
        try {
            $permission = $this->findById($id);
            if (!$permission) {
                return ['success' => false, 'message' => 'الصلاحية غير موجودة'];
            }
            
            $result = $this->delete($id);
            if ($result) {
                logActivity('delete_permission', "تم حذف الصلاحية: {$permission['name']}");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في حذف الصلاحية'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حذف الصلاحية'];
        }
    }
    
    /**
     * الحصول على جميع الصلاحيات مجمعة حسب الوحدة
     */
    public function getPermissionsByModule() {
        $sql = "SELECT * FROM permissions ORDER BY module, name";
        $permissions = fetchAll($sql);
        
        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['module']][] = $permission;
        }
        
        return $grouped;
    }
    
    /**
     * الحصول على صلاحيات وحدة معينة
     */
    public function getModulePermissions($module) {
        return $this->findWhere('module = ?', [$module]);
    }
    
    /**
     * الحصول على جميع الوحدات
     */
    public function getAllModules() {
        $sql = "SELECT DISTINCT module FROM permissions ORDER BY module";
        return fetchAll($sql);
    }
    
    /**
     * البحث في الصلاحيات
     */
    public function searchPermissions($searchTerm, $module = null) {
        $conditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $conditions[] = "(name LIKE ? OR description LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }
        
        if ($module) {
            $conditions[] = "module = ?";
            $params[] = $module;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM permissions $whereClause ORDER BY module, name";
        
        return fetchAll($sql, $params);
    }
    
    /**
     * الحصول على الأدوار التي تملك صلاحية معينة
     */
    public function getPermissionRoles($permissionId) {
        $sql = "
            SELECT r.* 
            FROM roles r 
            JOIN role_permissions rp ON r.id = rp.role_id 
            WHERE rp.permission_id = ?
            ORDER BY r.name
        ";
        return fetchAll($sql, [$permissionId]);
    }
    
    /**
     * إحصائيات الصلاحيات
     */
    public function getPermissionStats() {
        $moduleStats = [];
        $modules = $this->getAllModules();
        
        foreach ($modules as $module) {
            $moduleStats[$module['module']] = $this->count('module = ?', [$module['module']]);
        }
        
        return [
            'total' => $this->count(),
            'modules' => count($modules),
            'by_module' => $moduleStats
        ];
    }
    
    /**
     * إنشاء صلاحيات افتراضية لوحدة جديدة
     */
    public function createModulePermissions($module, $moduleName) {
        $defaultPermissions = [
            ['name' => "view_$module", 'description' => "عرض $moduleName", 'module' => $module],
            ['name' => "add_$module", 'description' => "إضافة $moduleName", 'module' => $module],
            ['name' => "edit_$module", 'description' => "تعديل $moduleName", 'module' => $module],
            ['name' => "delete_$module", 'description' => "حذف $moduleName", 'module' => $module],
        ];
        
        $createdPermissions = [];
        
        foreach ($defaultPermissions as $permission) {
            if (!$this->findByName($permission['name'])) {
                $result = $this->createPermission($permission);
                if ($result['success']) {
                    $createdPermissions[] = $permission['name'];
                }
            }
        }
        
        if (!empty($createdPermissions)) {
            logActivity('create_module_permissions', "تم إنشاء صلاحيات الوحدة $module: " . implode(', ', $createdPermissions));
        }
        
        return $createdPermissions;
    }
    
    /**
     * التحقق من صلاحية المستخدم
     */
    public function checkUserPermission($userId, $permissionName) {
        $sql = "
            SELECT COUNT(*)
            FROM permissions p
            WHERE p.name = ?
            AND (
                -- صلاحيات من جدول user_roles
                EXISTS (
                    SELECT 1 FROM user_roles ur
                    JOIN role_permissions rp ON ur.role_id = rp.role_id
                    WHERE ur.user_id = ? AND rp.permission_id = p.id
                )
                -- صلاحيات من جدول users مباشرة
                OR EXISTS (
                    SELECT 1 FROM users u
                    JOIN role_permissions rp ON u.role_id = rp.role_id
                    WHERE u.id = ? AND rp.permission_id = p.id AND u.status = 'active'
                )
                -- صلاحيات مباشرة من جدول user_permissions
                OR EXISTS (
                    SELECT 1 FROM user_permissions up
                    WHERE up.user_id = ? AND up.permission_id = p.id
                )
            )
        ";
        return fetchColumn($sql, [$permissionName, $userId, $userId, $userId]) > 0;
    }
    
    /**
     * الحصول على جميع صلاحيات المستخدم
     */
    public function getUserPermissions($userId) {
        $sql = "
            SELECT DISTINCT p.name, p.description, p.module
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN user_roles ur ON rp.role_id = ur.role_id
            LEFT JOIN user_permissions up ON p.id = up.permission_id
            WHERE (ur.user_id = ? OR up.user_id = ?)
            AND EXISTS (SELECT 1 FROM users WHERE id = ? AND status = 'active')
            ORDER BY p.module, p.name
        ";
        return fetchAll($sql, [$userId, $userId, $userId]);
    }
    
    /**
     * تصدير الصلاحيات
     */
    public function exportPermissions() {
        $permissions = $this->getPermissionsByModule();
        
        $export = [
            'export_date' => getCurrentDateTime(),
            'total_permissions' => $this->count(),
            'permissions' => $permissions
        ];
        
        return $export;
    }
    
    /**
     * استيراد الصلاحيات
     */
    public function importPermissions($data) {
        try {
            $this->db->beginTransaction();
            
            $imported = 0;
            $skipped = 0;
            
            foreach ($data['permissions'] as $module => $modulePermissions) {
                foreach ($modulePermissions as $permission) {
                    if (!$this->findByName($permission['name'])) {
                        $this->insert([
                            'name' => $permission['name'],
                            'description' => $permission['description'],
                            'module' => $permission['module'],
                            'created_at' => getCurrentDateTime()
                        ]);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                }
            }
            
            $this->db->commit();
            
            logActivity('import_permissions', "تم استيراد $imported صلاحية، تم تخطي $skipped صلاحية موجودة");
            
            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'فشل في استيراد الصلاحيات'];
        }
    }
}
?>
