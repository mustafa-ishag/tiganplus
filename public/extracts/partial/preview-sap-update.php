<?php
/**
 * صفحة معاينة تحديثات SAP قبل التأكيد
 * Preview SAP Updates Before Confirmation
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = 'معاينة تحديثات SAP';
$error = '';
$previewData = null;

// التحقق من وجود بيانات المعاينة في الجلسة
if (!isset($_SESSION['sap_preview_data'])) {
    header('Location: update-sap-entry-number.php');
    exit();
}

$previewData = $_SESSION['sap_preview_data'];

// الاتصال بقاعدة البيانات
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - نظام تِقان</title>

    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        .border-left-danger {
            border-left: 0.25rem solid #e74a3b !important;
        }
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        .table {
            font-size: 0.9rem;
        }
        .badge {
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/etganplus/public/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php">المستخلصات الجزئية</a></li>
                <li class="breadcrumb-item"><a href="update-sap-entry-number.php">تحديث SAP</a></li>
                <li class="breadcrumb-item active">معاينة</li>
            </ol>
        </nav>
    </div>

    <!-- إحصائيات المعاينة -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي السجلات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($previewData['valid_records']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                جاهز للتحديث
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($previewData['valid_records']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                سيتم التخطي
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $previewData['skipped_count']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                أخطاء
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($previewData['errors']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- أزرار التأكيد والإلغاء -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-question-circle me-2"></i>
                تأكيد التحديث
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>تنبيه:</strong> سيتم تحديث <strong><?php echo count($previewData['valid_records']); ?></strong> مستخلص جزئي.
                يرجى مراجعة البيانات أدناه قبل التأكيد.
            </div>

            <form method="POST" action="confirm-sap-update.php">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="update-sap-entry-number.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                    <button type="submit" class="btn btn-success" onclick="return confirm('هل أنت متأكد من تأكيد التحديث؟')">
                        <i class="fas fa-check me-2"></i>
                        تأكيد التحديث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول المعاينة -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-table me-2"></i>
                السجلات الجاهزة للتحديث (<?php echo count($previewData['valid_records']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم المستخلص (SAP)</th>
                            <th>رقم المستخلص (قاعدة البيانات)</th>
                            <th>رقم PO</th>
                            <th>رقم صحيفة الإدخال</th>
                            <th>تاريخ الصرف</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['valid_records'] as $index => $record): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($record['extract_number_sap']); ?></span></td>
                            <td><?php echo htmlspecialchars($record['extract_number_db']); ?></td>
                            <td><strong><?php echo htmlspecialchars($record['po_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($record['entry_sheet_number']); ?></td>
                            <td><?php echo htmlspecialchars($record['disbursement_date'] ?? '-'); ?></td>
                            <td><span class="badge bg-success">جاهز</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- جدول الأخطاء -->
    <?php if (!empty($previewData['errors'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                الأخطاء (<?php echo count($previewData['errors']); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم الصف</th>
                            <th>رقم المستخلص</th>
                            <th>رقم PO</th>
                            <th>رقم صحيفة الإدخال</th>
                            <th>الخطأ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['errors'] as $error): ?>
                        <tr>
                            <td><?php echo $error['row']; ?></td>
                            <td><?php echo htmlspecialchars($error['extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($error['po_number'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($error['entry_sheet_number']); ?></td>
                            <td><span class="text-danger"><?php echo htmlspecialchars($error['error']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

