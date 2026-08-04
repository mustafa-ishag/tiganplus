<?php

declare(strict_types=1);

use EtganERP\Application\WorkOrderType\UpdateWorkOrderType\UpdateWorkOrderTypeCommand;
use EtganERP\Application\WorkOrderType\UpdateWorkOrderType\UpdateWorkOrderTypeHandler;
use EtganERP\Infrastructure\Persistence\WorkOrderTypeRepository;
use EtganERP\Domain\Shared\ValueObjects\Id;

// تحميل التطبيق
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// التحقق من معرف نوع أمر العمل
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$typeId = new Id((int) $_GET['id']);
$workOrderTypeRepository = new WorkOrderTypeRepository();
$workOrderType = $workOrderTypeRepository->findById($typeId);

if (!$workOrderType) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// معالجة تحديث نوع أمر العمل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;

        if (empty($name)) {
            throw new InvalidArgumentException('يرجى إدخال اسم نوع أمر العمل');
        }

        // إنشاء الأمر والمعالج
        $command = new UpdateWorkOrderTypeCommand($workOrderType->id()->value(), $name, $description);
        $updateWorkOrderTypeHandler = new UpdateWorkOrderTypeHandler($workOrderTypeRepository);

        // تنفيذ تحديث نوع أمر العمل
        $response = $updateWorkOrderTypeHandler->handle($command);

        $success = $response->message;
        
        // إعادة تحميل بيانات نوع أمر العمل
        $workOrderType = $workOrderTypeRepository->findById($typeId);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل نوع أمر العمل - <?php echo $app['name']; ?></title>
    
    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Arabic -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .page-title {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #718096;
            margin-bottom: 0;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #176cb4;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            border-radius: 10px;
            padding: 12px 30px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: #176cb4;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل العلوي -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo path('../dashboard.php'); ?>">
                <i class="fas fa-building me-2"></i>
                <?php echo $app['name']; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <!-- رأس الصفحة -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo path('../dashboard.php'); ?>">لوحة التحكم</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo path('index.php'); ?>">أنواع أوامر العمل</a></li>
                            <li class="breadcrumb-item active">تعديل النوع</li>
                        </ol>
                    </nav>
                    <h1 class="page-title">
                        <i class="fas fa-edit me-2"></i>
                        تعديل نوع أمر العمل
                    </h1>
                    <p class="page-subtitle">تعديل بيانات النوع: <?php echo htmlspecialchars($workOrderType->name()->value()); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo path('index.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-2"></i>
                        العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        
        <!-- محتوى الصفحة -->
        <div class="content-card">
            <!-- معلومات نوع أمر العمل الحالية -->
            <div class="info-card">
                <div class="row">
                    <div class="col-md-3">
                        <strong>المعرف:</strong><br>
                        <span class="badge bg-info"><?php echo $workOrderType->id()->value(); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>الحالة:</strong><br>
                        <?php if ($workOrderType->isActive()): ?>
                            <span class="badge bg-success">نشط</span>
                        <?php else: ?>
                            <span class="badge bg-danger">غير نشط</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>تاريخ الإنشاء:</strong><br>
                        <?php echo $workOrderType->createdAt()->format('Y-m-d H:i'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>آخر تحديث:</strong><br>
                        <?php echo $workOrderType->updatedAt()?->format('Y-m-d H:i') ?? 'لم يتم التحديث'; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="editWorkOrderTypeForm">
                <div class="form-floating">
                    <input type="text" class="form-control" id="name" name="name" 
                           placeholder="اسم نوع أمر العمل" required maxlength="100"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $workOrderType->name()->value()); ?>">
                    <label for="name">
                        <i class="fas fa-tag me-2"></i>اسم نوع أمر العمل *
                    </label>
                </div>
                
                <div class="form-floating">
                    <textarea class="form-control" id="description" name="description" 
                              placeholder="وصف نوع أمر العمل" style="height: 120px;" maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? $workOrderType->description()?->value() ?? ''); ?></textarea>
                    <label for="description">
                        <i class="fas fa-align-right me-2"></i>وصف نوع أمر العمل
                    </label>
                    <div class="form-text">
                        وصف اختياري لنوع أمر العمل (الحد الأقصى 500 حرف)
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="<?php echo path('index.php'); ?>" class="btn btn-secondary me-md-2">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // تركيز على حقل اسم نوع أمر العمل
        document.getElementById('name').focus();
        
        // التحقق من صحة النموذج
        document.getElementById('editWorkOrderTypeForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('يرجى إدخال اسم نوع أمر العمل');
                return;
            }
        });
    </script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
