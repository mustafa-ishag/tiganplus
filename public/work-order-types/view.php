<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../../bootstrap/app.php';

use EtganERP\Infrastructure\Persistence\WorkOrderTypeRepository;
use EtganERP\Domain\Shared\ValueObjects\Id;

// التحقق من وجود معرف نوع أمر العمل
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$typeId = (int) $_GET['id'];

try {
    // جلب نوع أمر العمل
    $workOrderTypeRepository = new WorkOrderTypeRepository();
    $workOrderType = $workOrderTypeRepository->findById(new Id($typeId));
    
    if (!$workOrderType) {
        header('Location: index.php?error=' . urlencode('نوع أمر العمل غير موجود'));
        exit;
    }
    
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('حدث خطأ أثناء جلب البيانات'));
    exit;
}

$pageTitle = 'عرض نوع أمر العمل: ' . $workOrderType->code()->value();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - نظام إتقان ERP</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .info-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        
        .info-item:hover {
            background-color: #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            color: #212529;
            font-size: 1.1rem;
        }
        
        .btn-custom {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        
        .code-badge {
            font-size: 1.2rem;
            padding: 0.75rem 1.5rem;
            border-radius: 15px;
            font-weight: bold;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-eye me-3"></i>
                        عرض نوع أمر العمل
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">تفاصيل نوع أمر العمل</p>
                </div>
                <div class="col-md-6 text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-end bg-transparent mb-0">
                            <li class="breadcrumb-item">
                                <a href="<?php echo path('../dashboard.php'); ?>" class="text-white text-decoration-none">
                                    <i class="fas fa-home"></i> الرئيسية
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="<?php echo path('index.php'); ?>" class="text-white text-decoration-none">أنواع أوامر العمل</a>
                            </li>
                            <li class="breadcrumb-item active text-white-50" aria-current="page">عرض</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Work Order Type Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            تفاصيل نوع أمر العمل
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <!-- Type Code -->
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-code me-2 text-primary"></i>
                                كود النوع
                            </div>
                            <div class="info-value">
                                <span class="badge bg-primary code-badge">
                                    <?php echo htmlspecialchars($workOrderType->code()->value()); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-align-left me-2 text-info"></i>
                                الوصف
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($workOrderType->description()?->value() ?? 'لا يوجد وصف'); ?>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-toggle-on me-2 text-success"></i>
                                الحالة
                            </div>
                            <div class="info-value">
                                <?php if ($workOrderType->isActive()): ?>
                                    <span class="badge bg-success status-badge">
                                        <i class="fas fa-check-circle me-1"></i>
                                        نشط
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger status-badge">
                                        <i class="fas fa-times-circle me-1"></i>
                                        غير نشط
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Created At -->
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus me-2 text-secondary"></i>
                                تاريخ الإنشاء
                            </div>
                            <div class="info-value">
                                <?php echo $workOrderType->createdAt()->format('Y-m-d H:i:s'); ?>
                            </div>
                        </div>

                        <!-- Updated At -->
                        <?php if ($workOrderType->updatedAt()): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-edit me-2 text-warning"></i>
                                تاريخ آخر تحديث
                            </div>
                            <div class="info-value">
                                <?php echo $workOrderType->updatedAt()->format('Y-m-d H:i:s'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center mt-4">
                    <a href="<?php echo path('index.php'); ?>" class="btn btn-secondary btn-custom me-3">
                        <i class="fas fa-arrow-right me-2"></i>
                        العودة للقائمة
                    </a>
                    
                    <a href="edit.php?id=<?php echo $workOrderType->id()->value(); ?>" class="btn btn-warning btn-custom me-3">
                        <i class="fas fa-edit me-2"></i>
                        تعديل
                    </a>
                    
                    <?php if ($workOrderType->canBeDeleted()): ?>
                    <button type="button" class="btn btn-danger btn-custom" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>
                        حذف
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmDelete() {
            if (confirm('هل أنت متأكد من حذف نوع أمر العمل هذا؟\nهذا الإجراء لا يمكن التراجع عنه.')) {
                window.location.href = 'delete.php?id=<?php echo $workOrderType->id()->value(); ?>';
            }
        }
    </script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
