/**
 * AppView.js
 * ==================
 * طبقة العرض (View) - مسؤولة فقط عن الـ DOM والأحداث
 * لا تتصل أبداً بالـ Model مباشرة
 * تُمرر الأحداث إلى الـ Controller عبر callbacks
 */

import LocalFileStore from '../models/LocalFileStore.js';

const AppView = {
  // ======================================
  // مراجع عناصر الـ DOM
  // ======================================
  elements: {},

  /**
   * تهيئة مراجع عناصر DOM
   */
  init() {
    // --- أوامر العمل ---
    this.elements.woForm = document.getElementById('wo-form');
    this.elements.woNumberInput = document.getElementById('wo-number');
    this.elements.woTypeInput = document.getElementById('wo-type');
    this.elements.woLocationInput = document.getElementById('wo-location');
    this.elements.woDepartmentInput = document.getElementById('wo-department');
    this.elements.woList = document.getElementById('wo-list');
    this.elements.woSearch = document.getElementById('wo-search');
    this.elements.woFilterBtns = document.querySelectorAll('.wo-filter-btn');
    this.elements.btnNewWo = document.getElementById('btn-new-wo');
    this._allOrders = [];
    this._woFilter = 'all';
    this._woSearchText = '';

    // --- Modal أمر عمل جديد ---
    this.elements.modalCreateWo = document.getElementById('modal-create-wo');

    // --- Modal تفاصيل أمر العمل ---
    this.elements.modalWoDetails = document.getElementById('modal-wo-details');
    this.elements.modalWoTitle = document.getElementById('modal-wo-title');
    this.elements.btnAddRemoved = document.getElementById('btn-add-removed');

    // --- Modal إضافة/تعديل مادة مزالة ---
    this.elements.modalAddRemoved = document.getElementById('modal-add-removed');
    this.elements.modalRemovedTitle = document.getElementById('modal-removed-title');

    // --- Modal تعديل المكان ---
    this.elements.modalEditLocation = document.getElementById('modal-edit-location');
    this.elements.editLocationForm = document.getElementById('edit-location-form');
    this.elements.editLocationInput = document.getElementById('edit-location-input');

    // --- المواد المُزالة ---
    this.elements.removedForm = document.getElementById('removed-form');
    this.elements.removedItemNumber = document.getElementById('removed-item-number');
    this.elements.removedAssembly = document.getElementById('removed-assembly');
    this.elements.removedDescription = document.getElementById('removed-description');
    this.elements.removedUnit = document.getElementById('removed-unit');
    this.elements.removedFunctionalLocation = document.getElementById('removed-functional-location');
    this.elements.removedEquipment = document.getElementById('removed-equipment');
    this.elements.removedCapacityKva = document.getElementById('removed-capacity-kva');
    this.elements.removedManufacturer = document.getElementById('removed-manufacturer');
    this.elements.removedPrimSecVolt = document.getElementById('removed-prim-sec-volt');
    this.elements.removedYear = document.getElementById('removed-year');
    this.elements.removedSerial = document.getElementById('removed-serial');
    this.elements.removedQuantity = document.getElementById('removed-quantity');
    this.elements.removedStatus = document.getElementById('removed-status');
    this.elements.removedDisposalReason = document.getElementById('removed-disposal-reason');
    this.elements.removedMaterialCondition = document.getElementById('removed-material-condition');
    this.elements.removedRemarks = document.getElementById('removed-remarks');
    this.elements.removedItemType = document.getElementById('removed-item-type');
    this.elements.removedImage = document.getElementById('removed-image');
    this.elements.capitalFields = document.getElementById('capital-fields');
    this.elements.removedTable = document.getElementById('removed-table-body');
    this.elements.autocompleteList = document.getElementById('autocomplete-list');
    this.elements.exportMdrBtn = document.getElementById('export-mdr-btn');
    this.elements.exportMdrPdfBtn = document.getElementById('export-mdr-pdf-btn');
    this.elements.exportIrBtn = document.getElementById('export-ir-btn');
    this.elements.exportIrPdfBtn = document.getElementById('export-ir-pdf-btn');
    this.elements.exportFatraBtn = document.getElementById('export-fatra-btn');
    this.elements.exportFatraPdfBtn = document.getElementById('export-fatra-pdf-btn');
    this.elements.exportMrrBtn = document.getElementById('export-mrr-btn');
    this.elements.exportMrrPdfBtn = document.getElementById('export-mrr-pdf-btn');
    this.elements.selectAllRemoved = document.getElementById('select-all-removed');
    this.elements.toggleExtraCols = document.getElementById('toggle-extra-cols');
    this.elements.attachmentInput = document.getElementById('attachment-input');
    this.elements.attachmentsList = document.getElementById('attachments-list');
    this._extraColsVisible = false;

    // أعمدة إضافية
    this.elements.toggleExtraCols.addEventListener('click', () => {
      this._extraColsVisible = !this._extraColsVisible;
      const table = document.getElementById('removed-items-table');
      table.classList.toggle('show-extra-cols', this._extraColsVisible);
      this.elements.toggleExtraCols.classList.toggle('active', this._extraColsVisible);
      this.elements.toggleExtraCols.innerHTML = this._extraColsVisible
        ? '<span>📊</span> إخفاء التفاصيل'
        : '<span>📊</span> عرض التفاصيل';
    });

    // --- دليل المواد ---
    this.elements.importBtn = document.getElementById('import-btn');
    this.elements.importFile = document.getElementById('import-file');
    this.elements.exportBtn = document.getElementById('export-btn');
    this.elements.templateBtn = document.getElementById('template-btn');
    this.elements.addMaterialToggle = document.getElementById('add-material-toggle');
    this.elements.addMaterialSection = document.getElementById('add-material-section');
    this.elements.addMaterialForm = document.getElementById('add-material-form');
    this.elements.cancelAddMaterial = document.getElementById('cancel-add-material');
    this.elements.matItemNumber = document.getElementById('mat-item-number');
    this.elements.matAssembly = document.getElementById('mat-assembly');
    this.elements.matDescription = document.getElementById('mat-description');
    this.elements.matUnit = document.getElementById('mat-unit');
    this.elements.materialsTable = document.getElementById('materials-table-body');
    this.elements.materialsCount = document.getElementById('materials-count');

    // --- Image Viewer Modal ---
    this.elements.modalImageViewer = document.getElementById('modal-image-viewer');
    this.elements.ivImage = document.getElementById('iv-image');
    this.elements.ivTitle = document.getElementById('iv-title');
    this.elements.ivPrev = document.getElementById('iv-prev');
    this.elements.ivNext = document.getElementById('iv-next');
    this.elements.ivDownload = document.getElementById('iv-download');
    this.elements.ivCounter = document.getElementById('iv-counter');
    this._currentImageUrls = [];
    this._currentImageIndex = 0;
    this._currentImageTitle = '';

    // --- عناصر عامة ---
    this.elements.notification = document.getElementById('notification');
    this.elements.loadingOverlay = document.getElementById('loading-overlay');
    this.elements.tabs = document.querySelectorAll('.tab-btn');
    this.elements.tabPanels = document.querySelectorAll('.tab-panel');

    // تفعيل التبويبات
    this._setupTabs();
    // تفعيل Modals
    this._setupModals();
    // زر أمر عمل جديد
    this.elements.btnNewWo.addEventListener('click', () => this.openModal('modal-create-wo'));
    // زر إضافة مادة مزالة
    this.elements.btnAddRemoved.addEventListener('click', () => {
      this.elements.modalRemovedTitle.textContent = '➕ إضافة مادة مُزالة';
      this.elements.removedItemType.dispatchEvent(new Event('change')); // لتحديث الحقول الرأسمالية
      this.openModal('modal-add-removed');
    });

    // إظهار وإخفاء الحقول الرأسمالية
    this.elements.removedItemType.addEventListener('change', (e) => {
      if (e.target.value === 'رأس مالي') {
        this.elements.capitalFields.style.display = 'block';
      } else {
        this.elements.capitalFields.style.display = 'none';
      }
    });

    this._setupImageViewer();
  },

  // ======================================
  // Modals
  // ======================================
  _setupModals() {
    // إغلاق بالضغط على X
    document.querySelectorAll('.modal-close').forEach(btn => {
      btn.addEventListener('click', () => {
        const modalId = btn.dataset.closeModal;
        this.closeModal(modalId);
      });
    });
    // إغلاق بالضغط على الخلفية
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) this.closeModal(overlay.id);
      });
    });
    // إغلاق بالضغط على Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => this.closeModal(m.id));
      }
    });
  },

  _setupImageViewer() {
    this.elements.ivPrev.addEventListener('click', () => {
      if (this._currentImageUrls.length > 1) {
        this._currentImageIndex = (this._currentImageIndex - 1 + this._currentImageUrls.length) % this._currentImageUrls.length;
        this._updateImageViewer();
      }
    });

    this.elements.ivNext.addEventListener('click', () => {
      if (this._currentImageUrls.length > 1) {
        this._currentImageIndex = (this._currentImageIndex + 1) % this._currentImageUrls.length;
        this._updateImageViewer();
      }
    });

    this.elements.ivDownload.addEventListener('click', async () => {
      const url = this._currentImageUrls[this._currentImageIndex];
      if (url) {
        try {
          const originalContent = this.elements.ivDownload.innerHTML;
          this.elements.ivDownload.innerHTML = '<span>⏳</span> جارِ التحميل...';
          this.elements.ivDownload.disabled = true;

          const response = await fetch(url);
          if (!response.ok) throw new Error('Network response was not ok');
          const blob = await response.blob();
          const blobUrl = window.URL.createObjectURL(blob);
          
          const link = document.createElement('a');
          link.href = blobUrl;
          link.download = `${this._currentImageTitle}-${this._currentImageIndex + 1}.jpg`;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          
          window.URL.revokeObjectURL(blobUrl);
        } catch (error) {
          console.error('Error downloading image:', error);
          this.showNotification('حدث خطأ أثناء تحميل الصورة', 'error');
        } finally {
          this.elements.ivDownload.innerHTML = '<span>⬇️</span> تحميل الصورة';
          this.elements.ivDownload.disabled = false;
        }
      }
    });

    // استخدام event delegation لفتح الصور
    this.elements.removedTable.addEventListener('click', (e) => {
      if (e.target.classList.contains('thumb-img')) {
        const urlsStr = e.target.dataset.urls;
        const indexStr = e.target.dataset.index;
        const titleStr = e.target.dataset.title || 'صورة المادة';
        if (urlsStr && indexStr !== undefined) {
          try {
            this._currentImageUrls = JSON.parse(urlsStr);
            this._currentImageIndex = parseInt(indexStr, 10);
            this._currentImageTitle = titleStr;
            this._updateImageViewer();
            this.openModal('modal-image-viewer');
          } catch (err) {
            console.error('Error parsing image URLs:', err);
          }
        }
      }
    });
  },

  _updateImageViewer() {
    const total = this._currentImageUrls.length;
    if (total === 0) return;
    
    this.elements.ivImage.src = this._currentImageUrls[this._currentImageIndex];
    this.elements.ivCounter.textContent = `${this._currentImageIndex + 1} / ${total}`;
    
    // Set title as Item Number or Description
    this.elements.ivTitle.textContent = this._currentImageTitle;

    if (total <= 1) {
      this.elements.ivPrev.style.display = 'none';
      this.elements.ivNext.style.display = 'none';
      this.elements.ivCounter.style.display = 'none';
    } else {
      this.elements.ivPrev.style.display = 'block';
      this.elements.ivNext.style.display = 'block';
      this.elements.ivCounter.style.display = 'block';
    }
  },

  openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  },

  closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('active');
      // تحقق إذا لم تبقَ أي modals مفتوحة
      const openModals = document.querySelectorAll('.modal-overlay.active');
      if (openModals.length === 0) {
        document.body.style.overflow = '';
      }
    }
  },

  // ======================================
  // التبويبات
  // ======================================
  _setupTabs() {
    this.elements.tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        this.elements.tabs.forEach(t => t.classList.remove('active'));
        this.elements.tabPanels.forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const panelId = tab.dataset.tab;
        document.getElementById(panelId).classList.add('active');
      });
    });
  },

  // ======================================
  // عرض أوامر العمل كجدول
  // ======================================
  renderWorkOrders(orders) {
    this._allOrders = orders || [];
    this._renderFilteredOrders();
  },

  _renderFilteredOrders() {
    this.elements.woList.innerHTML = '';

    let filtered = this._allOrders;

    if (this._woFilter !== 'all') {
      filtered = filtered.filter(o => (o.status || 'جاري') === this._woFilter);
    }

    if (this._woSearchText) {
      const q = this._woSearchText.toLowerCase();
      filtered = filtered.filter(o =>
        o.wo_number.toLowerCase().includes(q) ||
        o.wo_type.toLowerCase().includes(q) ||
        (o.location || '').toLowerCase().includes(q)
      );
    }

    if (filtered.length === 0) {
      this.elements.woList.innerHTML = `
        <tr>
          <td colspan="7" class="empty-cell">
            <div class="empty-state-small">
              <p>${this._woSearchText || this._woFilter !== 'all' ? 'لا توجد نتائج مطابقة' : 'لا توجد أوامر عمل بعد'}</p>
            </div>
          </td>
        </tr>`;
      return;
    }

    filtered.forEach((order, index) => {
      const row = document.createElement('tr');
      row.className = 'wo-row fade-in';
      row.style.animationDelay = `${index * 0.03}s`;
      row.dataset.id = order.id;
      const date = new Date(order.created_at).toLocaleDateString('ar-SA');
      const woStatus = order.status || 'جاري';
      const woStatusClass = woStatus === 'مكتمل' ? 'status-complete' : 'status-progress';
      row.innerHTML = `
        <td>${index + 1}</td>
        <td class="wo-clickable"><strong>${order.wo_number}</strong></td>
        <td class="wo-clickable">${order.wo_type}</td>
        <td class="wo-clickable"><span class="badge badge-outline">${order.department || '-'}</span></td>
        <td class="wo-clickable">${order.location || '<span class="text-muted">—</span>'}</td>
        <td class="wo-clickable"><span class="wo-status-badge ${woStatusClass}">${woStatus}</span></td>
        <td class="wo-clickable">${date}</td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit-location" data-id="${order.id}" data-location="${order.location || ''}" title="تعديل المكان">📍</button>
          <button class="btn-icon btn-delete-wo" data-id="${order.id}" title="حذف">🗑️</button>
        </td>`;
      this.elements.woList.appendChild(row);
    });
  },

  setupWoSearchAndFilter() {
    this.elements.woSearch.addEventListener('input', (e) => {
      this._woSearchText = e.target.value.trim();
      this._renderFilteredOrders();
    });

    this.elements.woFilterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        this.elements.woFilterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this._woFilter = btn.dataset.filter;
        this._renderFilteredOrders();
      });
    });
  },

  // ======================================
  // عرض المواد المُزالة
  // ======================================
  async renderRemovedItems(items) {
    this.elements.removedTable.innerHTML = '';
    this._currentRemovedItems = items || [];

    if (this.elements.selectAllRemoved) {
      this.elements.selectAllRemoved.checked = false;
    }

    const hasItems = items && items.length > 0;
    this.elements.exportMdrBtn.style.display = hasItems ? 'inline-flex' : 'none';
    this.elements.exportMdrPdfBtn.style.display = hasItems ? 'inline-flex' : 'none';
    this.elements.exportIrBtn.style.display = hasItems ? 'inline-flex' : 'none';
    if (this.elements.exportIrPdfBtn) {
      this.elements.exportIrPdfBtn.style.display = hasItems ? 'inline-flex' : 'none';
    }
    this.elements.exportFatraBtn.style.display = hasItems ? 'inline-flex' : 'none';
    if (this.elements.exportFatraPdfBtn) {
      this.elements.exportFatraPdfBtn.style.display = hasItems ? 'inline-flex' : 'none';
    }
    const hasReturn = items && items.some(i => i.status === 'إرجاع');
    this.elements.exportMrrBtn.style.display = hasReturn ? 'inline-flex' : 'none';
    if (this.elements.exportMrrPdfBtn) {
      this.elements.exportMrrPdfBtn.style.display = hasReturn ? 'inline-flex' : 'none';
    }

    if (!items || items.length === 0) {
      this.elements.removedTable.innerHTML = `
        <tr>
          <td colspan="19" class="empty-cell">
            <div class="empty-state-small">
              <p>لا توجد مواد مُزالة لهذا الأمر</p>
            </div>
          </td>
        </tr>`;
      return;
    }

    for (let index = 0; index < items.length; index++) {
      const item = items[index];
      const row = document.createElement('tr');
      row.className = 'fade-in';
      row.style.animationDelay = `${index * 0.05}s`;
      if (item.is_completed) row.classList.add('row-completed');
      const statusClass = item.status === 'إرجاع' ? 'status-return' : 'status-scrap';
      const completedChecked = item.is_completed ? 'checked' : '';
      let imagesHtml = '<span class="no-image">—</span>';
      const itemTitle = item.description || item.item_number || 'المادة المزالة';
      if (item.image_url) {
        if (Array.isArray(item.image_url) && item.image_url.length > 0) {
          const urlsJson = JSON.stringify(item.image_url).replace(/"/g, '&quot;');
          imagesHtml = item.image_url.map((url, i) => 
            `<img src="${url}" class="thumb-img" alt="صورة المادة" data-urls="${urlsJson}" data-index="${i}" data-title="${itemTitle.replace(/"/g, '&quot;')}" style="margin-left: 2px; cursor: pointer;" />`
          ).join('');
        } else if (typeof item.image_url === 'string') {
          // Fallback for old single image data (LocalFileStore or single URL string)
          const src = item.image_url.startsWith('http') ? item.image_url : await LocalFileStore.getFileURL(item.image_url);
          if (src) {
            const urlsJson = JSON.stringify([src]).replace(/"/g, '&quot;');
            imagesHtml = `<img src="${src}" class="thumb-img" alt="صورة المادة" data-urls="${urlsJson}" data-index="0" data-title="${itemTitle.replace(/"/g, '&quot;')}" style="cursor: pointer;" />`;
          }
        }
      }

      row.innerHTML = `
        <td><input type="checkbox" class="removed-checkbox" data-index="${index}" /></td>
        <td>${index + 1}</td>
        <td><strong>${item.item_number || '-'}</strong></td>
        <td>${item.assembly_number || '-'}</td>
        <td class="desc-cell">${item.description || '-'}</td>
        <td>${item.unit || '-'}</td>
        <td class="col-extra">${item.functional_location || '-'}</td>
        <td class="col-extra">${item.equipment || '-'}</td>
        <td class="col-extra">${item.capacity_kva || '-'}</td>
        <td class="col-extra">${item.manufacturer || '-'}</td>
        <td class="col-extra">${item.prim_sec_volt || '-'}</td>
        <td class="col-extra">${item.manufacture_year || '-'}</td>
        <td class="col-extra">${item.serial_number || '-'}</td>
        <td>${item.quantity || 1}</td>
        <td><span class="type-badge ${(item.item_type || 'تشغيلي') === 'رأس مالي' ? 'type-capital' : 'type-operational'}">${item.item_type || 'تشغيلي'}</span></td>
        <td><span class="status-badge ${statusClass}">${item.status}</span></td>
        <td><input type="checkbox" class="complete-checkbox" data-id="${item.id}" ${completedChecked} title="تحديد كمكتمل" /></td>
        <td style="white-space: nowrap; overflow-x: auto; max-width: 150px;">
          ${imagesHtml}
        </td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit-item" data-id="${item.id}" title="تعديل">✏️</button>
          <button class="btn-icon btn-delete-item" data-id="${item.id}" title="حذف">🗑️</button>
        </td>`;
      this.elements.removedTable.appendChild(row);
    }
  },

  renderAutocompleteSuggestions(materials) {
    this.elements.autocompleteList.innerHTML = '';
    if (!materials || materials.length === 0) {
      this.elements.autocompleteList.classList.remove('visible');
      return;
    }
    this.elements.autocompleteList.classList.add('visible');
    materials.forEach(mat => {
      const item = document.createElement('div');
      item.className = 'autocomplete-item';
      item.innerHTML = `
        <span class="ac-number">${mat.item_number}</span>
        <span class="ac-desc">${mat.description || ''}</span>`;
      item.addEventListener('click', () => {
        this.elements.removedItemNumber.value = mat.item_number;
        this.elements.removedAssembly.value = mat.assembly_number || '';
        this.elements.removedDescription.value = mat.description || '';
        this.elements.removedUnit.value = mat.unit || '';
        this.elements.autocompleteList.classList.remove('visible');
      });
      this.elements.autocompleteList.appendChild(item);
    });
  },

  renderMaterialsTable(materials) {
    this.elements.materialsTable.innerHTML = '';
    this.elements.materialsCount.textContent = materials ? materials.length : 0;
    if (!materials || materials.length === 0) {
      this.elements.materialsTable.innerHTML = `
        <tr>
          <td colspan="5" class="empty-cell">
            <div class="empty-state-small">
              <p>لا توجد مواد في الدليل الرئيسي</p>
              <p class="sub">قم باستيراد ملف CSV لإضافة المواد</p>
            </div>
          </td>
        </tr>`;
      return;
    }
    materials.forEach((mat, index) => {
      const row = document.createElement('tr');
      row.className = 'fade-in';
      row.style.animationDelay = `${index * 0.02}s`;
      row.innerHTML = `
        <td>${index + 1}</td>
        <td><strong>${mat.item_number}</strong></td>
        <td>${mat.assembly_number || '-'}</td>
        <td>${mat.description || '-'}</td>
        <td>${mat.unit || '-'}</td>`;
      this.elements.materialsTable.appendChild(row);
    });
  },

  setActiveWorkOrder(order) {
    this.elements.modalWoTitle.textContent = `🔩 أمر العمل: ${order.wo_number} — ${order.location || 'بدون مكان'}`;
    this.openModal('modal-wo-details');
  },

  hideRemovedSection() {
    this.closeModal('modal-wo-details');
  },

  // ======================================
  // الإشعارات والتحميل
  // ======================================
  showNotification(message, type = 'info') {
    const el = this.elements.notification;
    el.textContent = message;
    el.className = `notification ${type} show`;
    clearTimeout(this._notifTimeout);
    this._notifTimeout = setTimeout(() => {
      el.classList.remove('show');
    }, 4000);
  },

  showLoading() {
    this.elements.loadingOverlay.classList.add('visible');
  },

  hideLoading() {
    this.elements.loadingOverlay.classList.remove('visible');
  },

  resetRemovedForm() {
    this.elements.removedForm.reset();
    this.elements.autocompleteList.classList.remove('visible');
    if (this.elements.capitalFields) {
      this.elements.capitalFields.style.display = 'none';
    }
  },

  resetWoForm() {
    this.elements.woForm.reset();
    if (this.elements.woLocationInput) this.elements.woLocationInput.value = '';
    if (this.elements.woDepartmentInput) this.elements.woDepartmentInput.value = '';
    this.closeModal('modal-create-wo');
  },

  resetAddMaterialForm() {
    this.elements.addMaterialForm.reset();
    this.elements.addMaterialSection.classList.remove('visible');
  },

  // ======================================
  // ربط الأحداث (Event Binding)
  // ======================================

  bindSubmitWorkOrder(handler) {
    this.elements.woForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const woNumber = this.elements.woNumberInput.value.trim();
      const woType = this.elements.woTypeInput.value.trim();
      const woLocation = this.elements.woLocationInput.value.trim();
      const woDepartment = this.elements.woDepartmentInput.value;
      handler(woNumber, woType, woLocation, woDepartment);
    });
  },

  bindAddRemovedItem(handler) {
    this.elements.removedForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = {
        item_number: this.elements.removedItemNumber.value.trim(),
        assembly_number: this.elements.removedAssembly.value.trim(),
        description: this.elements.removedDescription.value.trim(),
        unit: this.elements.removedUnit.value.trim(),
        functional_location: this.elements.removedFunctionalLocation.value.trim(),
        equipment: this.elements.removedEquipment.value.trim(),
        capacity_kva: this.elements.removedCapacityKva.value.trim(),
        manufacturer: this.elements.removedManufacturer.value.trim(),
        prim_sec_volt: this.elements.removedPrimSecVolt.value.trim(),
        manufacture_year: parseInt(this.elements.removedYear.value) || null,
        serial_number: this.elements.removedSerial.value.trim(),
        quantity: parseInt(this.elements.removedQuantity.value) || 1,
        status: this.elements.removedStatus.value,
        disposal_reason: this.elements.removedDisposalReason.value,
        material_condition: this.elements.removedMaterialCondition.value,
        remarks: this.elements.removedRemarks.value.trim(),
        item_type: this.elements.removedItemType.value,
        imageFiles: Array.from(this.elements.removedImage.files)
      };
      handler(data);
    });
  },

  bindSelectWorkOrder(handler) {
    this.elements.woList.addEventListener('click', (e) => {
      const clickable = e.target.closest('.wo-clickable');
      const row = e.target.closest('.wo-row');
      if (clickable && row) {
        handler(row.dataset.id);
      }
    });
  },

  bindDeleteWorkOrder(handler) {
    this.elements.woList.addEventListener('click', (e) => {
      const deleteBtn = e.target.closest('.btn-delete-wo');
      if (deleteBtn) {
        e.stopPropagation();
        if (confirm('هل أنت متأكد من حذف أمر العمل هذا وجميع المواد المرتبطة به؟')) {
          handler(deleteBtn.dataset.id);
        }
      }
    });
  },

  bindEditLocation(handler) {
    // فتح modal التعديل
    this.elements.woList.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-edit-location');
      if (btn) {
        e.stopPropagation();
        this._editingLocationWoId = btn.dataset.id;
        this.elements.editLocationInput.value = btn.dataset.location || '';
        this.openModal('modal-edit-location');
        this.elements.editLocationInput.focus();
      }
    });
    // حفظ المكان
    this.elements.editLocationForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const newLocation = this.elements.editLocationInput.value.trim();
      handler(this._editingLocationWoId, newLocation);
      this.closeModal('modal-edit-location');
    });
  },

  bindItemNumberSearch(handler) {
    let debounceTimer;
    const triggerSearch = (query) => {
      clearTimeout(debounceTimer);
      if (query.length < 2) {
        this.elements.autocompleteList.classList.remove('visible');
        return;
      }
      debounceTimer = setTimeout(() => handler(query), 300);
    };
    this.elements.removedItemNumber.addEventListener('input', (e) => {
      triggerSearch(e.target.value.trim());
    });
    this.elements.removedDescription.addEventListener('input', (e) => {
      triggerSearch(e.target.value.trim());
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.autocomplete-wrapper') && !e.target.closest('#removed-description')) {
        this.elements.autocompleteList.classList.remove('visible');
      }
    });
  },

  bindImportCSV(handler) {
    this.elements.importBtn.addEventListener('click', () => {
      this.elements.importFile.click();
    });
    this.elements.importFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        handler(file);
        e.target.value = '';
      }
    });
  },

  bindExportCSV(handler) {
    this.elements.exportBtn.addEventListener('click', () => handler());
  },

  bindDownloadTemplate(handler) {
    this.elements.templateBtn.addEventListener('click', () => handler());
  },

  bindAddMaterial(handler) {
    this.elements.addMaterialToggle.addEventListener('click', () => {
      this.elements.addMaterialSection.classList.toggle('visible');
    });
    this.elements.cancelAddMaterial.addEventListener('click', () => {
      this.elements.addMaterialSection.classList.remove('visible');
      this.elements.addMaterialForm.reset();
    });
    this.elements.addMaterialForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = {
        item_number: this.elements.matItemNumber.value.trim(),
        assembly_number: this.elements.matAssembly.value.trim(),
        description: this.elements.matDescription.value.trim(),
        unit: this.elements.matUnit.value.trim()
      };
      handler(data);
    });
  },

  bindExportMDR(handler) {
    this.elements.selectAllRemoved.addEventListener('change', (e) => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox');
      checkboxes.forEach(cb => cb.checked = e.target.checked);
    });
    this.elements.exportMdrBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        const operationalItems = this._currentRemovedItems.filter(i => (i.item_type || 'تشغيلي') === 'تشغيلي' && i.status === 'تخريد');
        if (operationalItems.length === 0) {
          this.showNotification('لا توجد مواد تشغيلية للتخريد للتصدير', 'error');
          return;
        }
        handler(operationalItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportMDRPdf(handler) {
    this.elements.exportMdrPdfBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        const operationalItems = this._currentRemovedItems.filter(i => (i.item_type || 'تشغيلي') === 'تشغيلي' && i.status === 'تخريد');
        if (operationalItems.length === 0) {
          this.showNotification('لا توجد مواد تشغيلية للتخريد للتصدير', 'error');
          return;
        }
        handler(operationalItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportIR(handler) {
    this.elements.exportIrBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        handler(this._currentRemovedItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportIRPdf(handler) {
    if (!this.elements.exportIrPdfBtn) return;
    this.elements.exportIrPdfBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        handler(this._currentRemovedItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindDeleteRemovedItem(handler) {
    this.elements.removedTable.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-delete-item');
      if (btn) {
        if (confirm('هل أنت متأكد من حذف هذه المادة؟')) {
          handler(btn.dataset.id);
        }
      }
    });
  },

  bindEditRemovedItem(handler) {
    this.elements.removedTable.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-edit-item');
      if (btn) {
        const id = btn.dataset.id;
        const item = this._currentRemovedItems.find(i => i.id === id);
        if (item) {
          handler(item);
          // تأكد من تحديث ظهور الحقول الرأسمالية عند التعديل
          setTimeout(() => {
            this.elements.removedItemType.dispatchEvent(new Event('change'));
          }, 50);
        }
      }
    });
  },

  bindToggleComplete(handler) {
    this.elements.removedTable.addEventListener('change', (e) => {
      if (e.target.classList.contains('complete-checkbox')) {
        const isChecked = e.target.checked;
        const msg = isChecked ? 'هل تريد تحديد هذه المادة كمكتملة؟' : 'هل تريد إلغاء اكتمال هذه المادة؟';
        if (confirm(msg)) {
          handler(e.target.dataset.id, isChecked);
        } else {
          e.target.checked = !isChecked;
        }
      }
    });
  },

  bindExportFATRA(handler) {
    this.elements.exportFatraBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        handler(this._currentRemovedItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportFATRAPdf(handler) {
    if (!this.elements.exportFatraPdfBtn) return;
    this.elements.exportFatraPdfBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        handler(this._currentRemovedItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportMRR(handler) {
    this.elements.exportMrrBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        const returnItems = this._currentRemovedItems.filter(i => i.status === 'إرجاع');
        if (returnItems.length === 0) {
          this.showNotification('لا توجد مواد إرجاع للتصدير', 'error');
          return;
        }
        handler(returnItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  bindExportMRRPdf(handler) {
    if (!this.elements.exportMrrPdfBtn) return;
    this.elements.exportMrrPdfBtn.addEventListener('click', () => {
      const checkboxes = this.elements.removedTable.querySelectorAll('.removed-checkbox:checked');
      const selectedItems = [];
      checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.index);
        if (this._currentRemovedItems[idx]) {
          selectedItems.push(this._currentRemovedItems[idx]);
        }
      });
      if (selectedItems.length === 0) {
        const returnItems = this._currentRemovedItems.filter(i => i.status === 'إرجاع');
        if (returnItems.length === 0) {
          this.showNotification('لا توجد مواد إرجاع للتصدير', 'error');
          return;
        }
        handler(returnItems);
      } else {
        handler(selectedItems);
      }
    });
  },

  async renderAttachments(attachments) {
    this.elements.attachmentsList.innerHTML = '';
    if (!attachments || attachments.length === 0) {
      this.elements.attachmentsList.innerHTML = '<p class="no-attachments">لا توجد مرفقات</p>';
      return;
    }
    for (const att of attachments) {
      const size = att.file_size ? (att.file_size / 1024).toFixed(1) + ' KB' : '';
      const date = new Date(att.created_at).toLocaleDateString('ar-SA');
      const fileUrl = await LocalFileStore.getFileURL(att.file_url);
      const item = document.createElement('div');
      item.className = 'attachment-item';
      item.innerHTML = `
        <div class="attachment-info">
          <span class="attachment-icon">📄</span>
          <a href="${fileUrl}" target="_blank" class="attachment-name">${att.file_name}</a>
          <span class="attachment-meta">${size} • ${date}</span>
        </div>
        <button class="btn-icon btn-delete-attachment" data-id="${att.id}" title="حذف">🗑️</button>`;
      this.elements.attachmentsList.appendChild(item);
    }
  },

  bindUploadAttachment(handler) {
    this.elements.attachmentInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (files.length > 0) handler(files);
      e.target.value = '';
    });
  },

  bindDeleteAttachment(handler) {
    this.elements.attachmentsList.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-delete-attachment');
      if (btn && confirm('هل تريد حذف هذا المرفق؟')) {
        handler(btn.dataset.id);
      }
    });
  }
};

export default AppView;
