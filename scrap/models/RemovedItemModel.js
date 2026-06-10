/**
 * RemovedItemModel.js
 * ==================
 * طبقة النموذج لجدول removed_items
 * مسؤولة عن إضافة وجلب المواد المُزالة ورفع الصور باستخدام PHP API
 */

const API_URL = 'api/backend.php';
const UPLOAD_URL = 'api/upload.php';

async function apiCall(action, payload = {}) {
  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, payload })
    });
    return await response.json();
  } catch (error) {
    return { data: null, error };
  }
}

const RemovedItemModel = {
  /**
   * إضافة مادة مُزالة جديدة
   */
  async create(itemData) {
    return await apiCall('create_removed_item', itemData);
  },

  /**
   * جلب جميع المواد المُزالة لأمر عمل محدد
   */
  async fetchByWorkOrder(woId) {
    return await apiCall('fetch_removed_items', { wo_id: woId });
  },

  /**
   * رفع صورة إلى المجلد المحلي
   * @param {File} file - ملف الصورة
   */
  async uploadImage(file) {
    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch(UPLOAD_URL, {
        method: 'POST',
        body: formData
      });
      const result = await response.json();
      return { url: result.url, error: result.error };
    } catch (error) {
      return { url: null, error };
    }
  },

  /**
   * حذف مادة مُزالة بحسب المعرف
   */
  async deleteById(id) {
    return await apiCall('delete_removed_item', { id });
  },

  /**
   * تحديث مادة مُزالة
   */
  async update(id, updates) {
    return await apiCall('update_removed_item', { id, updates });
  },

  /**
   * تبديل حالة الإكمال
   */
  async toggleComplete(id, isCompleted) {
    return await apiCall('toggle_complete_removed_item', { id, is_completed: isCompleted ? 1 : 0 });
  }
};

export default RemovedItemModel;
