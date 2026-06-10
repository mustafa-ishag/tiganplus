/**
 * WorkOrderModel.js
 * ==================
 * طبقة النموذج لجدول work_orders
 * مسؤولة عن إنشاء وجلب أوامر العمل باستخدام PHP API
 */

const API_URL = 'api/backend.php';

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

const WorkOrderModel = {
  /**
   * إنشاء أمر عمل جديد
   */
  async create(woNumber, woType, location, department) {
    return await apiCall('create_work_order', { 
      wo_number: woNumber, 
      wo_type: woType, 
      location, 
      department 
    });
  },

  /**
   * جلب جميع أوامر العمل مرتبة من الأحدث
   */
  async fetchAll() {
    return await apiCall('fetch_work_orders');
  },

  /**
   * جلب أمر عمل بحسب المعرف
   */
  async getById(id) {
    return await apiCall('get_work_order', { id });
  },

  /**
   * حذف أمر عمل
   */
  async deleteById(id) {
    return await apiCall('delete_work_order', { id });
  },

  /**
   * تحديث حالة أمر العمل
   */
  async updateStatus(id, status) {
    return await apiCall('update_work_order_status', { id, status });
  },

  /**
   * تحديث مكان أمر العمل
   */
  async updateLocation(id, location) {
    return await apiCall('update_work_order_location', { id, location });
  }
};

export default WorkOrderModel;
