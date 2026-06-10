/**
 * MaterialModel.js
 * ==================
 * طبقة النموذج لجدول master_materials
 * مسؤولة عن جميع عمليات CRUD والبحث والاستيراد/التصدير باستخدام PHP API
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

const MaterialModel = {
  /**
   * جلب جميع المواد من الدليل الرئيسي
   */
  async fetchAll() {
    return await apiCall('fetch_materials');
  },

  /**
   * البحث الديناميكي بحسب رقم البند (Autocomplete)
   */
  async searchByItemNumber(query) {
    return await apiCall('search_materials', { query });
  },

  /**
   * جلب مادة واحدة بحسب رقم البند الدقيق
   */
  async getByItemNumber(itemNumber) {
    return await apiCall('get_material_by_item', { item_number: itemNumber });
  },

  /**
   * إدراج أو تحديث مجموعة من المواد (للاستيراد من CSV)
   */
  async upsertBatch(materials) {
    return await apiCall('upsert_materials', { materials });
  },

  /**
   * حذف مادة بحسب المعرف
   */
  async deleteById(id) {
    return await apiCall('delete_material', { id });
  }
};

export default MaterialModel;
