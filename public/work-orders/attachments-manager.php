<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_attachments')) {
    header('Location: index.php');
    exit;
}

// التحقق من وجود معرف أمر العمل
if (!isset($_GET['work_order_id']) || !is_numeric($_GET['work_order_id'])) {
    header('Location: index.php');
    exit;
}

$workOrderId = (int) $_GET['work_order_id'];
$db = getDB();

// جلب بيانات أمر العمل
$stmt = $db->prepare("
    SELECT 
        wo.*,
        b.name as branch_name,
        b.code as branch_code,
        wot.type_code,
        wot.description as work_order_type_description
    FROM work_orders wo
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    WHERE wo.id = ?
");
$stmt->execute([$workOrderId]);
$workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$workOrder) {
    header('Location: index.php');
    exit;
}

// تعريف جميع أنواع النماذج مع الحالات الافتراضية
$allFormTypes = [
    'precise_drilling_form' => 'not_applicable',
    'excavation_form' => 'not_applicable',
    'demolition_form' => 'not_applicable',
    'f1_form' => 'not_applicable',
    'assets_receipt_form' => 'not_applicable',
    'completion_certificate' => 'not_attached'
];

// جلب المرفقات الموجودة في قاعدة البيانات
$stmt = $db->prepare("
    SELECT * FROM work_order_attachments
    WHERE work_order_id = ?
    ORDER BY form_type
");
$stmt->execute([$workOrderId]);
$existingAttachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحويل المرفقات الموجودة إلى مصفوفة مفهرسة بنوع النموذج
$existingAttachmentsMap = [];
foreach ($existingAttachments as $attachment) {
    $existingAttachmentsMap[$attachment['form_type']] = $attachment;
}

// إنشاء قائمة المرفقات النهائية (موجودة + افتراضية)
$attachments = [];
foreach ($allFormTypes as $formType => $defaultStatus) {
    if (isset($existingAttachmentsMap[$formType])) {
        // استخدام البيانات الموجودة
        $attachments[] = $existingAttachmentsMap[$formType];
    } else {
        // إنشاء بيانات افتراضية
        $attachments[] = [
            'id' => null,
            'work_order_id' => $workOrderId,
            'form_type' => $formType,
            'status' => $defaultStatus,
            'completion_certificate_confirmation' => 'empty',
            'file_path' => null,
            'original_filename' => null,
            'file_size' => null,
            'file_type' => null,
            'uploaded_by' => null,
            'uploaded_at' => null,
            'notes' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    }
}

// حساب الإحصائيات
$totalAttachments = count($attachments);
$attachedCount = count(array_filter($attachments, fn($a) => $a['status'] === 'attached'));
$completionPercentage = $totalAttachments > 0 ? round(($attachedCount / $totalAttachments) * 100) : 0;

// جلب المستندات الأخرى (فقط التي تحتوي على ملفات)
$stmt = $db->prepare("
    SELECT * FROM work_order_attachments
    WHERE work_order_id = ?
    AND form_type = 'other_document'
    AND file_path IS NOT NULL
    AND original_filename IS NOT NULL
    ORDER BY uploaded_at DESC
");
$stmt->execute([$workOrderId]);
$otherDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تعريف أنواع النماذج
$formTypes = [
    'precise_drilling_form' => ['name' => 'نموذج الحفر الدقيق', 'icon' => 'fas fa-crosshairs'],
    'excavation_form' => ['name' => 'نموذج الكشط', 'icon' => 'fas fa-hard-hat'],
    'demolition_form' => ['name' => 'نموذج التخريد (الاسكراب)', 'icon' => 'fas fa-recycle'],
    'f1_form' => ['name' => 'نموذج F1', 'icon' => 'fas fa-file-alt'],
    'assets_receipt_form' => ['name' => 'استلام الأصول (إجراء 211)', 'icon' => 'fas fa-box-open'],
    'completion_certificate' => ['name' => 'شهادة الإنجاز', 'icon' => 'fas fa-certificate'],
    'other_document' => ['name' => 'مستندات أخرى', 'icon' => 'fas fa-file']
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدير المرفقات - أمر العمل <?= htmlspecialchars($workOrder['work_order_number']) ?></title>
    
    <!-- Bootstrap RTL CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Tajawal -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #176cb4;
            --secondary-color: #0e2942;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .header-top {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .work-order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .info-value {
            font-size: 1rem;
            color: var(--gray-900);
            font-weight: 600;
        }

        .attachments-section {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .section-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 24px;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .attachments-list {
            divide-y: 1px solid var(--gray-200);
        }

        .attachment-item {
            padding: 20px 24px;
            transition: background-color 0.2s;
        }

        .attachment-item:hover {
            background: var(--gray-50);
        }

        .attachment-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .attachment-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .attachment-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .attachment-meta {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .attachment-status {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-indicator {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .precise_drilling_form { background: var(--info-color); }
        .excavation_form { background: #f59e0b; } /* برتقالي للكشط */
        .demolition_form { background: #6b7280; } /* رمادي للتخريد */
        .f1_form { background: var(--primary-color); }
        .completion_certificate { background: var(--success-color); }
        .other_document { background: #8b5cf6; } /* بنفسجي للمستندات الأخرى */

        .status-not_attached {
            background: #fef3c7;
            color: #92400e;
        }

        .status-attached {
            background: #d1fae5;
            color: #065f46;
        }

        .status-not_applicable {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .attachment-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .control-select {
            padding: 6px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: white;
            font-size: 0.875rem;
            color: var(--gray-700);
            min-width: 120px;
        }

        .control-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-info {
            background: var(--info-color);
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background: var(--gray-50);
        }

        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid var(--gray-200);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 50;
        }

        .progress-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .progress-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: conic-gradient(var(--success-color) <?= $completionPercentage * 3.6 ?>deg, var(--gray-200) 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 50%;
        }

        .progress-text {
            position: relative;
            z-index: 1;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .progress-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .progress-info strong {
            color: var(--gray-900);
        }
        .alert-container {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            min-width: 320px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .attachment-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .attachment-controls {
                width: 100%;
                justify-content: space-between;
            }

            .bottom-bar {
                flex-direction: column;
                gap: 12px;
                padding: 12px 16px;
            }

            .work-order-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="alert-container" id="alertContainer"></div>

    <div class="container">
        <!-- Header معلومات أمر العمل -->
        <div class="header">
            <div class="header-top">
                <h1 class="header-title">
                    <i class="fas fa-paperclip"></i>
                    مدير المرفقات
                </h1>
                <div class="header-actions">
                    <button class="btn btn-outline btn-sm" onclick="refreshPage()">
                        <i class="fas fa-sync-alt"></i>
                        تحديث
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="closePage()">
                        <i class="fas fa-times"></i>
                        إغلاق
                    </button>
                </div>
            </div>

            <div class="work-order-info">
                <div class="info-item">
                    <div class="info-label">رقم أمر العمل</div>
                    <div class="info-value"><?= htmlspecialchars($workOrder['work_order_number']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">الفرع</div>
                    <div class="info-value"><?= htmlspecialchars($workOrder['branch_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">نوع العمل</div>
                    <div class="info-value"><?= htmlspecialchars($workOrder['work_order_type_description']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">تاريخ التكليف</div>
                    <div class="info-value"><?= date('Y-m-d', strtotime($workOrder['assignment_date'])) ?></div>
                </div>
            </div>
        </div>

        <!-- قائمة المرفقات -->
        <div class="attachments-section">
            <div class="section-header">
                <h2 class="section-title">النماذج المرفقة (<?= count($attachments) ?>)</h2>
            </div>

            <div class="attachments-list">
                <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-item">
                        <div class="attachment-content">
                            <!-- أيقونة ومعلومات النموذج -->
                            <div class="attachment-icon <?= $attachment['form_type'] ?>">
                                <i class="<?= $formTypes[$attachment['form_type']]['icon'] ?>"></i>
                            </div>

                            <div class="attachment-info">
                                <div class="attachment-title"><?= $formTypes[$attachment['form_type']]['name'] ?></div>
                                <div class="attachment-meta">
                                    <?php if ($attachment['id']): ?>
                                        ID: <?= $attachment['id'] ?>
                                        <?php if (!empty($attachment['file_path'])): ?>
                                            • <?= htmlspecialchars($attachment['original_filename']) ?>
                                            • <?= number_format($attachment['file_size'] / 1024, 1) ?> KB
                                            • <?= date('Y-m-d H:i', strtotime($attachment['uploaded_at'])) ?>
                                        <?php else: ?>
                                            • لا يوجد ملف مرفق
                                        <?php endif; ?>
                                    <?php else: ?>
                                        • لم يتم إنشاء السجل بعد
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($attachment['notes'])): ?>
                                    <div class="attachment-notes" style="margin-top: 5px; padding: 5px 10px; background: #f8f9fa; border-radius: 4px; font-size: 0.875rem; color: #495057;">
                                        <i class="fas fa-sticky-note" style="color: #ffc107;"></i>
                                        <strong>ملاحظات:</strong> <?= htmlspecialchars($attachment['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- حالة المرفق -->
                            <div class="attachment-status">
                                <div class="status-indicator status-<?= $attachment['status'] ?>">
                                    <?php
                                    $statusLabels = [
                                        'not_attached' => 'غير مرفق',
                                        'attached' => 'مرفق',
                                        'not_applicable' => 'لا ينطبق'
                                    ];
                                    echo $statusLabels[$attachment['status']];
                                    ?>
                                </div>
                            </div>

                            <!-- عناصر التحكم -->
                            <div class="attachment-controls">
                                <!-- تغيير الحالة -->
                                <select class="control-select" onchange="updateAttachmentStatus('<?= $attachment['form_type'] ?>', <?= $attachment['id'] ?? 'null' ?>, this.value)">
                                    <option value="not_attached" <?= $attachment['status'] == 'not_attached' ? 'selected' : '' ?>>غير مرفق</option>
                                    <option value="attached" <?= $attachment['status'] == 'attached' ? 'selected' : '' ?>>مرفق</option>
                                    <option value="not_applicable" <?= $attachment['status'] == 'not_applicable' ? 'selected' : '' ?>>لا ينطبق</option>
                                </select>

                                <!-- تأكيد شهادة الإنجاز -->
                                <?php if ($attachment['form_type'] == 'completion_certificate'): ?>
                                    <select class="control-select" onchange="updateCompletionConfirmation('<?= $attachment['form_type'] ?>', <?= $attachment['id'] ?? 'null' ?>, this.value)">
                                        <option value="empty" <?= $attachment['completion_certificate_confirmation'] == 'empty' ? 'selected' : '' ?>>فارغ</option>
                                        <option value="accepted" <?= $attachment['completion_certificate_confirmation'] == 'accepted' ? 'selected' : '' ?>>قبول</option>
                                        <option value="rejected" <?= $attachment['completion_certificate_confirmation'] == 'rejected' ? 'selected' : '' ?>>رفض</option>
                                        <option value="confirmed" <?= $attachment['completion_certificate_confirmation'] == 'confirmed' ? 'selected' : '' ?>>تأكيد</option>
                                    </select>
                                <?php endif; ?>

                                <!-- أزرار الإجراءات -->
                                <div class="action-buttons">
                                    <button class="btn btn-success btn-sm" onclick="uploadFileForAttachment('<?= $attachment['form_type'] ?>', <?= $attachment['id'] ?? 'null' ?>)" title="رفع ملف">
                                        <i class="fas fa-upload"></i>
                                    </button>

                                    <?php if (!empty($attachment['file_path']) && $attachment['id']): ?>
                                        <button class="btn btn-primary btn-sm" onclick="viewFile(<?= $attachment['id'] ?>)" title="استعراض">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="downloadFile(<?= $attachment['id'] ?>)" title="تحميل">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="removeFile(<?= $attachment['id'] ?>)" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($attachment['id']): ?>
                                        <button class="btn btn-warning btn-sm" onclick="editNotes(<?= $attachment['id'] ?>, '<?= htmlspecialchars($attachment['notes'] ?? '') ?>')" title="ملاحظات">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- قسم المستندات الأخرى -->
        <div class="attachments-section">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="section-title">
                    <i class="fas fa-file"></i>
                    مستندات أخرى (<?= count($otherDocuments) ?>)
                </h2>
                <button class="btn btn-success btn-sm" onclick="uploadOtherDocument()">
                    <i class="fas fa-plus"></i>
                    إضافة مستند
                </button>
            </div>

            <div class="attachments-list">
                <?php if (empty($otherDocuments)): ?>
                    <div class="attachment-item" style="text-align: center; color: var(--gray-500);">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p>لا توجد مستندات أخرى مرفقة</p>
                        <p style="font-size: 0.875rem;">انقر على "إضافة مستند" لرفع مستندات إضافية</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($otherDocuments as $doc): ?>
                        <div class="attachment-item">
                            <div class="attachment-content">
                                <!-- أيقونة المستند -->
                                <div class="attachment-icon other_document">
                                    <i class="fas fa-file"></i>
                                </div>

                                <div class="attachment-info">
                                    <div class="attachment-title"><?= htmlspecialchars($doc['original_filename'] ?? 'ملف بدون اسم') ?></div>
                                    <div class="attachment-meta">
                                        <?php if (!empty($doc['file_size'])): ?>
                                            <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                                        <?php else: ?>
                                            0.0 KB
                                        <?php endif; ?>
                                        <?php if (!empty($doc['uploaded_at'])): ?>
                                            • <?= date('Y-m-d H:i', strtotime($doc['uploaded_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($doc['notes'])): ?>
                                        <div class="attachment-notes" style="margin-top: 5px; padding: 5px 10px; background: #f8f9fa; border-radius: 4px; font-size: 0.875rem; color: #495057;">
                                            <i class="fas fa-sticky-note" style="color: #ffc107;"></i>
                                            <strong>ملاحظات:</strong> <?= htmlspecialchars($doc['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- أزرار الإجراءات -->
                                <div class="attachment-controls">
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-sm" onclick="viewFile(<?= $doc['id'] ?>)" title="استعراض">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="downloadFile(<?= $doc['id'] ?>)" title="تحميل">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="editNotes(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['notes'] ?? '') ?>')" title="ملاحظات">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="removeFile(<?= $doc['id'] ?>)" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- مساحة للشريط السفلي -->
        <div style="height: 80px;"></div>
    </div>

    <!-- شريط التحكم السفلي -->
    <div class="bottom-bar">
        <div class="progress-section">
            <div class="progress-circle">
                <div class="progress-text"><?= $completionPercentage ?>%</div>
            </div>
            <div class="progress-info">
                <strong>تقدم المرفقات</strong><br>
                <?= $attachedCount ?> من <?= $totalAttachments ?> مرفق
            </div>
        </div>

        <div class="header-actions">
            <button class="btn btn-primary" onclick="refreshPage()">
                <i class="fas fa-sync-alt"></i>
                تحديث
            </button>
            <button class="btn btn-outline" onclick="closePage()">
                <i class="fas fa-times"></i>
                إغلاق
            </button>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // عرض التنبيهات
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button type="button" style="float: left; background: none; border: none; font-size: 1.2rem; cursor: pointer;" onclick="this.parentElement.remove()">×</button>
            `;

            alertContainer.appendChild(alertDiv);

            // إزالة التنبيه بعد 5 ثوان
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // تحديث حالة المرفق
        function updateAttachmentStatus(formType, attachmentId, status) {
            const data = new FormData();
            data.append('form_type', formType);
            data.append('work_order_id', <?= $workOrderId ?>);
            data.append('status', status);

            if (attachmentId && attachmentId !== 'null') {
                data.append('attachment_id', attachmentId);
            }

            fetch('update-attachment-ajax.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'تم تحديث حالة المرفق بنجاح');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message || 'حدث خطأ أثناء التحديث');
                }
            })
            .catch(error => {
                showAlert('danger', 'حدث خطأ في الاتصال');
            });
        }

        // تحديث تأكيد شهادة الإنجاز
        function updateCompletionConfirmation(formType, attachmentId, confirmation) {
            const data = new FormData();
            data.append('form_type', formType);
            data.append('work_order_id', <?= $workOrderId ?>);
            data.append('completion_confirmation', confirmation);

            if (attachmentId && attachmentId !== 'null') {
                data.append('attachment_id', attachmentId);
            }

            fetch('update-attachment-ajax.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'تم تحديث تأكيد شهادة الإنجاز بنجاح');
                } else {
                    showAlert('danger', data.message || 'حدث خطأ أثناء التحديث');
                }
            })
            .catch(error => {
                showAlert('danger', 'حدث خطأ في الاتصال');
            });
        }

        // رفع ملف للمرفق
        function uploadFileForAttachment(formType, attachmentId) {
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif';

            fileInput.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // التحقق من حجم الملف (10MB)
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('danger', 'حجم الملف كبير جداً. الحد الأقصى 10 ميجابايت');
                    return;
                }

                const formData = new FormData();
                formData.append('form_type', formType);
                formData.append('work_order_id', <?= $workOrderId ?>);
                formData.append('file', file);

                if (attachmentId && attachmentId !== 'null') {
                    formData.append('attachment_id', attachmentId);
                }

                showAlert('info', 'جاري رفع الملف...');

                fetch('upload-file-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'تم رفع الملف بنجاح');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', data.message || 'حدث خطأ أثناء رفع الملف');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'حدث خطأ في الاتصال');
                });
            };

            fileInput.click();
        }

        // استعراض ملف
        function viewFile(attachmentId) {
            window.open('view-attachment.php?id=' + attachmentId, '_blank');
        }

        // تحميل ملف
        function downloadFile(attachmentId) {
            window.location.href = 'download-attachment.php?id=' + attachmentId;
        }

        // رفع مستند آخر
        function uploadOtherDocument() {
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx';

            fileInput.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // التحقق من حجم الملف (10MB)
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('danger', 'حجم الملف كبير جداً. الحد الأقصى 10MB');
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);
                formData.append('work_order_id', <?= $workOrderId ?>);
                formData.append('form_type', 'other_document');

                showAlert('info', 'جاري رفع الملف...');

                fetch('upload-file-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'تم رفع الملف بنجاح');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', data.message || 'حدث خطأ أثناء رفع الملف');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'حدث خطأ في الاتصال');
                });
            };

            fileInput.click();
        }

        // حذف ملف
        function removeFile(attachmentId) {
            if (!confirm('هل أنت متأكد من حذف هذا الملف؟')) {
                return;
            }

            fetch('remove-file-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attachment_id: attachmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'تم حذف الملف بنجاح');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message || 'حدث خطأ أثناء حذف الملف');
                }
            })
            .catch(error => {
                showAlert('danger', 'حدث خطأ في الاتصال');
            });
        }

        // تعديل الملاحظات
        function editNotes(attachmentId, currentNotes) {
            // التأكد من أن currentNotes ليست undefined أو null
            currentNotes = currentNotes || '';
            const notes = prompt('أدخل الملاحظات:', currentNotes);
            if (notes === null) return;

            fetch('update-attachment-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attachment_id: attachmentId,
                    notes: notes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'تم تحديث الملاحظات بنجاح');
                    // تحديث الصفحة لعرض الملاحظات الجديدة
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message || 'حدث خطأ أثناء التحديث');
                }
            })
            .catch(error => {
                showAlert('danger', 'حدث خطأ في الاتصال');
            });
        }

        // تحديث الصفحة
        function refreshPage() {
            location.reload();
        }

        // إغلاق الصفحة
        function closePage() {
            if (window.opener && !window.opener.closed) {
                window.close();
            } else {
                if (confirm('هل تريد العودة إلى صفحة أوامر العمل؟')) {
                    window.location.href = 'index.php';
                }
            }
        }

        // تأثيرات بصرية عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            // تأثير ظهور البطاقات
            const cards = document.querySelectorAll('.attachment-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>

</body>
</html>
