/**
 * ملف JavaScript الرئيسي لنظام تِقان
 * Main JavaScript File for Tiqan ERP System
 */

// متغيرات عامة
const TiqanERP = {
    baseUrl: window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1),
    currentUser: null,
    permissions: [],
    
    // إعدادات عامة
    settings: {
        dateFormat: 'dd/mm/yyyy',
        currency: 'SAR',
        language: 'ar',
        recordsPerPage: 25
    },
    
    // دوال المساعدة
    utils: {
        // تنسيق التاريخ
        formatDate: function(date, format = 'dd/mm/yyyy') {
            if (!date) return '';
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            
            switch(format) {
                case 'dd/mm/yyyy':
                    return `${day}/${month}/${year}`;
                case 'yyyy-mm-dd':
                    return `${year}-${month}-${day}`;
                default:
                    return date;
            }
        },
        
        // تنسيق الأرقام
        formatNumber: function(num, decimals = 2) {
            if (isNaN(num)) return '0';
            return parseFloat(num).toLocaleString('ar-SA', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },
        
        // تنسيق العملة
        formatCurrency: function(amount) {
            return this.formatNumber(amount, 2) + ' ريال';
        },
        
        // التحقق من صحة البريد الإلكتروني
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // التحقق من صحة رقم الهاتف السعودي
        validateSaudiPhone: function(phone) {
            const re = /^(05|5)[0-9]{8}$/;
            return re.test(phone);
        },
        
        // تنظيف النص
        sanitizeText: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // توليد معرف فريد
        generateId: function() {
            return Date.now().toString(36) + Math.random().toString(36).substr(2);
        }
    },
    
    // دوال AJAX
    ajax: {
        // طلب GET
        get: function(url, data = {}, callback = null) {
            return $.ajax({
                url: TiqanERP.baseUrl + url,
                method: 'GET',
                data: data,
                dataType: 'json',
                success: callback,
                error: function(xhr, status, error) {
                    TiqanERP.ui.showError('حدث خطأ في الاتصال: ' + error);
                }
            });
        },
        
        // طلب POST
        post: function(url, data = {}, callback = null) {
            return $.ajax({
                url: TiqanERP.baseUrl + url,
                method: 'POST',
                data: data,
                dataType: 'json',
                success: callback,
                error: function(xhr, status, error) {
                    TiqanERP.ui.showError('حدث خطأ في الإرسال: ' + error);
                }
            });
        },
        
        // رفع ملف
        uploadFile: function(url, formData, callback = null, progressCallback = null) {
            return $.ajax({
                url: TiqanERP.baseUrl + url,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    if (progressCallback) {
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                const percentComplete = evt.loaded / evt.total * 100;
                                progressCallback(percentComplete);
                            }
                        }, false);
                    }
                    return xhr;
                },
                success: callback,
                error: function(xhr, status, error) {
                    TiqanERP.ui.showError('فشل في رفع الملف: ' + error);
                }
            });
        }
    },
    
    // دوال واجهة المستخدم
    ui: {
        // عرض رسالة نجاح
        showSuccess: function(message, title = 'تم بنجاح') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'success',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#667eea'
            });
        },
        
        // عرض رسالة خطأ
        showError: function(message, title = 'خطأ') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'error',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#e74a3b'
            });
        },
        
        // عرض رسالة تحذير
        showWarning: function(message, title = 'تحذير') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'warning',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#f6c23e'
            });
        },
        
        // عرض رسالة معلومات
        showInfo: function(message, title = 'معلومات') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'info',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#36b9cc'
            });
        },
        
        // تأكيد الحذف
        confirmDelete: function(callback, message = 'هل أنت متأكد من الحذف؟') {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed && callback) {
                    callback();
                }
            });
        },
        
        // عرض مؤشر التحميل
        showLoading: function(message = 'جاري التحميل...') {
            Swal.fire({
                title: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },
        
        // إخفاء مؤشر التحميل
        hideLoading: function() {
            Swal.close();
        },
        
        // عرض نافذة منبثقة مخصصة
        showModal: function(title, content, buttons = []) {
            const modalId = 'customModal_' + TiqanERP.utils.generateId();
            const modal = $(`
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${content}
                            </div>
                            <div class="modal-footer">
                                ${buttons.map(btn => `<button type="button" class="btn btn-${btn.type || 'secondary'}" onclick="${btn.onclick || ''}">${btn.text}</button>`).join('')}
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            const bsModal = new bootstrap.Modal(document.getElementById(modalId));
            bsModal.show();
            
            // حذف النافذة عند الإغلاق
            modal.on('hidden.bs.modal', function() {
                modal.remove();
            });
            
            return bsModal;
        }
    },
    
    // دوال الجداول
    tables: {
        // إعداد DataTable
        init: function(tableId, options = {}) {
            const defaultOptions = {
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                },
                responsive: true,
                pageLength: TiqanERP.settings.recordsPerPage,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                order: [[0, 'desc']],
                columnDefs: [
                    {
                        targets: 'no-sort',
                        orderable: false
                    }
                ]
            };
            
            const finalOptions = $.extend(true, {}, defaultOptions, options);
            return $('#' + tableId).DataTable(finalOptions);
        },
        
        // تصدير إلى Excel
        exportToExcel: function(tableId, filename = 'export') {
            const table = document.getElementById(tableId);
            if (!table) {
                TiqanERP.ui.showError('الجدول غير موجود');
                return;
            }
            
            // استخدام SheetJS لتصدير Excel
            if (typeof XLSX !== 'undefined') {
                const wb = XLSX.utils.table_to_book(table);
                XLSX.writeFile(wb, filename + '.xlsx');
            } else {
                TiqanERP.ui.showError('مكتبة التصدير غير متوفرة');
            }
        },
        
        // تصدير إلى CSV
        exportToCSV: function(tableId, filename = 'export') {
            const table = document.getElementById(tableId);
            if (!table) {
                TiqanERP.ui.showError('الجدول غير موجود');
                return;
            }
            
            let csv = '';
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                const csvRow = [];
                
                for (let j = 0; j < cols.length; j++) {
                    csvRow.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                }
                
                csv += csvRow.join(',') + '\n';
            }
            
            // تنزيل الملف
            const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename + '.csv';
            link.click();
        }
    },
    
    // دوال النماذج
    forms: {
        // التحقق من صحة النموذج
        validate: function(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            return isValid;
        },
        
        // إعادة تعيين النموذج
        reset: function(formId) {
            const form = document.getElementById(formId);
            if (form) {
                form.reset();
                form.querySelectorAll('.is-invalid, .is-valid').forEach(field => {
                    field.classList.remove('is-invalid', 'is-valid');
                });
            }
        },
        
        // تعبئة النموذج بالبيانات
        populate: function(formId, data) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = data[key];
                    } else if (field.type === 'radio') {
                        const radio = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        field.value = data[key];
                    }
                }
            });
        },
        
        // الحصول على بيانات النموذج
        getData: function(formId) {
            const form = document.getElementById(formId);
            if (!form) return {};
            
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            return data;
        }
    }
};

// تهيئة النظام عند تحميل الصفحة
$(document).ready(function() {
    // تفعيل tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // تفعيل popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // إعداد التواريخ
    $('input[type="date"]').each(function() {
        if (!$(this).val()) {
            $(this).val(new Date().toISOString().split('T')[0]);
        }
    });
    
    // إخفاء التنبيهات تلقائياً
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // تفعيل البحث السريع
    $('.quick-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const targetTable = $(this).data('target');
        
        if (targetTable) {
            $(`#${targetTable} tbody tr`).each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.includes(searchTerm));
            });
        }
    });
});

// تصدير الكائن للاستخدام العام
window.TiqanERP = TiqanERP;
