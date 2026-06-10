<?php
/**
 * معالج AJAX لإدارة تعيين المعتمدين وخطوات الاعتماد
 * AJAX Handler for Approval Assignments & Steps Management
 */

if (ob_get_level()) { ob_clean(); }
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../models/ApprovalAssignment.php';
} catch (Exception $e) {
    error_log("Error loading files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في تحميل النظام']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

if (!hasPermission('manage_system')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة تعيين المعتمدين']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// معالجة طلبات GET (جلب البيانات)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'معرف التعيين غير صحيح']);
            exit();
        }

        try {
            $approvalModel = new ApprovalAssignment();
            $assignment = $approvalModel->getById($id);
            if (!$assignment) {
                echo json_encode(['success' => false, 'message' => 'التعيين غير موجود']);
                exit();
            }
            echo json_encode(['success' => true, 'assignment' => $assignment]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ في جلب البيانات: ' . $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'get_steps') {
        try {
            $approvalModel = new ApprovalAssignment();
            $steps = $approvalModel->getAllSteps();
            echo json_encode(['success' => true, 'steps' => $steps]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    echo json_encode(['success' => false, 'message' => 'إجراء غير صحيح']);
    exit();
}

try {
    $approvalModel = new ApprovalAssignment();
    $action = $_POST['action'] ?? 'add';
    
    switch ($action) {
        // ===================== إدارة التعيينات =====================
        case 'add':
            $stepId = (int)($_POST['step_id'] ?? 0);
            $approverUserId = (int)($_POST['approver_user_id'] ?? 0);
            $scopeType = sanitizeInput($_POST['scope_type'] ?? 'global');
            $scopeId = !empty($_POST['scope_id']) ? (int)$_POST['scope_id'] : null;
            $priority = (int)($_POST['priority'] ?? 1);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if (!$stepId || !$approverUserId) {
                throw new Exception('يرجى ملء جميع البيانات المطلوبة');
            }
            
            if (!in_array($scopeType, ['global', 'branch', 'work_order'])) {
                throw new Exception('نوع النطاق غير صحيح');
            }
            
            if (in_array($scopeType, ['branch', 'work_order']) && !$scopeId) {
                throw new Exception('يجب تحديد ' . ($scopeType === 'branch' ? 'الفرع' : 'أمر العمل'));
            }
            
            if ($approvalModel->isDuplicateAssignment($stepId, $approverUserId, $scopeType, $scopeId)) {
                throw new Exception('يوجد تعيين مماثل بالفعل لهذا المستخدم');
            }
            
            $result = $approvalModel->addAssignment(
                $stepId, $approverUserId, $scopeType, $scopeId,
                $_SESSION['user_id'], $notes, $priority
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم إضافة التعيين بنجاح']);
            } else {
                throw new Exception('فشل في إضافة التعيين');
            }
            break;
            
        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            $isActive = filter_var($_POST['is_active'], FILTER_VALIDATE_BOOLEAN);
            if (!$id) throw new Exception('معرف التعيين غير صحيح');
            $result = $approvalModel->toggleAssignment($id, $isActive);
            if ($result) {
                $actionText = $isActive ? 'تفعيل' : 'إلغاء تفعيل';
                echo json_encode(['success' => true, 'message' => "تم $actionText التعيين بنجاح"]);
            } else {
                throw new Exception('فشل في تحديث التعيين');
            }
            break;

        case 'edit':
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            $stepId = (int)($_POST['step_id'] ?? 0);
            $approverUserId = (int)($_POST['approver_user_id'] ?? 0);
            $scopeType = sanitizeInput($_POST['scope_type'] ?? 'global');
            $scopeId = !empty($_POST['scope_id']) ? (int)$_POST['scope_id'] : null;
            $priority = (int)($_POST['priority'] ?? 1);
            $notes = sanitizeInput($_POST['notes'] ?? '');

            if (!$assignmentId || !$stepId || !$approverUserId) {
                throw new Exception('يرجى ملء جميع البيانات المطلوبة');
            }

            if (!in_array($scopeType, ['global', 'branch', 'work_order'])) {
                throw new Exception('نوع النطاق غير صحيح');
            }

            if (($scopeType === 'branch' || $scopeType === 'work_order') && !$scopeId) {
                throw new Exception('يرجى تحديد ' . ($scopeType === 'branch' ? 'الفرع' : 'أمر العمل'));
            }

            if ($approvalModel->isDuplicateAssignment($stepId, $approverUserId, $scopeType, $scopeId, $assignmentId)) {
                throw new Exception('يوجد تعيين مماثل بالفعل لهذا المستخدم');
            }

            $result = $approvalModel->updateAssignment(
                $assignmentId, $stepId, $approverUserId, $scopeType, $scopeId, $notes, $priority
            );

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث التعيين بنجاح']);
            } else {
                throw new Exception('فشل في تحديث التعيين');
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('معرف التعيين غير صحيح');
            $assignment = $approvalModel->findById($id);
            if (!$assignment) throw new Exception('التعيين غير موجود');
            $result = $approvalModel->removeAssignment($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم حذف التعيين بنجاح']);
            } else {
                throw new Exception('فشل في حذف التعيين');
            }
            break;

        // ===================== إدارة الخطوات =====================
        case 'add_step':
            $stepName = sanitizeInput($_POST['step_name'] ?? '');
            $stepKey = sanitizeInput($_POST['step_key'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $isFinal = filter_var($_POST['is_final'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (empty($stepName) || empty($stepKey)) {
                throw new Exception('اسم الخطوة والمفتاح مطلوبان');
            }

            if (!preg_match('/^[a-z_]+$/', $stepKey)) {
                throw new Exception('المفتاح يجب أن يحتوي على أحرف إنجليزية صغيرة وشرطة سفلية فقط');
            }

            $result = $approvalModel->addStep($stepName, $stepKey, $description, $isFinal);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم إضافة الخطوة بنجاح', 'step_id' => $result]);
            } else {
                throw new Exception('فشل في إضافة الخطوة');
            }
            break;

        case 'edit_step':
            $stepId = (int)($_POST['step_id'] ?? 0);
            if (!$stepId) throw new Exception('معرف الخطوة غير صحيح');

            $data = [];
            if (isset($_POST['step_name'])) $data['step_name'] = sanitizeInput($_POST['step_name']);
            if (isset($_POST['step_key'])) {
                $key = sanitizeInput($_POST['step_key']);
                if (!preg_match('/^[a-z_]+$/', $key)) {
                    throw new Exception('المفتاح يجب أن يحتوي على أحرف إنجليزية صغيرة وشرطة سفلية فقط');
                }
                $data['step_key'] = $key;
            }
            if (isset($_POST['description'])) $data['description'] = sanitizeInput($_POST['description']);
            if (isset($_POST['is_final'])) $data['is_final'] = filter_var($_POST['is_final'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            if (isset($_POST['is_active'])) $data['is_active'] = filter_var($_POST['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            if (isset($_POST['step_order'])) $data['step_order'] = (int)$_POST['step_order'];

            $result = $approvalModel->updateStep($stepId, $data);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث الخطوة بنجاح']);
            } else {
                throw new Exception('فشل في تحديث الخطوة');
            }
            break;

        case 'delete_step':
            $stepId = (int)($_POST['step_id'] ?? 0);
            if (!$stepId) throw new Exception('معرف الخطوة غير صحيح');
            $result = $approvalModel->deleteStep($stepId);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'تم حذف الخطوة بنجاح']);
            } else {
                throw new Exception($result['message']);
            }
            break;

        case 'toggle_step':
            $stepId = (int)($_POST['step_id'] ?? 0);
            $isActive = filter_var($_POST['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (!$stepId) throw new Exception('معرف الخطوة غير صحيح');
            $result = $approvalModel->toggleStep($stepId, $isActive);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الخطوة بنجاح']);
            } else {
                throw new Exception('فشل في تحديث حالة الخطوة');
            }
            break;

        default:
            throw new Exception('إجراء غير صحيح');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
