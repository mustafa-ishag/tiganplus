/**
 * AppController.js
 * ==================
 * طبقة المتحكم (Controller) - صلة الوصل بين Model و View
 * يستقبل الأوامر من الـ View، ينفذ العمليات في الـ Models،
 * ثم يعيد إرسال البيانات للـ View لعرضها
 * 
 * نقطة البداية الرئيسية للتطبيق
 */

import AppView from '../views/AppView.js';
import MaterialModel from '../models/MaterialModel.js';
import WorkOrderModel from '../models/WorkOrderModel.js';
import RemovedItemModel from '../models/RemovedItemModel.js';
import AttachmentModel from '../models/AttachmentModel.js';
import LocalFileStore from '../models/LocalFileStore.js';

const AppController = {
  // أمر العمل المختار حالياً
  _activeWorkOrderId: null,

  // رابط سيرفر تحويل PDF الخاص
  _pdfServerUrl: 'https://pdf-converter-69cg.onrender.com',

  /**
   * تهيئة التطبيق - نقطة البداية
   */
  async init() {
    // تهيئة الـ View
    AppView.init();

    // ربط الأحداث بدوال المعالجة
    AppView.bindSubmitWorkOrder(this.handleSubmitWorkOrder.bind(this));
    AppView.bindAddRemovedItem(this.handleAddRemovedItem.bind(this));
    AppView.bindSelectWorkOrder(this.handleSelectWorkOrder.bind(this));
    AppView.bindDeleteWorkOrder(this.handleDeleteWorkOrder.bind(this));
    AppView.bindItemNumberSearch(this.handleItemNumberSearch.bind(this));
    AppView.bindImportCSV(this.handleImportCSV.bind(this));
    AppView.bindExportCSV(this.handleExportCSV.bind(this));
    AppView.bindDownloadTemplate(this.handleDownloadTemplate.bind(this));
    AppView.bindAddMaterial(this.handleAddMaterial.bind(this));
    AppView.bindExportMDR(this.handleExportMDR.bind(this));
    AppView.bindExportMDRPdf(this.handleExportMDRPdf.bind(this));
    AppView.bindExportIR(this.handleExportIR.bind(this));
    if (AppView.bindExportIRPdf) {
      AppView.bindExportIRPdf(this.handleExportIRPdf.bind(this));
    }
    AppView.bindExportFATRA(this.handleExportFATRA.bind(this));
    if (AppView.bindExportFATRAPdf) {
      AppView.bindExportFATRAPdf(this.handleExportFATRAPdf.bind(this));
    }
    AppView.bindExportMRR(this.handleExportMRR.bind(this));

    // Add MRR PDF export binding if view supports it
    if (AppView.bindExportMRRPdf) {
      AppView.bindExportMRRPdf(this.handleExportMRRPdf.bind(this));
    }

    AppView.bindDeleteRemovedItem(this.handleDeleteRemovedItem.bind(this));
    AppView.bindEditRemovedItem(this.handleEditRemovedItem.bind(this));
    AppView.bindToggleComplete(this.handleToggleComplete.bind(this));
    AppView.bindUploadAttachment(this.handleUploadAttachment.bind(this));
    AppView.bindDeleteAttachment(this.handleDeleteAttachment.bind(this));
    AppView.bindEditLocation(this.handleEditLocation.bind(this));
    AppView.bindDeleteWorkOrder(this.handleDeleteWorkOrder.bind(this));

    // تفعيل البحث والفلترة
    AppView.setupWoSearchAndFilter();

    // تحميل البيانات الأولية
    await this.loadWorkOrders();
    await this.loadMaterials();
  },

  // ======================================
  // أوامر العمل
  // ======================================

  /**
   * تحميل وعرض جميع أوامر العمل
   */
  async loadWorkOrders() {
    const { data, error } = await WorkOrderModel.fetchAll();
    if (error) {
      AppView.showNotification('خطأ في تحميل أوامر العمل: ' + error.message, 'error');
      return;
    }
    AppView.renderWorkOrders(data);
  },

  /**
   * معالجة إنشاء أمر عمل جديد
   * @param {string} woNumber - رقم أمر العمل
   * @param {string} woType - نوع أمر العمل
   */
  async handleSubmitWorkOrder(woNumber, woType, woLocation, woDepartment) {
    // التحقق من صحة المدخلات
    if (!/^\d{9}$/.test(woNumber)) {
      AppView.showNotification('رقم أمر العمل يجب أن يكون 9 أرقام بالضبط', 'error');
      return;
    }
    if (!/^\d{3}$/.test(woType)) {
      AppView.showNotification('نوع أمر العمل يجب أن يكون 3 أرقام بالضبط', 'error');
      return;
    }
    if (!woDepartment) {
      AppView.showNotification('الرجاء اختيار القسم', 'error');
      return;
    }

    AppView.showLoading();
    const { data, error } = await WorkOrderModel.create(woNumber, woType, woLocation, woDepartment);
    AppView.hideLoading();

    if (error) {
      if (error.code === '23505') {
        AppView.showNotification('رقم أمر العمل موجود مسبقاً!', 'error');
      } else {
        AppView.showNotification('خطأ في إنشاء أمر العمل: ' + error.message, 'error');
      }
      return;
    }

    AppView.showNotification('تم إنشاء أمر العمل بنجاح ✅', 'success');
    AppView.resetWoForm();
    await this.loadWorkOrders();
  },

  /**
   * معالجة اختيار أمر عمل
   * @param {string} woId - معرف أمر العمل
   */
  async handleSelectWorkOrder(woId) {
    this._activeWorkOrderId = woId;

    const { data: order, error } = await WorkOrderModel.getById(woId);
    if (error) {
      AppView.showNotification('خطأ في جلب أمر العمل', 'error');
      return;
    }

    AppView.setActiveWorkOrder(order);
    await this.loadRemovedItems(woId);
    await this.loadAttachments(woId);
  },

  /**
   * معالجة حذف أمر عمل
   * @param {string} woId - معرف أمر العمل
   */
  async handleDeleteWorkOrder(woId) {
    AppView.showLoading();
    const { error } = await WorkOrderModel.deleteById(woId);
    AppView.hideLoading();

    if (error) {
      AppView.showNotification('خطأ في حذف أمر العمل: ' + error.message, 'error');
      return;
    }

    // إذا كان الأمر المحذوف هو النشط، إخفاء قسم المواد
    if (this._activeWorkOrderId === woId) {
      this._activeWorkOrderId = null;
      AppView.hideRemovedSection();
    }

    AppView.showNotification('تم حذف أمر العمل بنجاح 🗑️', 'success');
    await this.loadWorkOrders();
  },

  /**
   * تعديل مكان أمر العمل
   */
  async handleEditLocation(woId, newLocation) {
    AppView.showLoading();
    const { data, error } = await WorkOrderModel.updateLocation(woId, newLocation);
    AppView.hideLoading();

    if (error) {
      AppView.showNotification('خطأ في تحديث المكان: ' + error.message, 'error');
      return;
    }

    AppView.showNotification('تم تحديث المكان بنجاح ✅', 'success');
    await this.loadWorkOrders();
  },

  // ======================================
  // المواد المُزالة
  // ======================================

  /**
   * تحميل وعرض المواد المُزالة لأمر عمل
   * @param {string} woId - معرف أمر العمل
   */
  async loadRemovedItems(woId) {
    const { data, error } = await RemovedItemModel.fetchByWorkOrder(woId);
    if (error) {
      AppView.showNotification('خطأ في تحميل المواد المُزالة', 'error');
      return;
    }
    AppView.renderRemovedItems(data);
  },

  async loadAttachments(woId) {
    const { data, error } = await AttachmentModel.fetchByWorkOrder(woId);
    if (error) {
      console.error('خطأ في تحميل المرفقات:', error);
      return;
    }
    AppView.renderAttachments(data);
  },

  async handleUploadAttachment(files) {
    if (!this._activeWorkOrderId) {
      AppView.showNotification('يرجى اختيار أمر عمل أولاً', 'error');
      return;
    }
    AppView.showLoading();
    let successCount = 0;
    for (const file of files) {
      const { error } = await AttachmentModel.upload(this._activeWorkOrderId, file);
      if (error) {
        AppView.showNotification(`خطأ في رفع ${file.name}: ${error.message}`, 'error');
      } else {
        successCount++;
      }
    }
    AppView.hideLoading();
    if (successCount > 0) {
      AppView.showNotification(`تم رفع ${successCount} مرفق بنجاح 📄`, 'success');
      await this.loadAttachments(this._activeWorkOrderId);
    }
  },

  async handleDeleteAttachment(attachmentId) {
    const { error } = await AttachmentModel.deleteById(attachmentId);
    if (error) {
      AppView.showNotification('خطأ في حذف المرفق: ' + error.message, 'error');
      return;
    }
    AppView.showNotification('تم حذف المرفق 🗑️', 'success');
    await this.loadAttachments(this._activeWorkOrderId);
  },

  /**
   * معالجة إضافة مادة مُزالة
   * @param {Object} formData - بيانات النموذج
   */
  async handleAddRemovedItem(formData) {
    if (!this._activeWorkOrderId) {
      AppView.showNotification('يرجى اختيار أمر عمل أولاً', 'error');
      return;
    }

    if (!formData.item_number) {
      AppView.showNotification('رقم البند مطلوب', 'error');
      return;
    }

    AppView.showLoading();

    // رفع الصور إن وُجدت
    let imageUrls = [];
    if (formData.imageFiles && formData.imageFiles.length > 0) {
      for (const file of formData.imageFiles) {
        try {
          const { url, error } = await RemovedItemModel.uploadImage(file);
          if (error) throw error;
          if (url) imageUrls.push(url);
        } catch (uploadError) {
          console.error("خطأ في رفع الصورة:", uploadError);
          AppView.showNotification('حدث خطأ في رفع إحدى الصور', 'error');
        }
      }
    }

    // إعداد بيانات المادة
    const itemData = {
      wo_id: this._activeWorkOrderId,
      item_number: formData.item_number,
      assembly_number: formData.assembly_number || null,
      description: formData.description || null,
      unit: formData.unit || null,
      functional_location: formData.functional_location || null,
      equipment: formData.equipment || null,
      capacity_kva: formData.capacity_kva || null,
      manufacturer: formData.manufacturer || null,
      prim_sec_volt: formData.prim_sec_volt || null,
      manufacture_year: formData.manufacture_year,
      serial_number: formData.serial_number || null,
      quantity: formData.quantity || 1,
      status: formData.status,
      disposal_reason: formData.disposal_reason || null,
      material_condition: formData.material_condition || null,
      remarks: formData.remarks || null,
      item_type: formData.item_type || 'تشغيلي',
      image_url: imageUrls
    };

    let result;
    if (this._editingItemId) {
      // وضع التعديل
      if (!imageUrls || imageUrls.length === 0) delete itemData.image_url;
      delete itemData.wo_id;
      result = await RemovedItemModel.update(this._editingItemId, itemData);
    } else {
      // وضع الإضافة
      result = await RemovedItemModel.create(itemData);
    }

    AppView.hideLoading();

    if (result.error) {
      AppView.showNotification('خطأ: ' + result.error.message, 'error');
      return;
    }

    const msg = this._editingItemId ? 'تم تحديث المادة بنجاح ✏️' : 'تم إضافة المادة المُزالة بنجاح ✅';
    AppView.showNotification(msg, 'success');

    // إعادة تعيين وضع التعديل
    this._editingItemId = null;
    const submitBtn = document.querySelector('#removed-form button[type="submit"]');
    submitBtn.innerHTML = '<span>إضافة مادة مُزالة</span><span class="btn-icon-right">➕</span>';
    submitBtn.classList.remove('btn-editing');

    AppView.resetRemovedForm();
    AppView.closeModal('modal-add-removed');
    await this.loadRemovedItems(this._activeWorkOrderId);
  },

  /**
   * حذف مادة مُزالة
   */
  async handleDeleteRemovedItem(itemId) {
    AppView.showLoading();
    const { error } = await RemovedItemModel.deleteById(itemId);
    AppView.hideLoading();

    if (error) {
      AppView.showNotification('خطأ في حذف المادة: ' + error.message, 'error');
      return;
    }

    AppView.showNotification('تم حذف المادة بنجاح 🗑️', 'success');
    await this.loadRemovedItems(this._activeWorkOrderId);
    await this.checkWorkOrderCompletion();
  },

  /**
   * تعديل مادة مُزالة - ملء النموذج بالبيانات
   */
  async handleEditRemovedItem(item) {
    // ملء النموذج بالبيانات الحالية
    const e = AppView.elements;
    e.removedItemNumber.value = item.item_number || '';
    e.removedAssembly.value = item.assembly_number || '';
    e.removedDescription.value = item.description || '';
    e.removedUnit.value = item.unit || '';
    e.removedFunctionalLocation.value = item.functional_location || '';
    e.removedEquipment.value = item.equipment || '';
    e.removedCapacityKva.value = item.capacity_kva || '';
    e.removedManufacturer.value = item.manufacturer || '';
    e.removedPrimSecVolt.value = item.prim_sec_volt || '';
    e.removedYear.value = item.manufacture_year || '';
    e.removedSerial.value = item.serial_number || '';
    e.removedQuantity.value = item.quantity || 1;
    e.removedStatus.value = item.status || 'إرجاع';
    e.removedDisposalReason.value = item.disposal_reason || '';
    e.removedMaterialCondition.value = item.material_condition || '';
    e.removedRemarks.value = item.remarks || '';
    e.removedItemType.value = item.item_type || 'تشغيلي';

    // حفظ معرف المادة للتعديل
    this._editingItemId = item.id;

    // تغيير نص الزر
    const submitBtn = document.querySelector('#removed-form button[type="submit"]');
    submitBtn.innerHTML = '<span>تحديث المادة</span><span class="btn-icon-right">✏️</span>';
    submitBtn.classList.add('btn-editing');

    // فتح modal التعديل
    AppView.elements.modalRemovedTitle.textContent = '✏️ تعديل المادة';
    AppView.openModal('modal-add-removed');
    e.removedItemNumber.focus();
  },

  /**
   * تبديل حالة إكمال مادة
   */
  async handleToggleComplete(itemId, isCompleted) {
    const { error } = await RemovedItemModel.toggleComplete(itemId, isCompleted);
    if (error) {
      AppView.showNotification('خطأ في تحديث الحالة: ' + error.message, 'error');
      return;
    }
    await this.loadRemovedItems(this._activeWorkOrderId);
    await this.checkWorkOrderCompletion();
  },

  /**
   * التحقق من اكتمال جميع المواد وتحديث حالة أمر العمل
   */
  async checkWorkOrderCompletion() {
    if (!this._activeWorkOrderId) return;

    const { data: items } = await RemovedItemModel.fetchByWorkOrder(this._activeWorkOrderId);
    if (!items || items.length === 0) return;

    const allCompleted = items.every(i => i.is_completed);
    const newStatus = allCompleted ? 'مكتمل' : 'جاري';

    await WorkOrderModel.updateStatus(this._activeWorkOrderId, newStatus);
    await this.loadWorkOrders();
  },

  // ======================================
  // البحث الديناميكي (Autocomplete)
  // ======================================

  /**
   * معالجة البحث عن رقم البند
   * @param {string} query - النص المبحوث عنه
   */
  async handleItemNumberSearch(query) {
    const { data, error } = await MaterialModel.searchByItemNumber(query);
    if (error) return;
    AppView.renderAutocompleteSuggestions(data);
  },

  // ======================================
  // الدليل الرئيسي - استيراد وتصدير
  // ======================================

  /**
   * تحميل وعرض جدول الدليل الرئيسي
   */
  async loadMaterials() {
    const { data, error } = await MaterialModel.fetchAll();
    if (error) {
      AppView.showNotification('خطأ في تحميل الدليل الرئيسي', 'error');
      return;
    }
    AppView.renderMaterialsTable(data);
  },

  /**
   * معالجة استيراد ملف CSV للمواد
   * يستخدم PapaParse لتحويل CSV إلى مصفوفة كائنات
   * @param {File} file - ملف CSV
   */
  async handleImportCSV(file) {
    AppView.showLoading();

    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: async (results) => {
        if (results.errors.length > 0) {
          AppView.hideLoading();
          AppView.showNotification('خطأ في قراءة ملف CSV: ' + results.errors[0].message, 'error');
          return;
        }

        // تحويل الأعمدة إلى أسماء الحقول في قاعدة البيانات
        const materials = results.data.map(row => ({
          item_number: (row['item_number'] || row['رقم البند'] || row['Item Number'] || '').toString().trim(),
          assembly_number: (row['assembly_number'] || row['رقم التجميع'] || row['Assembly Number'] || '').toString().trim(),
          description: (row['description'] || row['الوصف'] || row['Description'] || '').toString().trim(),
          unit: (row['unit'] || row['الوحدة'] || row['Unit'] || '').toString().trim()
        })).filter(m => m.item_number); // تجاهل الصفوف بدون رقم بند

        if (materials.length === 0) {
          AppView.hideLoading();
          AppView.showNotification('لم يتم العثور على بيانات صالحة في الملف', 'error');
          return;
        }

        // تقسيم البيانات إلى دُفعات (batches) لتجنب حدود API
        const batchSize = 500;
        let totalUpserted = 0;

        for (let i = 0; i < materials.length; i += batchSize) {
          const batch = materials.slice(i, i + batchSize);
          const { error } = await MaterialModel.upsertBatch(batch);
          if (error) {
            AppView.hideLoading();
            AppView.showNotification('خطأ في استيراد البيانات: ' + error.message, 'error');
            return;
          }
          totalUpserted += batch.length;
        }

        AppView.hideLoading();
        AppView.showNotification(`تم استيراد ${totalUpserted} مادة بنجاح 📥`, 'success');
        await this.loadMaterials();
      },
      error: (error) => {
        AppView.hideLoading();
        AppView.showNotification('خطأ في قراءة الملف: ' + error.message, 'error');
      }
    });
  },

  /**
   * معالجة تصدير الدليل الرئيسي كملف CSV
   * يستخدم PapaParse لتحويل المصفوفة إلى CSV
   */
  async handleExportCSV() {
    AppView.showLoading();
    const { data, error } = await MaterialModel.fetchAll();
    AppView.hideLoading();

    if (error) {
      AppView.showNotification('خطأ في جلب البيانات: ' + error.message, 'error');
      return;
    }

    if (!data || data.length === 0) {
      AppView.showNotification('لا توجد بيانات للتصدير', 'error');
      return;
    }

    // تحويل إلى CSV مع أسماء أعمدة عربية وإنجليزية
    const exportData = data.map(m => ({
      'item_number': m.item_number,
      'assembly_number': m.assembly_number || '',
      'description': m.description || '',
      'unit': m.unit || ''
    }));

    const csv = Papa.unparse(exportData, {
      header: true
    });

    // إضافة BOM لدعم Unicode في Excel
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    // تنزيل الملف
    const link = document.createElement('a');
    link.href = url;
    link.download = `master_materials_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    AppView.showNotification('تم تصدير البيانات بنجاح 📤', 'success');
  },

  // ======================================
  // تنزيل نموذج الاستيراد
  // ======================================

  /**
   * تنزيل نموذج CSV فارغ بأسماء الأعمدة الصحيحة
   */
  handleDownloadTemplate() {
    const templateData = [
      {
        'item_number': '12345',
        'assembly_number': 'ASM-001',
        'description': 'مثال: محول كهربائي 500KVA',
        'unit': 'قطعة'
      },
      {
        'item_number': '67890',
        'assembly_number': 'ASM-002',
        'description': 'مثال: كابل نحاس 240mm',
        'unit': 'متر'
      }
    ];

    const csv = Papa.unparse(templateData, { header: true });
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = 'import_template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    AppView.showNotification('تم تنزيل نموذج الاستيراد 📄', 'success');
  },

  // ======================================
  // إضافة مادة يدوياً في الدليل
  // ======================================

  /**
   * معالجة إضافة مادة جديدة للدليل الرئيسي
   * @param {Object} data - بيانات المادة
   */
  async handleAddMaterial(data) {
    if (!data.item_number) {
      AppView.showNotification('رقم البند مطلوب', 'error');
      return;
    }

    AppView.showLoading();
    const { error } = await MaterialModel.upsertBatch([{
      item_number: data.item_number,
      assembly_number: data.assembly_number || null,
      description: data.description || null,
      unit: data.unit || null
    }]);
    AppView.hideLoading();

    if (error) {
      AppView.showNotification('خطأ في إضافة المادة: ' + error.message, 'error');
      return;
    }

    AppView.showNotification('تم إضافة المادة بنجاح ✅', 'success');
    AppView.resetAddMaterialForm();
    await this.loadMaterials();
  },

  // ======================================
  // تصدير نموذج MDR
  // ======================================

  /**
   * معالجة تصدير المواد المحددة إلى نموذج MDR Excel
   * @param {Array} selectedItems - مصفوفة المواد المحددة (تخريد فقط)
   */
  async handleExportMDR(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('يرجى تحديد مادة واحدة على الأقل', 'error');
      return;
    }

    AppView.showLoading();

    try {
      // جلب ملف القالب MDR.xlsx
      const response = await fetch('./MDR.xlsx');
      if (!response.ok) throw new Error('لم يتم العثور على ملف MDR.xlsx');
      const arrayBuffer = await response.arrayBuffer();

      // قراءة الملف باستخدام ExcelJS (يحافظ على كل التنسيقات والصور والشعارات)
      const workbook = new ExcelJS.Workbook();
      await workbook.xlsx.load(arrayBuffer);
      const worksheet = workbook.getWorksheet(1);

      // الحصول على رقم أمر العمل النشط
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (data) woNumber = data.wo_number;
      }

      // كتابة بيانات المواد في الخلايا (بدءاً من الصف 18)
      // B = عمود 2 (الوصف مدموج B-G)
      // I = عمود 9 (الوحدة)
      // J = عمود 10 (الكمية)
      // M = عمود 13 (رقم البند)
      const startRow = 18;
      selectedItems.forEach((item, i) => {
        const rowNum = startRow + i;
        const row = worksheet.getRow(rowNum);

        // وصف المادة - العمود B (مدموج B-G)
        row.getCell(2).value = item.description || '';

        // الوحدة - العمود I
        row.getCell(9).value = item.unit || '';

        // الكمية - العمود J
        row.getCell(10).value = item.quantity || 1;

        // سبب التخلص - العمود K (مدموج K-L)
        row.getCell(11).value = item.disposal_reason || 'معطوب ومستهلك';

        // رقم البند - العمود M
        row.getCell(13).value = item.item_number || '';

        // حالة المادة - العمود N (مدموج N-O)
        row.getCell(14).value = item.material_condition || '';

        // ملاحظات - العمود P (مدموج P-T)
        row.getCell(16).value = item.remarks || '';

        row.commit();
      });

      // كتابة رقم أمر العمل في الصفوف 32-35 (أعمدة G-M مدموجة)
      for (let r = 32; r <= 35; r++) {
        const row = worksheet.getRow(r);
        row.getCell(7).value = woNumber; // العمود G = 7
        row.commit();
      }

      // تنزيل الملف مع الحفاظ على كل التنسيقات
      const buffer = await workbook.xlsx.writeBuffer();
      const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = URL.createObjectURL(blob);

      const link = document.createElement('a');
      link.href = url;
      link.download = `MDR_${woNumber || 'export'}_${new Date().toISOString().slice(0, 10)}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} مادة إلى نموذج MDR بنجاح 📃`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير MDR: ' + error.message, 'error');
      console.error('MDR Export Error:', error);
    }
  },

  // ======================================
  // تصدير MDR كـ PDF (توليد مباشر في المتصفح)
  // ======================================

  async handleExportMDRPdf(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('يرجى تحديد مادة واحدة على الأقل', 'error');
      return;
    }

    AppView.showLoading();

    try {
      // جلب رقم أمر العمل
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (data) woNumber = data.wo_number;
      }

      // توليد PDF مباشرة في المتصفح
      await MDRPdfGenerator.generateAndDownload(selectedItems, woNumber);

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير MDR كـ PDF بنجاح 📕`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير PDF: ' + error.message, 'error');
      console.error('MDR PDF Export Error:', error);
    }
  },

  // ======================================
  // تصدير MRR كـ Excel
  // ======================================

  async handleExportMRR(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('لا توجد مواد إرجاع للتصدير', 'error');
      return;
    }

    AppView.showLoading();

    try {
      const response = await fetch('./MRR.xlsx');
      if (!response.ok) throw new Error('لم يتم العثور على ملف MRR.xlsx');
      const arrayBuffer = await response.arrayBuffer();

      const workbook = new ExcelJS.Workbook();
      await workbook.xlsx.load(arrayBuffer);
      const worksheet = workbook.getWorksheet(1);

      // جلب رقم أمر العمل
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (data) woNumber = data.wo_number;
      }

      // كتابة رقم أمر العمل - أعمدة L-P (L=12) الصف 23
      const woRow = worksheet.getRow(23);
      woRow.getCell(12).value = woNumber;
      woRow.commit();

      // كتابة بيانات المواد بدءاً من الصف 14
      // K=11 (رقم البند), J=10 (الوحدة), I=9 (الكمية)
      // L=12 (الوصف مدموج L-O), P=16 (رقم تسلسلي)
      const startRow = 14;
      selectedItems.forEach((item, i) => {
        const rowNum = startRow + i;
        const row = worksheet.getRow(rowNum);

        row.getCell(11).value = item.item_number || '';
        row.getCell(10).value = item.unit || '';
        row.getCell(9).value = item.quantity || 1;
        row.getCell(12).value = item.description || '';
        row.getCell(16).value = i + 1;

        row.commit();
      });

      const buffer = await workbook.xlsx.writeBuffer();
      const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = URL.createObjectURL(blob);

      const link = document.createElement('a');
      link.href = url;
      link.download = `MRR_${woNumber || 'export'}_${new Date().toISOString().slice(0, 10)}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} مادة إلى نموذج MRR بنجاح 📋`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير MRR: ' + error.message, 'error');
      console.error('MRR Export Error:', error);
    }
  },

  // ======================================
  // تصدير MRR كـ PDF
  // ======================================

  async handleExportMRRPdf(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('يرجى تحديد مادة واحدة على الأقل', 'error');
      return;
    }

    AppView.showLoading();

    try {
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (data) woNumber = data.wo_number;
      }

      await window.MRRPdfGenerator.generate(selectedItems, woNumber);

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير MRR كـ PDF بنجاح 📕`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير PDF: ' + error.message, 'error');
      console.error('MRR PDF Export Error:', error);
    }
  },

  // ======================================
  // تصدير IR كـ Word
  // ======================================

  async handleExportIR(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('لا توجد مواد للتصدير', 'error');
      return;
    }

    AppView.showLoading();

    try {
      // جلب بيانات أمر العمل للحصول على المكان
      let woLocation = '';
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data: wo } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (wo) {
          woLocation = wo.location || '';
          woNumber = wo.wo_number || '';
        }
      }

      // تصدير كل مادة بنموذجها الخاص حسب حالتها
      for (const item of selectedItems) {
        // اختيار القالب بناءً على حالة المادة: إرجاع أو تخريد
        const isReturn = item.status === 'إرجاع';
        const templateFile = isReturn ? './IRRETERN.docx' : './IR.docx';

        // تحميل قالب IR
        const response = await fetch(templateFile);
        if (!response.ok) throw new Error(`لم يتم العثور على ملف القالب`);
        const arrayBuffer = await response.arrayBuffer();

        // إعداد بيانات الاستبدال (مع علامات * للاستبدال المباشر في XML)
        const replacements = {
          '*st*': item.functional_location || '',
          '*qu*': item.equipment || '',
          '*ma*': item.manufacturer || '',
          '*pr*': item.prim_sec_volt || '',
          '*ca*': item.capacity_kva || '',
          '*ye*': item.manufacture_year ? String(item.manufacture_year) : '',
          '*se*': item.serial_number || '',
          '*sec*': item.item_number || '',
          '*ar*': woLocation,
          '*wo*': woNumber
        };

        // فتح الملف واستبدال العلامات مباشرة في XML
        const zip = new PizZip(arrayBuffer);

        // معالجة جميع ملفات XML في الأرشيف (بما فيها headers و footers)
        const xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];

        xmlFiles.forEach(filePath => {
          const file = zip.file(filePath);
          if (!file) return;

          let content = file.asText();

          // استبدال كل علامة - مع مراعاة أن Word قد يقسم النص في XML
          for (const [tag, value] of Object.entries(replacements)) {
            // استبدال مباشر
            content = content.split(tag).join(value);

            // استبدال مع احتمالية تقسيم Word للنص بين عناصر XML
            const tagChars = tag.split('');
            const regexParts = tagChars.map(ch => {
              const escaped = ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
              return escaped;
            });
            const regexStr = regexParts.join('(?:<[^>]*>)*');
            const regex = new RegExp(regexStr, 'g');
            content = content.replace(regex, (match) => {
              const xmlTags = match.match(/<[^>]*>/g) || [];
              return value + xmlTags.join('');
            });
          }

          zip.file(filePath, content);
        });

        // توليد الملف وتنزيله
        const output = zip.generate({
          type: 'blob',
          mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        });

        const filePrefix = isReturn ? 'IR-RETURN' : 'IR';
        const fileName = `${filePrefix}_${item.item_number || 'export'}_${new Date().toISOString().slice(0, 10)}.docx`;
        const url = URL.createObjectURL(output);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} نموذج IR بنجاح 📝`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير IR: ' + error.message, 'error');
      console.error('IR Export Error:', error);
    }
  },

  // ======================================
  // تصدير IR كـ PDF (عبر PHP API)
  // ======================================

  async handleExportIRPdf(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('لا توجد مواد للتصدير', 'error');
      return;
    }

    AppView.showLoading();

    try {
      let woLocation = '';
      let woNumber = '';
      if (this._activeWorkOrderId) {
        const { data: wo } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (wo) {
          woLocation = wo.location || '';
          woNumber = wo.wo_number || '';
        }
      }

      for (const item of selectedItems) {
        const response = await fetch('./api/export_ir.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            item: item,
            woLocation: woLocation,
            woNumber: woNumber
          })
        });

        const result = await response.json();

        if (result.error) {
          throw new Error(result.error);
        }

        if (result.url) {
          // تحميل الملف بدلاً من فتحه في المتصفح
          const pdfResponse = await fetch(result.url);
          const pdfBlob = await pdfResponse.blob();
          const isReturn = item.status === 'إرجاع';
          const filePrefix = isReturn ? 'IR-RETURN' : 'IR';
          const today = new Date().toISOString().slice(0, 10);
          const downloadName = `${filePrefix}_${woNumber || 'export'}_${item.item_number || 'export'}_${today}.pdf`;
          const blobUrl = URL.createObjectURL(pdfBlob);
          const a = document.createElement('a');
          a.href = blobUrl;
          a.download = downloadName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(blobUrl);
        }
      }

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} نموذج IR PDF بنجاح 📕`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير IR PDF: ' + error.message, 'error');
      console.error('IR PDF Export Error:', error);
    }
  },

  // ======================================
  // تصدير FATRA كـ Word
  // ======================================

  async handleExportFATRA(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('لا توجد مواد للتصدير', 'error');
      return;
    }

    AppView.showLoading();

    try {
      // جلب رقم أمر العمل والمكان
      let woNumber = '';
      let woLocation = '';
      if (this._activeWorkOrderId) {
        const { data: wo } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (wo) {
          woNumber = wo.wo_number;
          woLocation = wo.location || '';
        }
      }

      // تصدير كل مادة بنموذجها الخاص حسب حالتها
      for (const item of selectedItems) {
        // اختيار القالب بناءً على حالة المادة: إرجاع أو تخريد
        const isReturn = item.status === 'إرجاع';
        const templateFile = isReturn ? './FATRARETERN.docx' : './FATRA.docx';

        // تحميل القالب
        const response = await fetch(templateFile);
        if (!response.ok) throw new Error(`لم يتم العثور على ملف القالب`);
        const arrayBuffer = await response.arrayBuffer();

        // إعداد بيانات الاستبدال
        const replacements = {
          '*st*': item.functional_location || '',
          '*se*': item.serial_number || '',
          '*qu*': item.equipment || '',
          '*sec*': item.item_number || '',
          '*wo*': woNumber,
          '*ma*': item.manufacturer || '',
          '*ca*': item.capacity_kva || '',
          '*ar*': woLocation
        };

        // فتح الملف واستبدال العلامات مباشرة في XML
        const zip = new PizZip(arrayBuffer);

        // معالجة جميع ملفات XML في الأرشيف
        const xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];

        xmlFiles.forEach(filePath => {
          const file = zip.file(filePath);
          if (!file) return;

          let content = file.asText();

          // استبدال كل علامة - مع مراعاة أن Word قد يقسم النص في XML
          for (const [tag, value] of Object.entries(replacements)) {
            // استبدال مباشر
            content = content.split(tag).join(value);

            // استبدال مع احتمالية تقسيم Word للنص بين عناصر XML
            const tagChars = tag.split('');
            // بناء regex يتجاهل عناصر XML بين أحرف العلامة
            const regexParts = tagChars.map(ch => {
              const escaped = ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
              return escaped;
            });
            const regexStr = regexParts.join('(?:<[^>]*>)*');
            const regex = new RegExp(regexStr, 'g');
            content = content.replace(regex, (match) => {
              // الحفاظ على عناصر XML الموجودة قبل الاستبدال
              const xmlTags = match.match(/<[^>]*>/g) || [];
              return value + xmlTags.join('');
            });
          }

          zip.file(filePath, content);
        });

        // توليد الملف وتنزيله
        const output = zip.generate({
          type: 'blob',
          mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        });

        const filePrefix = isReturn ? 'FATRA-RETURN' : 'FATRA';
        const fileName = `${filePrefix}_${item.item_number || 'export'}_${new Date().toISOString().slice(0, 10)}.docx`;
        const url = URL.createObjectURL(output);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} نموذج FATRA بنجاح 📄`, 'success');

    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير FATRA: ' + error.message, 'error');
      console.error('FATRA Export Error:', error);
    }
  },

  async handleExportFATRAPdf(selectedItems) {
    if (!selectedItems || selectedItems.length === 0) {
      AppView.showNotification('لا توجد مواد للتصدير', 'error');
      return;
    }
    AppView.showLoading();
    try {
      let woNumber = '';
      let woLocation = '';
      if (this._activeWorkOrderId) {
        const { data: wo } = await WorkOrderModel.getById(this._activeWorkOrderId);
        if (wo) {
          woNumber = wo.wo_number;
          woLocation = wo.location || '';
        }
      }

      for (const item of selectedItems) {
        const response = await fetch('./api/export_fatra.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            item: item,
            woLocation: woLocation,
            woNumber: woNumber
          })
        });

        const result = await response.json();

        if (result.error) {
          throw new Error(result.error);
        }

        if (result.url) {
          // تحميل الملف بدلاً من فتحه في المتصفح
          const pdfResponse = await fetch(result.url);
          const pdfBlob = await pdfResponse.blob();
          const isReturn = item.status === 'إرجاع';
          const filePrefix = isReturn ? 'FATRA-RETURN' : 'FATRA';
          const today = new Date().toISOString().slice(0, 10);
          const downloadName = `${filePrefix}_${woNumber || 'export'}_${item.item_number || 'export'}_${today}.pdf`;
          const blobUrl = URL.createObjectURL(pdfBlob);
          const a = document.createElement('a');
          a.href = blobUrl;
          a.download = downloadName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(blobUrl);
        }
      }

      AppView.hideLoading();
      AppView.showNotification(`تم تصدير ${selectedItems.length} نماذج FATRA PDF بنجاح 📕`, 'success');
    } catch (error) {
      AppView.hideLoading();
      AppView.showNotification('خطأ في تصدير FATRA PDF: ' + error.message, 'error');
      console.error('FATRA PDF Export Error:', error);
    }
  }
};

// ======================================
// بدء تشغيل التطبيق عند تحميل الصفحة
// ======================================
document.addEventListener('DOMContentLoaded', () => {
  AppController.init();
});
