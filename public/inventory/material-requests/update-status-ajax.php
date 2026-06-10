<?php
/**
 * تحديث حالة طلب الصرف عبر AJAX
 * Update Material Request Status via AJAX
 */

// تنظيف أي output سابق
if (ob_get_level()) {
    ob_clean();
}

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تعيين header للـ JSON
header('Content-Type: application/json; charset=utf-8');

// إخفاء الأخطاء من العرض
ini_set('display_errors', 0);
error_reporting(E_ALL);

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../models/MaterialRequest.php';

    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error loading files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في تحميل النظام']);
    exit;
}

// التحقق من الصلاحيات الأساسية - استخدام النظام الجديد
if (!hasPermission('inventory_requests_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة طلبات الصرف']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$requestId = (int) ($input['request_id'] ?? 0);
$action = $input['action'] ?? '';
$stepId = (int) ($input['step_id'] ?? 0);
$reason = $input['reason'] ?? '';

if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الطلب غير صحيح']);
    exit;
}

if (!in_array($action, ['approve', 'reject', 'submit', 'request_revision'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'الإجراء غير صحيح']);
    exit;
}

try {
    $materialRequestModel = new MaterialRequest();

    // التحقق من وجود الطلب
    $request = $materialRequestModel->findById($requestId);
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    if ($action === 'approve') {
        // التحقق من وجود step_id
        if ($stepId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'معرف خطوة الاعتماد مطلوب']);
            exit;
        }

        // التحقق من الصلاحية
        $canApprove = canApproveRequestByStep($stepId, $request['branch_id'], $request['work_order_id']);

        if (!$canApprove) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'ليس لديك صلاحية الاعتماد في هذه المرحلة'
            ]);
            exit;
        }

        // موافقة الطلب (بواسطة step_id) مع تمرير الملاحظات
        $notes = $reason; // الملاحظات المدخلة من المعتمد
        $result = $materialRequestModel->approveRequest($requestId, $stepId, $userId, $notes);
    } elseif ($action === 'request_revision') {
        // طلب تعديل — إعادة الطلب لمقدمه
        $currentStep = $materialRequestModel->getCurrentStepForRequest($request);
        $currentStepId = $currentStep ? $currentStep['id'] : null;

        if ($currentStepId) {
            $canRevise = canApproveRequestByStep($currentStepId, $request['branch_id'], $request['work_order_id']);
        } else {
            $canRevise = false;
        }

        if (!$canRevise) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية طلب التعديل في هذه المرحلة']);
            exit;
        }

        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ملاحظات التعديل المطلوب مطلوبة']);
            exit;
        }

        $result = $materialRequestModel->requestRevision($requestId, $userId, $reason, $currentStepId);
    } elseif ($action === 'submit') {
        // إرسال الطلب (تغيير الحالة من مسودة أو طلب تعديل إلى مرسل)

        if (!in_array($request['status'], ['draft', 'revision_requested'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'يمكن إرسال الطلبات في حالة مسودة أو طلب تعديل فقط']);
            exit;
        }

        if ($request['requested_by'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'يمكن فقط لمنشئ الطلب إرساله']);
            exit;
        }

        $result = $materialRequestModel->submitRequest($requestId);
    } else {
        // رفض الطلب - التحقق من الصلاحيات
        // الحصول على الخطوة الحالية للطلب
        $currentStep = $materialRequestModel->getCurrentStepForRequest($request);
        $currentStepId = $currentStep ? $currentStep['id'] : null;

        if ($currentStepId) {
            $canReject = canApproveRequestByStep($currentStepId, $request['branch_id'], $request['work_order_id']);
        } else {
            $canReject = false;
        }

        if (!$canReject) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية رفض الطلبات في هذه المرحلة']);
            exit;
        }

        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'سبب الرفض مطلوب']);
            exit;
        }

        $result = $materialRequestModel->rejectRequest($requestId, $userId, $reason, $currentStepId);
    }

    if ($result['success']) {
        $actionText = $action === 'approve' ? 'موافقة' : ($action === 'submit' ? 'إرسال' : ($action === 'request_revision' ? 'طلب تعديل' : 'رفض'));

        // تسجيل النشاط
        try {
            logActivity('update_material_request_status', "تم {$actionText} طلب الصرف: {$request['request_number']}");
        } catch (Exception $logEx) {
            error_log('[update-status-ajax] logActivity failed: ' . $logEx->getMessage());
        }

        // ===== إرسال الإشعارات عند الإرسال أو الموافقة النهائية فقط =====
        $isFinal = isset($result['is_final']) && $result['is_final'];
        if ($action === 'submit' || ($action === 'approve' && $isFinal)) {
            ignore_user_abort(true);
            set_time_limit(60);
            try {
                require_once __DIR__ . '/../../../includes/EmailService.php';
                require_once __DIR__ . '/../../../includes/WhatsAppService.php';
                $db = getDB();

                $reqStmt = $db->prepare(
                    "SELECT mr.*,
                            wo.work_order_number,
                            wot.description as work_order_type_description,
                            b.name as branch_name,
                            u1.full_name as requested_by_name,
                            u1.email as requested_by_email,
                            u1.phone as requested_by_phone
                     FROM material_requests mr
                     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
                     LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
                     LEFT JOIN branches b ON wo.branch_id = b.id
                     LEFT JOIN users u1 ON mr.requested_by = u1.id
                     WHERE mr.id = ?"
                );
                $reqStmt->execute([$requestId]);
                $fullRequest = $reqStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $db->prepare(
                    "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
                     FROM material_request_details mrd
                     JOIN materials m ON mrd.material_id = m.id
                     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                     WHERE mrd.request_id = ?
                     ORDER BY mc.description"
                );
                $stmt->execute([$requestId]);
                $emailDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $emailService = new EmailService();
                $whatsappService = new WhatsAppService();

                if ($action === 'submit') {
                    $settingsStmt = $db->query("SELECT * FROM notification_settings WHERE event_name = 'material_request_submit' AND is_active = 1");
                    $notifications = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
                    $defaultEmailSent = false;

                    foreach ($notifications as $notification) {
                        $recipient = $notification['recipient'];
                        if ($notification['notification_type'] === 'whatsapp_personal') {
                            $whatsappService->sendMaterialRequestNotification($fullRequest, $emailDetails, $recipient, false);
                        } elseif ($notification['notification_type'] === 'whatsapp_group') {
                            $whatsappService->sendMaterialRequestNotification($fullRequest, $emailDetails, $recipient, true);
                        } elseif ($notification['notification_type'] === 'email' && !$defaultEmailSent) {
                            $emailService->sendMaterialRequestNotification($fullRequest, $emailDetails);
                            $defaultEmailSent = true;
                        }
                    }

                    if (!$defaultEmailSent && count($notifications) === 0) {
                        $emailService->sendMaterialRequestNotification($fullRequest, $emailDetails);
                    }
                } elseif ($action === 'approve' && $isFinal) {
                    // إرسال إشعار الموافقة النهائية لمقدم الطلب
                    $requesterEmail = $fullRequest['requested_by_email'] ?? '';
                    if (!empty($requesterEmail)) {
                        $emailService->sendMaterialRequestApprovalNotification($fullRequest, $emailDetails, $requesterEmail, 'نهائي');
                    }

                    $requesterPhone = $fullRequest['requested_by_phone'] ?? '';
                    if (!empty($requesterPhone)) {
                        $whatsappService->sendMaterialRequestApprovalNotification($fullRequest, $emailDetails, $requesterPhone, 'الاعتماد النهائي');
                    }
                }
            } catch (Exception $emailEx) {
                error_log('[update-status-ajax] Notification exception: ' . $emailEx->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "تم {$actionText} الطلب بنجاح"
        ]);

    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'فشل في تحديث حالة الطلب'
        ]);
    }

} catch (Exception $e) {
    error_log("Error updating material request status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Fatal error updating material request status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ فادح في النظام: ' . $e->getMessage()
    ]);
}
?>