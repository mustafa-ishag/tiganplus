/**
 * LocalFileStore.js
 * ==================
 * تخزين الملفات محلياً باستخدام IndexedDB
 * بديل عن Supabase Storage للعمل المحلي
 */

const DB_NAME = 'wo_files_db';
const DB_VERSION = 1;
const STORE_NAME = 'files';

const LocalFileStore = {
  _db: null,

  async _getDB() {
    if (this._db) return this._db;

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME, { keyPath: 'id' });
        }
      };

      request.onsuccess = (e) => {
        this._db = e.target.result;
        resolve(this._db);
      };

      request.onerror = (e) => {
        reject(new Error('فشل فتح قاعدة البيانات المحلية'));
      };
    });
  },

  /**
   * حفظ ملف محلياً
   * @returns {string} معرف الملف المحلي
   */
  async saveFile(file) {
    const db = await this._getDB();
    const id = `file_${Date.now()}_${Math.random().toString(36).substring(2)}`;
    
    const arrayBuffer = await file.arrayBuffer();

    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      
      store.put({
        id,
        name: file.name,
        type: file.type,
        size: file.size,
        data: arrayBuffer,
        created_at: new Date().toISOString()
      });

      tx.oncomplete = () => resolve(id);
      tx.onerror = () => reject(new Error('فشل حفظ الملف'));
    });
  },

  /**
   * جلب ملف وإنشاء رابط عرض
   * @returns {string} Object URL للملف
   */
  async getFileURL(fileId) {
    if (!fileId || fileId.startsWith('http')) return fileId;
    
    const db = await this._getDB();

    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const request = store.get(fileId);

      request.onsuccess = () => {
        if (request.result) {
          const blob = new Blob([request.result.data], { type: request.result.type });
          resolve(URL.createObjectURL(blob));
        } else {
          resolve('');
        }
      };
      request.onerror = () => resolve('');
    });
  },

  /**
   * حذف ملف
   */
  async deleteFile(fileId) {
    if (!fileId || fileId.startsWith('http')) return;
    
    const db = await this._getDB();

    return new Promise((resolve) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      store.delete(fileId);
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
    });
  }
};

export default LocalFileStore;
