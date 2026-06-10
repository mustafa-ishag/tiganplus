<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();
    $workOrderId = (int) ($_GET['id'] ?? 0);

    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // جلب تفاصيل أمر العمل
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
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // جلب المرفقات
    $attachmentsStmt = $db->prepare("
        SELECT
            id,
            form_type,
            status,
            completion_certificate_confirmation,
            certificate_attached_date,
            certificate_confirmed_date,
            file_path,
            original_filename,
            file_size,
            uploaded_at
        FROM work_order_attachments
        WHERE work_order_id = ?
        ORDER BY form_type
    ");
    $attachmentsStmt->execute([$workOrderId]);
    $attachments = $attachmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // تعريف أنواع النماذج
    $formTypes = [
        'excavation_form' => ['name' => 'نموذج الكشط', 'icon' => 'fas fa-hard-hat', 'color' => 'danger'],
        'precise_drilling_form' => ['name' => 'نموذج الحفر الدقيق', 'icon' => 'fas fa-crosshairs', 'color' => 'info'],
        'demolition_form' => ['name' => 'نموذج التخريد (الاسكراب)', 'icon' => 'fas fa-recycle', 'color' => 'secondary'],
        'f1_form' => ['name' => 'نموذج F1', 'icon' => 'fas fa-file-alt', 'color' => 'primary'],
        'assets_receipt_form' => ['name' => 'استلام الأصول (إجراء 211)', 'icon' => 'fas fa-box-open', 'color' => 'warning'],
        'completion_certificate' => ['name' => 'شهادة الإنجاز', 'icon' => 'fas fa-certificate', 'color' => 'success']
    ];

    // تعريف حالات المرفقات
    $attachmentStatusText = [
        'attached' => ['text' => 'مرفق', 'class' => 'success'],
        'not_attached' => ['text' => 'غير مرفق', 'class' => 'warning'],
        'not_applicable' => ['text' => 'لا ينطبق', 'class' => 'secondary']
    ];

    // إنشاء مصفوفة للمرفقات الموجودة
    $existingAttachmentsMap = [];
    foreach ($attachments as $attachment) {
        $existingAttachmentsMap[$attachment['form_type']] = $attachment;
    }

    // دمج المرفقات الموجودة مع الافتراضية
    $allAttachments = [];
    $allFormTypes = [
        'excavation_form' => 'not_applicable',
        'precise_drilling_form' => 'not_applicable',
        'demolition_form' => 'not_applicable',
        'f1_form' => 'not_applicable',
        'assets_receipt_form' => 'not_applicable',
        'completion_certificate' => 'not_attached'
    ];

    foreach ($allFormTypes as $formType => $defaultStatus) {
        if (isset($existingAttachmentsMap[$formType])) {
            $allAttachments[] = $existingAttachmentsMap[$formType];
        } else {
            $allAttachments[] = [
                'id' => null,
                'form_type' => $formType,
                'status' => $defaultStatus,
                'completion_certificate_confirmation' => 'empty',
                'certificate_attached_date' => null,
                'certificate_confirmed_date' => null,
                'file_path' => null,
                'original_filename' => null,
                'file_size' => null,
                'uploaded_at' => null
            ];
        }
    }

    // تحويل حالة الصرف إلى نص
    $disbursementStatusText = [
        'none' => 'لا يوجد',
        'pending_disbursement' => 'في انتظار الصرف',
        'disbursement' => 'تم الصرف',
        'completed' => 'مكتمل',
        'return' => 'مرتجع',
        'partial_disbursement' => 'صرف جزئي',
        'cancelled_disbursement' => 'صرف ملغي'
    ];

    // تحويل حالة أمر العمل إلى نص
    $workOrderStatusText = [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي'
    ];

    // تحويل القسم إلى نص
    $departmentText = [
        'connections' => 'التوصيلات',
        'projects' => 'المشاريع'
    ];

    // إنشاء HTML لعرض التفاصيل
    $html = '<div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        المعلومات الأساسية
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="fw-bold">رقم أمر العمل:</td>
                            <td>' . htmlspecialchars($workOrder['work_order_number']) . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">نوع أمر العمل:</td>
                            <td>' . htmlspecialchars($workOrder['type_code']) . ' - ' . htmlspecialchars($workOrder['work_order_type_description']) . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">القسم:</td>
                            <td>' . ($departmentText[$workOrder['department']] ?? $workOrder['department']) . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">الفرع:</td>
                            <td>' . htmlspecialchars($workOrder['branch_name']) . ' (' . htmlspecialchars($workOrder['branch_code']) . ')</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">الموقع:</td>
                            <td>
                                ' . (!empty($workOrder['location']) ?
                                    '<i class="fas fa-map-marker-alt text-primary me-2"></i>' . htmlspecialchars($workOrder['location']) :
                                    '<span class="text-muted">غير محدد</span>') . '
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">الحالة:</td>
                            <td>
                                <span class="badge bg-' . ($workOrder['status'] == 'active' ? 'success' : ($workOrder['status'] == 'completed' ? 'primary' : 'secondary')) . '">
                                    ' . ($workOrderStatusText[$workOrder['status']] ?? $workOrder['status']) . '
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calendar text-info me-2"></i>
                        التواريخ
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="fw-bold">تاريخ التكليف:</td>
                            <td>' . ($workOrder['assignment_date'] ? date('Y-m-d', strtotime($workOrder['assignment_date'])) : 'غير محدد') . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">تاريخ الاستلام:</td>
                            <td>' . ($workOrder['receipt_date'] ? date('Y-m-d', strtotime($workOrder['receipt_date'])) : 'غير محدد') . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">تاريخ الإنشاء:</td>
                            <td>' . date('Y-m-d H:i', strtotime($workOrder['created_at'])) . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">آخر تحديث:</td>
                            <td>' . ($workOrder['updated_at'] ? date('Y-m-d H:i', strtotime($workOrder['updated_at'])) : 'لا يوجد') . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-money-bill text-success me-2"></i>
                        المعلومات المالية
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h5 class="text-primary">' . number_format($workOrder['estimated_value'], 2) . ' ريال</h5>
                                <small class="text-muted">القيمة المقدرة</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h5 class="text-success">' . number_format($workOrder['actual_value'], 2) . ' ريال</h5>
                                <small class="text-muted">القيمة الفعلية</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <span class="badge bg-' . ($workOrder['disbursement_status'] == 'completed' ? 'success' : ($workOrder['disbursement_status'] == 'none' ? 'secondary' : 'warning')) . ' fs-6">
                                    ' . ($disbursementStatusText[$workOrder['disbursement_status']] ?? $workOrder['disbursement_status']) . '
                                </span>
                                <br><small class="text-muted">حالة الصرف</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

    // إضافة قسم المرفقات
    $html .= '<div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="fas fa-paperclip text-info me-2"></i>
                    المرفقات والنماذج
                </h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openAttachmentsManager(' . $workOrderId . ')">
                    <i class="fas fa-cog me-1"></i>
                    إدارة المرفقات
                </button>
            </div>
            <div class="card-body">
                <div class="row">';

    foreach ($allAttachments as $attachment) {
        $formType = $attachment['form_type'];
        $formInfo = $formTypes[$formType];
        $status = $attachmentStatusText[$attachment['status']] ?? ['text' => $attachment['status'], 'class' => 'secondary'];

        $html .= '<div class="col-md-6 col-lg-4 mb-3">
            <div class="card border-' . $status['class'] . ' h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="' . $formInfo['icon'] . ' text-' . $formInfo['color'] . ' me-2"></i>
                        <h6 class="card-title mb-0 small">' . htmlspecialchars($formInfo['name']) . '</h6>
                    </div>

                    <div class="mb-2">
                        <span class="badge bg-' . $status['class'] . ' small">' . htmlspecialchars($status['text']) . '</span>';

        if ($attachment['id']) {
            $html .= ' <small class="text-muted">ID: ' . (int)$attachment['id'] . '</small>';
        }

        $html .= '</div>';

        if (!empty($attachment['file_path']) && !empty($attachment['original_filename'])) {
            $fileSize = $attachment['file_size'] ? number_format($attachment['file_size'] / 1024, 1) . ' KB' : '';
            $uploadDate = $attachment['uploaded_at'] ? date('Y-m-d', strtotime($attachment['uploaded_at'])) : '';

            $html .= '<div class="small text-muted">
                <div><i class="fas fa-file me-1"></i>' . htmlspecialchars($attachment['original_filename']) . '</div>';

            if ($fileSize) {
                $html .= '<div><i class="fas fa-weight me-1"></i>' . $fileSize . '</div>';
            }

            if ($uploadDate) {
                $html .= '<div><i class="fas fa-calendar me-1"></i>' . $uploadDate . '</div>';
            }

            $html .= '</div>
                <div class="mt-2">
                    <a href="download.php?id=' . $attachment['id'] . '" class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="fas fa-download me-1"></i>تحميل
                    </a>
                </div>';
        } else {
            $html .= '<div class="small text-muted">
                <i class="fas fa-info-circle me-1"></i>لا يوجد ملف مرفق
            </div>';
        }

        // إضافة معلومات شهادة الإنجاز
        if ($formType === 'completion_certificate' && !empty($attachment['completion_certificate_confirmation']) && $attachment['completion_certificate_confirmation'] !== 'empty') {
            $confirmationText = [
                'accepted' => ['text' => 'مقبول', 'class' => 'success'],
                'rejected' => ['text' => 'مرفوض', 'class' => 'danger'],
                'confirmed' => ['text' => 'مؤكد', 'class' => 'primary']
            ];

            // التحقق من وجود القيمة في المصفوفة
            if (isset($confirmationText[$attachment['completion_certificate_confirmation']])) {
                $confirmation = $confirmationText[$attachment['completion_certificate_confirmation']];
                $html .= '<div class="mt-2">
                    <small class="text-muted">التأكيد:</small>
                    <span class="badge bg-' . $confirmation['class'] . ' small">' . $confirmation['text'] . '</span>
                </div>';
            }

            // عرض تاريخ ارفاق الشهادة
            if (!empty($attachment['certificate_attached_date'])) {
                $html .= '<div class="mt-1">
                    <small class="text-muted"><i class="fas fa-calendar-check me-1"></i>تاريخ الإرفاق: ' . htmlspecialchars($attachment['certificate_attached_date']) . '</small>
                </div>';
            }

            // عرض تاريخ تأكيد الشهادة
            if (!empty($attachment['certificate_confirmed_date'])) {
                $html .= '<div class="mt-1">
                    <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>تاريخ التأكيد: ' . htmlspecialchars($attachment['certificate_confirmed_date']) . '</small>
                </div>';
            }
        }

        $html .= '</div>
            </div>
        </div>';
    }

    $html .= '</div>
            </div>
        </div>
    </div>';

    if ($workOrder['notes']) {
        $html .= '<div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-sticky-note text-warning me-2"></i>
                        الملاحظات
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">' . nl2br(htmlspecialchars($workOrder['notes'])) . '</p>
                </div>
            </div>
        </div>';
    }

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('خطأ في عرض أمر العمل: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى'
    ], JSON_UNESCAPED_UNICODE);
}
?>
