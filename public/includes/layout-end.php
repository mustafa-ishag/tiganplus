</div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // PathHelper للجافا سكريبت
    class PathHelper {
        static getBasePath() {
            const path = window.location.pathname;
            const segments = path.split('/').filter(segment => segment !== '');
            const etganIndex = segments.indexOf('etganplus');

            if (etganIndex !== -1) {
                const depth = segments.length - etganIndex - 2; // -2 for etganplus and public
                return '../'.repeat(Math.max(0, depth));
            }

            return './';
        }

        static path(relativePath) {
            return this.getBasePath() + relativePath;
        }

        static asset(assetPath) {
            return this.getBasePath() + 'assets/' + assetPath;
        }
    }

    // دالة مساعدة للمسارات
    function path(relativePath) {
        return PathHelper.path(relativePath);
    }

    function asset(assetPath) {
        return PathHelper.asset(assetPath);
    }

    // تبديل الشريط الجانبي لجميع الشاشات
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const overlay = document.getElementById('sidebarOverlay');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            // للجوال: استخدام show/hide
            sidebar.classList.toggle('show');
            if (overlay) {
                if (sidebar.classList.contains('show')) {
                    overlay.classList.add('show');
                } else {
                    overlay.classList.remove('show');
                }
            }
        } else {
            // للشاشات الكبيرة: استخدام collapsed
            sidebar.classList.toggle('collapsed');

            if (sidebar.classList.contains('collapsed')) {
                if (mainContent) mainContent.classList.add('expanded');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                if (mainContent) mainContent.classList.remove('expanded');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    }

    // استعادة حالة الشريط الجانبي عند تحميل الصفحة
    function restoreSidebarState() {
        const isMobile = window.innerWidth <= 768;

        if (!isMobile) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');

                sidebar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('expanded');
            }
        }
    }

    // إغلاق الشريط الجانبي عند النقر خارجه في الجوال
    document.addEventListener('click', function (event) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (window.innerWidth <= 768 &&
            !sidebar.contains(event.target) &&
            sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });

    // إعداد AJAX العام
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', error);
            if (xhr.status === 401) {
                window.location.href = path('auth/login.php');
            }
        }
    });

    // دالة عرض التنبيهات
    function showAlert(type, message, title = '') {
        const icons = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info',
            danger: 'error'
        };

        try {
            Swal.fire({
                icon: icons[type] || 'info',
                title: title || (type === 'success' ? 'نجح!' : type === 'error' || type === 'danger' ? 'خطأ!' : 'تنبيه!'),
                text: message,
                confirmButtonText: 'موافق',
                confirmButtonColor: '#176cb4'
            });
        } catch (e) {
            // fallback في حال فشل SweetAlert2
            alert((title || (type === 'error' || type === 'danger' ? 'خطأ!' : 'تنبيه!')) + '\n' + message);
        }
    }

    // دالة تأكيد الحذف
    function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
        return Swal.fire({
            title: 'تأكيد الحذف',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        });
    }

    // دالة معالجة النماذج
    function handleFormSubmit(form, url, successCallback) {
        const formData = new FormData(form);

        return fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    if (successCallback) successCallback(data);
                } else {
                    showAlert('error', data.message);
                }
                return data;
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'حدث خطأ في الاتصال');
                throw error;
            });
    }

    // تهيئة DataTables بالإعدادات العربية
    function initDataTable(selector, options = {}) {
        const defaultOptions = {
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        };

        return $(selector).DataTable($.extend(defaultOptions, options));
    }

    // دالة تنسيق الأرقام
    function formatNumber(number, decimals = 2) {
        return new Intl.NumberFormat('ar-SA', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    // دالة تنسيق العملة
    function formatCurrency(amount, currency = 'SAR') {
        return new Intl.NumberFormat('ar-SA', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    // دالة تنسيق التاريخ
    function formatDate(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };

        return new Intl.DateTimeFormat('ar-SA', $.extend(defaultOptions, options)).format(new Date(date));
    }

    // معالجة تغيير حجم الشاشة
    function handleResize() {
        const sidebar = document.getElementById('sidebar');
        const navbar = document.querySelector('.navbar');
        const mainContent = document.querySelector('.main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        const toggleIcon = toggleBtn?.querySelector('i');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            // للجوال: إزالة classes الخاصة بالشاشات الكبيرة
            sidebar.classList.remove('collapsed');
            if (navbar) navbar.classList.remove('expanded');
            if (mainContent) mainContent.classList.remove('expanded');
            if (toggleIcon) toggleIcon.className = 'fas fa-bars';
        } else {
            // للشاشات الكبيرة: إزالة classes الخاصة بالجوال
            sidebar.classList.remove('show');
            // استعادة الحالة المحفوظة
            restoreSidebarState();
        }
    }

    // تحسين تجربة المستخدم
    $(document).ready(function () {
        // استعادة حالة الشريط الجانبي
        restoreSidebarState();

        // معالجة تغيير حجم الشاشة
        window.addEventListener('resize', handleResize);

        // تفعيل tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        // إضافة تأثيرات التحميل للأزرار
        $('form').on('submit', function () {
            const submitBtn = $(this).find('button[type="submit"]');
            if (submitBtn.length) {
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-2"></i>جاري المعالجة...');

                setTimeout(() => {
                    submitBtn.prop('disabled', false).html(originalText);
                }, 3000);
            }
        });

        // تحسين تجربة الجداول
        $('.table').on('mouseenter', 'tbody tr', function () {
            $(this).addClass('table-hover-effect');
        }).on('mouseleave', 'tbody tr', function () {
            $(this).removeClass('table-hover-effect');
        });

        // إضافة تأثيرات للبطاقات
        $('.card').hover(
            function () { $(this).addClass('shadow-lg'); },
            function () { $(this).removeClass('shadow-lg'); }
        );
    });
</script>

<style>
    .table-hover-effect {
        background-color: rgba(44, 90, 160, 0.05) !important;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    .card {
        transition: box-shadow 0.3s ease;
    }

    .btn {
        transition: all 0.2s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
    }

    .swal2-popup {
        font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif !important;
    }

    .swal2-title {
        font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif !important;
    }

    .swal2-content {
        font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
</style>
</body>

</html>