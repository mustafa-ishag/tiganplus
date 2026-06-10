<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('غير مصرح');
}

// التحقق من معرف المرفق
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('معرف المرفق غير صحيح');
}

$attachmentId = (int) $_GET['id'];
$db = getDB();

// جلب بيانات المرفق
$stmt = $db->prepare("
    SELECT woa.*, wo.work_order_number
    FROM work_order_attachments woa
    JOIN work_orders wo ON woa.work_order_id = wo.id
    WHERE woa.id = ?
");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    http_response_code(404);
    die('المرفق غير موجود');
}

// التحقق من وجود الملف
if (empty($attachment['file_path'])) {
    http_response_code(404);
    die('مسار الملف غير محدد في قاعدة البيانات');
}

// تحديد المسار الكامل للملف
// المسار المحفوظ في قاعدة البيانات نسبي من مجلد public
$filePath = __DIR__ . '/../' . $attachment['file_path'];

// التحقق من وجود الملف
if (!file_exists($filePath)) {
    http_response_code(404);
    die('الملف غير موجود على الخادم.<br>المسار المحفوظ: ' . htmlspecialchars($attachment['file_path']) . '<br>المسار الكامل: ' . htmlspecialchars($filePath));
}

// تحديد نوع المحتوى
$fileExtension = strtolower(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION));
$contentType = 'application/octet-stream';

$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain',
    'html' => 'text/html',
    'htm' => 'text/html'
];

if (isset($mimeTypes[$fileExtension])) {
    $contentType = $mimeTypes[$fileExtension];
}

// إذا كان PDF أو صورة، عرضه مباشرة في المتصفح
if (in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png', 'gif'])) {
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . $attachment['original_filename'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=3600');
    readfile($filePath);
    exit;
}

// للملفات الأخرى، عرض صفحة معلومات
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعراض المرفق - <?= htmlspecialchars($attachment['original_filename']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
        }
        .file-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .file-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .file-icon i {
            font-size: 3rem;
            color: white;
        }
        .file-name {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        .btn-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="file-info-card">
            <div class="file-icon">
                <i class="fas fa-file-<?= in_array($fileExtension, ['doc', 'docx']) ? 'word' : (in_array($fileExtension, ['xls', 'xlsx']) ? 'excel' : 'alt') ?>"></i>
            </div>
            
            <div class="file-name">
                <?= htmlspecialchars($attachment['original_filename']) ?>
            </div>

            <div class="info-row">
                <span class="info-label">رقم أمر العمل:</span>
                <span class="info-value"><?= htmlspecialchars($attachment['work_order_number']) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">نوع الملف:</span>
                <span class="info-value"><?= strtoupper($fileExtension) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">حجم الملف:</span>
                <span class="info-value"><?= number_format($attachment['file_size'] / 1024, 2) ?> KB</span>
            </div>

            <div class="info-row">
                <span class="info-label">تاريخ الرفع:</span>
                <span class="info-value"><?= date('Y-m-d H:i', strtotime($attachment['uploaded_at'])) ?></span>
            </div>

            <?php if (!empty($attachment['notes'])): ?>
            <div class="info-row">
                <span class="info-label">ملاحظات:</span>
                <span class="info-value"><?= htmlspecialchars($attachment['notes']) ?></span>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="download-attachment.php?id=<?= $attachmentId ?>" class="btn btn-download">
                    <i class="fas fa-download"></i>
                    تحميل الملف
                </a>
                <button class="btn btn-secondary" onclick="window.close()">
                    <i class="fas fa-times"></i>
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</body>
</html>

