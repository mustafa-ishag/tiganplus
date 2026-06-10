<?php
/**
 * النموذج الأساسي
 * Base Model Class
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

abstract class BaseModel {
    protected $table;
    protected $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * البحث عن سجل واحد بشرط
     */
    public function findOneWhere($condition, $params = []) {
        $sql = "SELECT * FROM {$this->table} WHERE {$condition} LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * البحث عن جميع السجلات بشرط
     */
    public function findWhere($condition, $params = []) {
        $sql = "SELECT * FROM {$this->table} WHERE {$condition}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * البحث عن سجل بواسطة المعرف
     */
    public function findById($id) {
        return $this->findOneWhere('id = ?', [$id]);
    }
    
    /**
     * الحصول على جميع السجلات
     */
    public function findAll($orderBy = 'id DESC') {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * إدراج سجل جديد
     */
    public function insert($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * تحديث سجل
     */
    public function update($id, $data) {
        $setClause = implode(', ', array_map(function($key) {
            return "{$key} = :{$key}";
        }, array_keys($data)));
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE id = :id";
        $data['id'] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * حذف سجل
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * عد السجلات
     */
    public function count($condition = '', $params = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if (!empty($condition)) {
            $sql .= " WHERE {$condition}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * التحقق من وجود سجل
     */
    public function exists($condition, $params = []) {
        return $this->count($condition, $params) > 0;
    }
    
    /**
     * تنفيذ استعلام مخصص
     */
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * الحصول على سجل واحد من استعلام مخصص
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على جميع السجلات من استعلام مخصص
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على قيمة واحدة من استعلام مخصص
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * بدء معاملة
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * تأكيد المعاملة
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * التراجع عن المعاملة
     */
    public function rollback() {
        return $this->db->rollback();
    }
}
?>
