<?php
$requirements = checkSystemRequirements();
?>

<h2>فحص متطلبات النظام</h2>
<p>يرجى التأكد من تلبية جميع المتطلبات التالية:</p>

<div class="requirements-list">
    <div class="requirement">
        <span>إصدار PHP (8.3 أو أحدث)</span>
        <span class="<?php echo $requirements['php_version'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['php_version'] ? 'check' : 'times'; ?>"></i>
            <?php echo PHP_VERSION; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>امتداد PDO MySQL</span>
        <span class="<?php echo $requirements['pdo_mysql'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['pdo_mysql'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['pdo_mysql'] ? 'متوفر' : 'غير متوفر'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>امتداد Mbstring</span>
        <span class="<?php echo $requirements['mbstring'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['mbstring'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['mbstring'] ? 'متوفر' : 'غير متوفر'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>امتداد OpenSSL</span>
        <span class="<?php echo $requirements['openssl'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['openssl'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['openssl'] ? 'متوفر' : 'غير متوفر'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>امتداد Fileinfo</span>
        <span class="<?php echo $requirements['fileinfo'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['fileinfo'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['fileinfo'] ? 'متوفر' : 'غير متوفر'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>امتداد GD</span>
        <span class="<?php echo $requirements['gd'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['gd'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['gd'] ? 'متوفر' : 'غير متوفر'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>صلاحيات الكتابة - مجلد uploads</span>
        <span class="<?php echo $requirements['uploads_writable'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['uploads_writable'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['uploads_writable'] ? 'قابل للكتابة' : 'غير قابل للكتابة'; ?>
        </span>
    </div>
    
    <div class="requirement">
        <span>صلاحيات الكتابة - مجلد config</span>
        <span class="<?php echo $requirements['config_writable'] ? 'status-ok' : 'status-error'; ?>">
            <i class="fas fa-<?php echo $requirements['config_writable'] ? 'check' : 'times'; ?>"></i>
            <?php echo $requirements['config_writable'] ? 'قابل للكتابة' : 'غير قابل للكتابة'; ?>
        </span>
    </div>
</div>

<?php if (!$requirements['all_passed']): ?>
    <div class="alert alert-danger mt-4">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>متطلبات غير مستوفاة</h5>
        <p>يرجى تلبية جميع المتطلبات المذكورة أعلاه قبل المتابعة. اتصل بمدير الخادم إذا كنت بحاجة للمساعدة.</p>
    </div>
    
    <div class="d-grid gap-2 mt-4">
        <button onclick="location.reload()" class="btn btn-warning btn-lg">
            <i class="fas fa-redo me-2"></i>
            إعادة الفحص
        </button>
    </div>
<?php else: ?>
    <div class="alert alert-success mt-4">
        <h5><i class="fas fa-check-circle me-2"></i>جميع المتطلبات مستوفاة</h5>
        <p>يمكنك الآن المتابعة إلى الخطوة التالية لإعداد قاعدة البيانات.</p>
    </div>
    
    <form method="POST">
        <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>
                المتابعة إلى إعداد قاعدة البيانات
            </button>
        </div>
    </form>
<?php endif; ?>
