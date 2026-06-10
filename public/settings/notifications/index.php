<?php
$pageTitle = 'إدارة الإشعارات';
$currentPage = 'notification-settings';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '../../index.php'],
    ['title' => 'إعدادات النظام', 'url' => '#'],
    ['title' => 'إدارة الإشعارات', 'url' => '']
];

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// التحقق من الصلاحيات (نكتفي بصلاحية مدير النظام هنا مؤقتاً)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// يمكن إضافة صلاحية خاصة هنا لاحقاً، مثلا hasPermission('manage_settings')

ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fas fa-bell text-primary me-2"></i>
                إدارة الإشعارات والديناميكية
            </h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNotificationModal">
                <i class="fas fa-plus me-1"></i> إضافة مستلم جديد
            </button>
        </div>
    </div>

    <!-- بطاقة التوضيح -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-light border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-primary"><i class="fas fa-info-circle me-1"></i> كيف تعمل هذه الصفحة؟
                    </h6>
                    <p class="card-text text-muted mb-0 small">
                        من خلال هذه الصفحة يمكنك التحكم في الجهات (أرقام واتساب فردية، مجموعات واتساب، أو بريد إلكتروني)
                        التي سيتم إرسال الإشعارات إليها عند حدوث حدث معين في النظام (مثل: تقديم طلب صرف جديد).
                        يمكنك إضافة عدد لا نهائي من المستلمين لكل حدث وتفعيلهم أو إيقافهم بضغطة زر.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="notificationsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">الاسم / الوصف</th>
                                    <th class="px-4 py-3">نوع الإشعار</th>
                                    <th class="px-4 py-3">الوجهة (الرقم / الإيميل / القروب)</th>
                                    <th class="px-4 py-3">الحدث المرتبط</th>
                                    <th class="px-4 py-3 text-center">الحالة</th>
                                    <th class="px-4 py-3 text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- يتم جلب البيانات عبر AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة/تعديل مستلم -->
<div class="modal fade" id="addNotificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalTitle">إضافة مستلم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notificationForm">
                <div class="modal-body">
                    <input type="hidden" id="notificationId" name="id">

                    <div class="mb-3">
                        <label class="form-label">الحدث (العملية في النظام) <span class="text-danger">*</span></label>
                        <select class="form-select" id="eventName" name="event_name" required>
                            <option value="">اختر الحدث...</option>
                            <option value="material_request_submit">عند تقديم طلب صرف جديد (للمستودع)</option>
                            <option value="work_order_created_connections">أمر عمل جديد (قسم التوصيلات)</option>
                            <option value="work_order_created_projects">أمر عمل جديد (قسم المشاريع)</option>
                            <!-- يمكن إضافة المزيد لاحقاً -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">طريقة الإرسال <span class="text-danger">*</span></label>
                        <select class="form-select" id="notificationType" name="notification_type" required>
                            <option value="">اختر الطريقة...</option>
                            <option value="whatsapp_personal">واتساب (رسالة فردية)</option>
                            <option value="whatsapp_group">واتساب (مجموعة)</option>
                            <option value="email">بريد إلكتروني</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الوجهة (الرقم أو الإيميل) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="recipient" name="recipient" required
                            placeholder="مثال: 9665xxxxxxxx أو mygroup@g.us أو test@example.com">
                        <div class="form-text text-muted small mt-1" id="recipientHelp"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">اسم وصفي (اختياري)</label>
                        <input type="text" class="form-control" id="name" name="name"
                            placeholder="مثال: قروب المستودع المركزي">
                    </div>

                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1"
                            checked>
                        <label class="form-check-label" for="isActive">تفعيل هذا الإشعار</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let dt = new DataTable('#notificationsTable', {
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
            ajax: {
                url: 'ajax.php?action=list',
                dataSrc: 'data'
            },
            columns: [
                {
                    data: 'name', render: function (data) {
                        return data ? `<strong>${data}</strong>` : '<span class="text-muted">بدون اسم</span>';
                    }
                },
                {
                    data: 'notification_type', render: function (data) {
                        if (data === 'whatsapp_personal') return '<span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i> واتساب فردي</span>';
                        if (data === 'whatsapp_group') return '<span class="badge bg-primary"><i class="fas fa-users me-1"></i> مجموعة واتساب</span>';
                        if (data === 'email') return '<span class="badge bg-info"><i class="fas fa-envelope me-1"></i> بريد إلكتروني</span>';
                        return data;
                    }
                },
                {
                    data: 'recipient', render: function (data, type, row) {
                        return `<code class="bg-light px-2 py-1 rounded text-dark border">${data}</code>`;
                    }
                },
                {
                    data: 'event_name', render: function (data) {
                        if (data === 'material_request_submit') return 'تقديم طلب صرف جديد';
                        if (data === 'work_order_created_connections') return 'أمر عمل جديد (قسم التوصيلات)';
                        if (data === 'work_order_created_projects') return 'أمر عمل جديد (قسم المشاريع)';
                        return data;
                    }
                },
                {
                    data: 'is_active', className: 'text-center', render: function (data, type, row) {
                        const isChecked = parseInt(data) === 1 ? 'checked' : '';
                        return `
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input toggle-status" type="checkbox" data-id="${row.id}" ${isChecked}>
                    </div>
                `;
                    }
                },
                {
                    data: 'id', className: 'text-center', render: function (data, type, row) {
                        return `
                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${data}" title="حذف">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                    }
                }
            ]
        });

        // تحديث النص المساعد بناءً على نوع الإرسال
        document.getElementById('notificationType').addEventListener('change', function () {
            const type = this.value;
            const help = document.getElementById('recipientHelp');
            if (type === 'whatsapp_personal') {
                help.innerHTML = 'يجب أن يبدأ برمز الدولة بدون + (مثال: 9665...)';
            } else if (type === 'whatsapp_group') {
                help.innerHTML = 'معرف المجموعة ينتهي بـ @g.us (مثال: 123456789-123456@g.us)';
            } else if (type === 'email') {
                help.innerHTML = 'البريد الإلكتروني بصيغة صحيحة (مثال: name@domain.com)';
            } else {
                help.innerHTML = '';
            }
        });

        // إضافة/تعديل مستلم
        document.getElementById('notificationForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...';

            const formData = new FormData(this);
            formData.append('action', 'save');

            // إذا لم يكن الـ checkbox مفعل، لن يتم إرسال قيمته في FormData، لذلك نقوم بمعالجته
            if (!document.getElementById('isActive').checked) {
                formData.set('is_active', '0');
            }

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ',
                            text: 'تم حفظ إعدادات الإشعار بنجاح',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        bootstrap.Modal.getInstance(document.getElementById('addNotificationModal')).hide();
                        dt.ajax.reload();
                        this.reset();
                    } else {
                        throw new Error(data.message || 'حدث خطأ غير متوقع');
                    }
                })
                .catch(err => {
                    Swal.fire('خطأ', err.message, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'حفظ';
                });
        });

        // إعادة تعيين النموذج عند فتح المودال للإضافة
        document.getElementById('addNotificationModal').addEventListener('show.bs.modal', function (e) {
            if (e.relatedTarget && e.relatedTarget.hasAttribute('data-bs-target')) {
                document.getElementById('notificationForm').reset();
                document.getElementById('notificationId').value = '';
                document.getElementById('modalTitle').textContent = 'إضافة مستلم جديد';
            }
        });

        // تغيير حالة التفعيل (Toggle Status)
        document.querySelector('#notificationsTable').addEventListener('change', function (e) {
            if (e.target.classList.contains('toggle-status')) {
                const id = e.target.dataset.id;
                const status = e.target.checked ? 1 : 0;

                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', id);
                formData.append('status', status);

                fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (!data.success) {
                        e.target.checked = !e.target.checked;
                        Swal.fire('خطأ', 'لم يتم تحديث الحالة', 'error');
                    }
                });
            }
        });

        // حذف مستلم
        document.querySelector('#notificationsTable').addEventListener('click', function (e) {
            const btn = e.target.closest('.delete-btn');
            if (btn) {
                const id = btn.dataset.id;
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "لن تتمكن من التراجع عن هذا الإجراء!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'delete');
                        formData.append('id', id);

                        fetch('ajax.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    dt.ajax.reload();
                                    Swal.fire('تم الحذف!', 'تم حذف المستلم بنجاح.', 'success');
                                } else {
                                    Swal.fire('خطأ', data.message, 'error');
                                }
                            });
                    }
                });
            }
        });
    });
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
require_once '../../includes/layout.php';
?>