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
            wot.description as work_order_type_description,
            c.contract_number,
            c.start_date as contract_start_date,
            c.end_date as contract_end_date
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN contracts c ON wo.contract_id = c.id
        WHERE wo.id = ?
    ");
    
    $stmt->execute([$workOrderId]);
    $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$workOrder) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // جلب بنود أمر العمل (سابقاً بنود الإنتاجية)
    $workItemsStmt = $db->prepare("
        SELECT 
            pwi.*,
            cwi.item_number,
            cwi.description as contract_description,
            cwi.unit as contract_unit
        FROM productivity_work_items pwi
        LEFT JOIN contract_work_items cwi ON pwi.contract_work_item_id = cwi.id
        WHERE pwi.work_order_id = ?
        ORDER BY pwi.id ASC
    ");
    $workItemsStmt->execute([$workOrderId]);
    $workItems = $workItemsStmt->fetchAll(PDO::FETCH_ASSOC);

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

    // جلب تفاصيل المستخلصات المرتبطة بأمر العمل
    $extractDetails = [];

    // 1. المستخلصات الجزئية
    $partialStmt = $db->prepare("
        SELECT pe.*, pew.completion_date, pew.extract_value, pew.notes as wo_notes,
               b.name as branch_name, u.full_name as created_by_name
        FROM partial_extract_work_orders pew
        JOIN partial_extracts pe ON pew.partial_extract_id = pe.id
        LEFT JOIN branches b ON pe.branch_id = b.id
        LEFT JOIN users u ON pe.created_by = u.id
        WHERE pew.work_order_id = ?
        ORDER BY pe.extract_date DESC
    ");
    $partialStmt->execute([$workOrderId]);
    $partialExtracts = $partialStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($partialExtracts as $pe) {
        $pe['extract_type_label'] = 'جزئي';
        $pe['extract_type_color'] = 'info';
        $pe['extract_type_icon'] = 'fas fa-layer-group';
        $extractDetails[] = $pe;
    }

    // 2. المستخلصات النهائية العادية
    $finalRegStmt = $db->prepare("
        SELECT fre.*, frew.completion_date, frew.extract_value, frew.penalty_amount as wo_penalty, frew.notes as wo_notes,
               b.name as branch_name, u.full_name as created_by_name
        FROM final_regular_extract_work_orders frew
        JOIN final_regular_extracts fre ON frew.final_regular_extract_id = fre.id
        LEFT JOIN branches b ON fre.branch_id = b.id
        LEFT JOIN users u ON fre.created_by = u.id
        WHERE frew.work_order_id = ?
        ORDER BY fre.extract_date DESC
    ");
    $finalRegStmt->execute([$workOrderId]);
    $finalRegExtracts = $finalRegStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalRegExtracts as $fre) {
        $fre['extract_type_label'] = 'نهائي عادي';
        $fre['extract_type_color'] = 'success';
        $fre['extract_type_icon'] = 'fas fa-check-double';
        $extractDetails[] = $fre;
    }

    // 3. المستخلصات النهائية للجزئية
    $finalPartStmt = $db->prepare("
        SELECT ffpe.*, ffpew.completion_date, ffpew.extract_value, ffpew.penalty_amount as wo_penalty, ffpew.notes as wo_notes,
               b.name as branch_name, u.full_name as created_by_name
        FROM final_for_partial_extract_work_orders ffpew
        JOIN final_for_partial_extracts ffpe ON ffpew.final_for_partial_extract_id = ffpe.id
        LEFT JOIN branches b ON ffpe.branch_id = b.id
        LEFT JOIN users u ON ffpe.created_by = u.id
        WHERE ffpew.work_order_id = ?
        ORDER BY ffpe.extract_date DESC
    ");
    $finalPartStmt->execute([$workOrderId]);
    $finalPartExtracts = $finalPartStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalPartExtracts as $ffpe) {
        $ffpe['extract_type_label'] = 'نهائي للجزئي';
        $ffpe['extract_type_color'] = 'warning';
        $ffpe['extract_type_icon'] = 'fas fa-flag-checkered';
        $extractDetails[] = $ffpe;
    }

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

    // تحويل حالة صرف المواد إلى نص
    $disbursementStatusText = [
        'none' => 'لم يتم الصرف',
        'pending_disbursement' => 'في انتظار الصرف',
        'disbursement' => 'تم صرف المواد',
        'completed' => 'مكتمل',
        'return' => 'متبقي إرجاع',
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
                            <td class="fw-bold">رقم العقد:</td>
                            <td>' . (!empty($workOrder['contract_number']) ?
                                '<span class="badge bg-dark fs-6 px-2 py-1"><i class="fas fa-file-contract me-1"></i>' . htmlspecialchars($workOrder['contract_number']) . '</span>' :
                                '<span class="text-muted"><i class="fas fa-unlink me-1"></i>غير مرتبط بعقد</span>') . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">نوع أمر العمل:</td>
                            <td>' . htmlspecialchars($workOrder['type_code'] ?? '') . (!empty($workOrder['work_order_type_description']) ? ' - ' . htmlspecialchars($workOrder['work_order_type_description']) : '') . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">القسم:</td>
                            <td>' . ($departmentText[$workOrder['department']] ?? $workOrder['department']) . '</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">الفرع:</td>
                            <td>' . htmlspecialchars($workOrder['branch_name'] ?? '') . (!empty($workOrder['branch_code']) ? ' (' . htmlspecialchars($workOrder['branch_code']) . ')' : '') . '</td>
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
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-money-bill text-success me-2"></i>
                        المعلومات المالية
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="fw-bold">القيمة المقدرة:</td>
                            <td><span class="text-primary fw-bold">' . number_format($workOrder['estimated_value'], 2) . ' ريال</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">القيمة الفعلية:</td>
                            <td><span class="text-success fw-bold">' . number_format($workOrder['actual_value'], 2) . ' ريال</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-warehouse text-info me-2"></i>
                        حالة صرف المواد
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <span class="badge bg-' . ($workOrder['disbursement_status'] == 'completed' ? 'success' : ($workOrder['disbursement_status'] == 'none' ? 'secondary' : ($workOrder['disbursement_status'] == 'return' ? 'warning' : 'info'))) . ' fs-6 px-3 py-2">
                            <i class="fas fa-' . ($workOrder['disbursement_status'] == 'completed' ? 'check-circle' : ($workOrder['disbursement_status'] == 'none' ? 'minus-circle' : ($workOrder['disbursement_status'] == 'return' ? 'undo' : 'boxes'))) . ' me-1"></i>
                            ' . ($disbursementStatusText[$workOrder['disbursement_status']] ?? $workOrder['disbursement_status']) . '
                        </span>
                        <br><small class="text-muted mt-1 d-block">صرف المواد من مستودع شركة الكهرباء</small>
                    </div>
                </div>
            </div>
        </div>';

    // إضافة قسم المستخلصات
    if (!empty($extractDetails)) {
        // حساب الملخص المالي
        $totalExtractValue = 0;
        $totalPenalty = 0;
        foreach ($extractDetails as $ext) {
            $totalExtractValue += (float)($ext['extract_value'] ?? 0);
            $totalPenalty += (float)($ext['wo_penalty'] ?? 0);
        }

        $html .= '<div class="col-12">
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            تفاصيل المستخلصات
                            <span class="badge bg-light text-dark ms-2">' . count($extractDetails) . ' مستخلص</span>
                        </h6>
                        <div>
                            <span class="badge bg-light text-dark me-2">
                                <i class="fas fa-coins me-1"></i>
                                إجمالي القيمة: ' . number_format($totalExtractValue, 2) . ' ريال
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">';

        // جلب مراحل الاعتماد من قاعدة البيانات
        $stagesStmt = $db->query("SELECT stage_key, stage_name, stage_color, stage_order, is_final FROM approval_stages WHERE is_active = 1 ORDER BY stage_order ASC");
        $dbStages = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);

        // بناء مصفوفة المراحل وترتيبها
        $approvalStages = [];
        $stagesOrder = [];
        $stageIcons = [
            'draft' => 'fas fa-pencil-alt',
            'technical_support' => 'fas fa-headset',
            'construction' => 'fas fa-hard-hat',
            'department_manager' => 'fas fa-user-tie',
            'administration_manager' => 'fas fa-user-shield',
            'taif_finance' => 'fas fa-calculator',
            'finance' => 'fas fa-calculator',
            'disbursed' => 'fas fa-check-circle',
            'did' => 'fas fa-exclamation-triangle'
        ];
        $stageColors = [
            'primary' => '#0d6efd',
            'secondary' => '#6c757d',
            'success' => '#198754',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8'
        ];

        foreach ($dbStages as $stage) {
            $key = $stage['stage_key'];
            $color = $stageColors[$stage['stage_color']] ?? '#6c757d';
            $icon = $stageIcons[$key] ?? 'fas fa-circle';
            $approvalStages[$key] = [
                'label' => $stage['stage_name'],
                'icon' => $icon,
                'color' => $color
            ];
            $stagesOrder[] = $key;
        }

        foreach ($extractDetails as $idx => $extract) {
            $currentStage = $extract['approval_stage'] ?? 'draft';
            $extractStatus = $extract['status'] ?? $currentStage;

            $currentStageIndex = array_search($currentStage, $stagesOrder);
            if ($currentStageIndex === false) $currentStageIndex = 0;

            $html .= '<div class="p-3' . ($idx > 0 ? ' border-top' : '') . '">
                <div class="d-flex align-items-start mb-3">
                    <div class="me-3">
                        <span class="badge bg-' . $extract['extract_type_color'] . ' fs-6 px-3 py-2">
                            <i class="' . $extract['extract_type_icon'] . ' me-1"></i>
                            ' . $extract['extract_type_label'] . '
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <i class="fas fa-hashtag text-muted me-1"></i>
                            ' . htmlspecialchars($extract['extract_number']) . '
                        </h6>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>تاريخ المستخلص: ' . htmlspecialchars($extract['extract_date'] ?? '-') . '
                        </small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-primary fs-5">' . number_format((float)($extract['extract_value'] ?? 0), 2) . '</div>
                        <small class="text-muted">ريال</small>
                    </div>
                </div>';

            // شريط مراحل الاعتماد المرئي (RTL - من اليمين لليسار)
            $stagesOrderReversed = array_reverse($stagesOrder);
            $progressPercent = $currentStageIndex > 0 ? round(($currentStageIndex / (count($stagesOrder) - 1)) * 100) : 0;

            $html .= '<div class="mb-3" dir="ltr">
                <div class="d-flex justify-content-between align-items-center position-relative" style="padding: 0 10px; direction: ltr;">
                    <div style="position: absolute; top: 50%; left: 20px; right: 20px; height: 3px; background: #e9ecef; transform: translateY(-50%); z-index: 0;"></div>
                    <div style="position: absolute; top: 50%; right: 20px; height: 3px; background: linear-gradient(270deg, #176cb4, #4fa5e6); transform: translateY(-50%); z-index: 1; width: ' . $progressPercent . '%; transition: width 0.5s;"></div>';

            foreach ($stagesOrderReversed as $sIdx => $stage) {
                $stageInfo = $approvalStages[$stage] ?? ['label' => $stage, 'icon' => 'fas fa-circle', 'color' => '#6c757d'];
                $originalIdx = array_search($stage, $stagesOrder);
                $isCompleted = $originalIdx < $currentStageIndex;
                $isCurrent = $originalIdx == $currentStageIndex;
                $isPending = $originalIdx > $currentStageIndex;

                $bgColor = $isCompleted ? $stageInfo['color'] : ($isCurrent ? $stageInfo['color'] : '#dee2e6');
                $textColor = ($isCompleted || $isCurrent) ? 'white' : '#adb5bd';
                $size = $isCurrent ? '36px' : '28px';
                $shadow = $isCurrent ? 'box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.3);' : '';

                $html .= '<div class="text-center" style="z-index: 2; flex: 1;">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle" 
                         style="width: ' . $size . '; height: ' . $size . '; background: ' . $bgColor . '; color: ' . $textColor . '; ' . $shadow . ' transition: all 0.3s;"
                         title="' . $stageInfo['label'] . '">
                        <i class="' . ($isCompleted ? 'fas fa-check' : $stageInfo['icon']) . '" style="font-size: ' . ($isCurrent ? '14px' : '11px') . ';"></i>
                    </div>
                    <div class="mt-1" style="font-size: 10px; color: ' . ($isCurrent ? $stageInfo['color'] : '#adb5bd') . '; font-weight: ' . ($isCurrent ? 'bold' : 'normal') . ';">
                        ' . $stageInfo['label'] . '
                    </div>
                </div>';
            }

            $html .= '</div>
            </div>';

            // تفاصيل إضافية
            $html .= '<div class="row g-2">';

            // رقم أمر الشراء
            if (!empty($extract['po_number'])) {
                $html .= '<div class="col-auto">
                    <span class="badge bg-light text-dark border">
                        <i class="fas fa-shopping-cart text-primary me-1"></i>
                        PO: ' . htmlspecialchars($extract['po_number']) . '
                    </span>
                </div>';
            }

            // رقم الفاتورة
            if (!empty($extract['invoice_number'])) {
                $html .= '<div class="col-auto">
                    <span class="badge bg-light text-dark border">
                        <i class="fas fa-file-invoice text-success me-1"></i>
                        فاتورة: ' . htmlspecialchars($extract['invoice_number']) . '
                    </span>
                </div>';
            }

            // رقم ورقة الإدخال
            if (!empty($extract['entry_sheet_number'])) {
                $html .= '<div class="col-auto">
                    <span class="badge bg-light text-dark border">
                        <i class="fas fa-receipt text-info me-1"></i>
                        Entry Sheet: ' . htmlspecialchars($extract['entry_sheet_number']) . '
                    </span>
                </div>';
            }

            // تاريخ الإنجاز
            if (!empty($extract['completion_date'])) {
                $html .= '<div class="col-auto">
                    <span class="badge bg-light text-dark border">
                        <i class="fas fa-calendar-check text-success me-1"></i>
                        تاريخ الإنجاز: ' . htmlspecialchars($extract['completion_date']) . '
                    </span>
                </div>';
            }

            // الغرامات
            $penalty = (float)($extract['wo_penalty'] ?? 0);
            if ($penalty > 0) {
                $html .= '<div class="col-auto">
                    <span class="badge bg-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        غرامة: ' . number_format($penalty, 2) . ' ريال
                    </span>
                </div>';
            }

            $html .= '</div>'; // end row g-2

            $html .= '</div>'; // end p-3
        }

        $html .= '</div>
            </div>
        </div>';
    } else {
        // لا توجد مستخلصات
        $html .= '<div class="col-12">
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%); color: white;">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        تفاصيل المستخلصات
                    </h6>
                </div>
                <div class="card-body text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                    <p class="text-muted mb-0">لا توجد مستخلصات مرتبطة بأمر العمل هذا</p>
                </div>
            </div>
        </div>';
    }

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

    // إضافة قسم بنود أمر العمل
    $html .= '<div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                <h6 class="card-title mb-0 text-primary fw-bold">
                    <i class="fas fa-tasks me-2"></i>
                    بنود أمر العمل (مؤشرات الإنجاز)
                </h6>
                <a href="' . path('productivity/work-items/index.php?work_order_id=' . $workOrderId) . '" class="btn btn-sm btn-primary">
                    <i class="fas fa-external-link-alt me-1"></i>
                    إدارة البنود والإنجاز اليومي
                </a>
            </div>
            <div class="card-body p-0">';
            
    if (!empty($workItems)) {
        $html .= '<div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم البند</th>
                        <th>الوصف</th>
                        <th>الوحدة</th>
                        <th>الكمية المستهدفة</th>
                        <th>السعر</th>
                        <th>الإجمالي</th>
                        <th>الكمية المنجزة</th>
                        <th>نسبة الإنجاز</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($workItems as $item) {
            $progress = $item['target_quantity'] > 0 ? min(100, round(($item['actual_quantity_completed'] / $item['target_quantity']) * 100)) : 0;
            $progressClass = $progress >= 100 ? 'success' : ($progress > 0 ? 'primary' : 'secondary');
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($item['item_number'] ?? 'غير محدد') . '</strong></td>
                <td>' . htmlspecialchars($item['contract_description'] ?? $item['work_item_description']) . '</td>
                <td>' . htmlspecialchars($item['contract_unit'] ?? $item['unit']) . '</td>
                <td>' . number_format($item['target_quantity'], 2) . '</td>
                <td>' . number_format($item['unit_price'], 2) . '</td>
                <td>' . number_format($item['total_value'], 2) . '</td>
                <td><span class="text-success fw-bold">' . number_format($item['actual_quantity_completed'], 2) . '</span></td>
                <td style="width: 150px;">
                    <div class="d-flex align-items-center">
                        <span class="me-2 text-' . $progressClass . '">' . $progress . '%</span>
                        <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar bg-' . $progressClass . '" role="progressbar" style="width: ' . $progress . '%" aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </td>
            </tr>';
        }
        $html .= '</tbody></table></div>';
    } else {
        $html .= '<div class="text-center py-4 text-muted">
            <i class="fas fa-clipboard-list fa-3x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">لا توجد بنود مرتبطة بأمر العمل هذا.</p>
        </div>';
    }

    $html .= '</div>
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
