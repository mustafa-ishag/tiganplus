/**
 * AttachmentModel.js
 * ==================
 * طبقة النموذج لجدول wo_attachments
 * يتم تخزين الملفات محلياً في IndexedDB
 * ويتم تسجيل البيانات الوصفية فقط في MySQL عبر PHP API
 */

import LocalFileStore from './LocalFileStore.js';

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

const AttachmentModel = {
  /**
   * رفع ملف PDF وتخزينه محلياً
   */
  async upload(woId, file) {
    try {
      // حفظ الملف محلياً في IndexedDB
      const fileId = await LocalFileStore.saveFile(file);

      // تسجيل في قاعدة البيانات (البيانات الوصفية فقط)
      const response = await apiCall('create_attachment', {
        wo_id: woId,
        file_name: file.name,
        file_url: fileId,
        file_size: file.size
      });

      return { data: response.data, error: response.error };
    } catch (err) {
      return { data: null, error: err };
    }
  },

  /**
   * جلب مرفقات أمر عمل
   */
  async fetchByWorkOrder(woId) {
    return await apiCall('fetch_attachments', { wo_id: woId });
  },

  /**
   * حذف مرفق (من IndexedDB + قاعدة البيانات)
   */
  async deleteById(id) {
    // حذف من قاعدة البيانات وجلب الرابط
    const response = await apiCall('delete_attachment', { id });
    
    if (response.data && response.data.file_url) {
      await LocalFileStore.deleteFile(response.data.file_url);
    }

    return { error: response.error };
  }
};

export default AttachmentModel;
