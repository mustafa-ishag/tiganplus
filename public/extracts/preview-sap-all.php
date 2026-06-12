<?php
/**
 * صفحة معاينة تحديث SAP الشامل
 * Preview Unified SAP Update
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['sap_all_preview_data'])) {
    header('Location: update-sap-all.php');
    exit();
}

$previewData = $_SESSION['sap_all_preview_data'];
$pageTitle = 'معاينة تحديث SAP الشامل';
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'تحديث SAP الشامل', 'url' => 'extracts/update-sap-all.php'],
    ['title' => 'المعاينة', 'url' => '#']
];

$totalValid = count($previewData['partial_records']) + count($previewData['final_regular_records']) + count($previewData['final_for_partial_records']);

ob_start();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-search me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
    </div>

    <!-- ملخص التصنيف -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">المستخلصات الجزئية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($previewData['partial_records']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-alt fa-2x text-primary"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">النهائية للجزئية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($previewData['final_for_partial_records']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-contract fa-2x text-warning"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">النهائية العادية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($previewData['final_regular_records']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-invoice fa-2x text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">أخطاء / تخطي</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($previewData['errors']); ?> / <?php echo $previewData['skipped_count']; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-danger"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- المستخلصات الجزئية -->
    <?php if (!empty($previewData['partial_records'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-file-alt me-2"></i>
                المستخلصات الجزئية (<?php echo count($previewData['partial_records']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr><th>#</th><th>رقم المستخلص (SAP)</th><th>رقم PO</th><th>رقم صحيفة الإدخال</th><th>القديم</th><th>تاريخ الصرف</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['partial_records'] as $index => $record): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($record['extract_number_sap']); ?></strong></td>
                            <td><?php echo htmlspecialchars($record['po_number']); ?></td>
                            <td class="text-success"><strong><?php echo htmlspecialchars($record['entry_sheet_number']); ?></strong></td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['old_entry_sheet_number'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($record['disbursement_date'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- المستخلصات النهائية للجزئية -->
    <?php if (!empty($previewData['final_for_partial_records'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-warning text-dark">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-file-contract me-2"></i>
                المستخلصات النهائية للجزئية (<?php echo count($previewData['final_for_partial_records']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr><th>#</th><th>رقم المستخلص (SAP)</th><th>رقم PO</th><th>رقم صحيفة الإدخال</th><th>القديم</th><th>تاريخ الصرف</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['final_for_partial_records'] as $index => $record): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($record['extract_number_sap']); ?></strong></td>
                            <td><?php echo htmlspecialchars($record['po_number']); ?></td>
                            <td class="text-success"><strong><?php echo htmlspecialchars($record['entry_sheet_number']); ?></strong></td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['old_entry_sheet_number'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($record['disbursement_date'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- المستخلصات النهائية العادية -->
    <?php if (!empty($previewData['final_regular_records'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-success text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-file-invoice me-2"></i>
                المستخلصات النهائية العادية (<?php echo count($previewData['final_regular_records']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr><th>#</th><th>رقم المستخلص (SAP)</th><th>رقم PO</th><th>رقم صحيفة الإدخال</th><th>القديم</th><th>تاريخ الصرف</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['final_regular_records'] as $index => $record): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($record['extract_number_sap']); ?></strong></td>
                            <td><?php echo htmlspecialchars($record['po_number']); ?></td>
                            <td class="text-success"><strong><?php echo htmlspecialchars($record['entry_sheet_number']); ?></strong></td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['old_entry_sheet_number'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($record['disbursed_date'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- الأخطاء -->
    <?php if (!empty($previewData['errors'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-danger text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                الأخطاء (<?php echo count($previewData['errors']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr><th>صف</th><th>رقم المستخلص</th><th>رقم صحيفة الإدخال</th><th>الخطأ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['errors'] as $error): ?>
                        <tr>
                            <td><?php echo $error['row']; ?></td>
                            <td><?php echo htmlspecialchars($error['extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($error['entry_sheet_number']); ?></td>
                            <td class="text-danger"><?php echo htmlspecialchars($error['error']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- أزرار التأكيد -->
    <div class="card shadow mb-4">
        <div class="card-body text-center">
            <?php if ($totalValid > 0): ?>
            <div class="alert alert-warning mb-3">
                <i class="fas fa-exclamation-circle me-2"></i>
                سيتم تحديث <strong><?php echo $totalValid; ?></strong> مستخلص عبر الأنواع الثلاثة. هل أنت متأكد؟
            </div>
            <a href="confirm-sap-all.php" class="btn btn-lg btn-success me-2">
                <i class="fas fa-check-circle me-2"></i>
                تأكيد التحديث الشامل (<?php echo $totalValid; ?> مستخلص)
            </a>
            <?php else: ?>
            <div class="alert alert-danger mb-3">
                <i class="fas fa-times-circle me-2"></i>
                لا توجد مستخلصات صالحة للتحديث.
            </div>
            <?php endif; ?>
            <a href="update-sap-all.php" class="btn btn-lg btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                رفع ملف آخر
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
