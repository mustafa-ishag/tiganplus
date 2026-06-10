<?php
/**
 * معالج AJAX لإنشاء المستخلص الجزئي
 * AJAX Handler for Partial Extract Creation
 */

session_start();
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_create')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإنشاء المستخلصات']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];

    // التحقق من وضع التعديل
    $isEdit = isset($_POST['extract_id']) && !empty($_POST['extract_id']);
    $extractId = $isEdit ? intval($_POST['extract_id']) : null;

    // التحقق من البيانات المطلوبة
    $required_fields = ['extract_number', 'branch_id', 'department', 'extract_date', 'work_order_ids', 'extract_values', 'completion_dates'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("الحقل $field مطلوب");
        }
    }

    // في حالة التعديل، التحقق من وجود المستخلص وإمكانية تعديله (فقط المسودات)
    if ($isEdit) {
        $stmt = $db->prepare("SELECT * FROM partial_extracts WHERE id = ? AND (approval_stage = 'draft' OR approval_stage IS NULL)");
        $stmt->execute([$extractId]);
        $existingExtract = $stmt->fetch();

        if (!$existingExtract) {
            throw new Exception("المستخلص غير موجود أو لا يمكن تعديله");
        }
    }

    $extract_number = trim($_POST['extract_number']);
    $entry_sheet_number = trim($_POST['entry_sheet_number'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $branch_id = intval($_POST['branch_id']);
    $department = trim($_POST['department']);
    $extract_date = $_POST['extract_date'];
    $description = trim($_POST['description'] ?? '');
    $action = $_POST['action'] ?? 'save';

    // التحقق من رقم صحيفة الإدخال
    if (!empty($entry_sheet_number)) {
        // يجب أن يكون 10 أرقام فقط
        if (!preg_match('/^[0-9]{10}$/', $entry_sheet_number)) {
            throw new Exception('رقم صحيفة الإدخال يجب أن يكون مكون من 10 أرقام فقط');
        }

        // التحقق من عدم التكرار (إذا لم يكن في وضع التعديل أو تم تغيير الرقم)
        $checkDuplicateQuery = "SELECT id FROM partial_extracts WHERE entry_sheet_number = ?";
        $checkParams = [$entry_sheet_number];

        if ($isEdit) {
            $checkDuplicateQuery .= " AND id != ?";
            $checkParams[] = $extract_id;
        }

        $stmt = $db->prepare($checkDuplicateQuery);
        $stmt->execute($checkParams);

        if ($stmt->fetch()) {
            throw new Exception('رقم صحيفة الإدخال مستخدم مسبقاً. يرجى استخدام رقم آخر');
        }
    }
    
    $work_order_ids = $_POST['work_order_ids'];
    $extract_values = $_POST['extract_values'];
    $completion_dates = $_POST['completion_dates'];

    // تسجيل البيانات المستلمة
    error_log("Partial Extract - Received data:");
    error_log("Work Order IDs: " . json_encode($work_order_ids));
    error_log("Extract Values: " . json_encode($extract_values));
    error_log("Completion Dates: " . json_encode($completion_dates));
    
    // التحقق من وجود أوامر العمل
    if (empty($work_order_ids)) {
        throw new Exception('يجب إضافة أمر عمل واحد على الأقل');
    }
    
    // التحقق من تطابق البيانات
    foreach ($work_order_ids as $wo_id) {
        if (!isset($extract_values[$wo_id]) || !isset($completion_dates[$wo_id])) {
            throw new Exception('بيانات أوامر العمل غير مكتملة');
        }
        
        if (empty($extract_values[$wo_id]) || empty($completion_dates[$wo_id])) {
            throw new Exception('يجب إدخال قيمة المستخلص وتاريخ الإنجاز لجميع أوامر العمل');
        }
    }
    
    // التحقق من عدم تكرار رقم المستخلص (إلا في حالة التعديل)
    $checkQuery = "SELECT id FROM partial_extracts WHERE extract_number = ?" . ($isEdit ? " AND id != ?" : "");
    $checkStmt = $db->prepare($checkQuery);
    $checkParams = [$extract_number];
    if ($isEdit) {
        $checkParams[] = $extractId;
    }
    $checkStmt->execute($checkParams);
    if ($checkStmt->fetch()) {
        throw new Exception('رقم المستخلص موجود مسبقاً');
    }
    
    // حساب المبالغ
    $total_amount = 0;
    foreach ($work_order_ids as $wo_id) {
        $total_amount += floatval($extract_values[$wo_id]);
    }

    $tax_rate = 15.00;
    $tax_amount = $total_amount * ($tax_rate / 100);
    $net_amount = $total_amount; // الصافي = إجمالي المبلغ بدون ضريبة
    
    // تحديد الحالة - يتم التقديم مباشرة
    $status = 'submitted';
    $approval_stage = 'technical_support';
    
    $db->beginTransaction();

    if ($isEdit) {
        // تحديث المستخلص الجزئي
        $updateExtractQuery = "
            UPDATE partial_extracts SET
                extract_number = ?, entry_sheet_number = ?, invoice_number = ?, branch_id = ?, department = ?,
                description = ?, extract_date = ?, total_amount = ?, tax_rate = ?,
                tax_amount = ?, net_amount = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $updateExtractStmt = $db->prepare($updateExtractQuery);
        $updateExtractStmt->execute([
            $extract_number,
            !empty($entry_sheet_number) ? $entry_sheet_number : null,
            $invoice_number,
            $branch_id,
            $department,
            $description,
            $extract_date,
            $total_amount,
            $tax_rate,
            $tax_amount,
            $net_amount,
            $extractId
        ]);

        $extract_id = $extractId;

        // حذف أوامر العمل المرتبطة السابقة
        $deleteWorkOrdersQuery = "DELETE FROM partial_extract_work_orders WHERE partial_extract_id = ?";
        $deleteWorkOrdersStmt = $db->prepare($deleteWorkOrdersQuery);
        $deleteWorkOrdersStmt->execute([$extractId]);

    } else {
        // إدراج المستخلص الجزئي الجديد
        $insertExtractQuery = "
            INSERT INTO partial_extracts (
                extract_number, entry_sheet_number, invoice_number, branch_id, department, created_by, description,
                extract_date, total_amount, tax_rate, tax_amount, net_amount,
                status, approval_stage, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $insertExtractStmt = $db->prepare($insertExtractQuery);
        $insertExtractStmt->execute([
            $extract_number,
            !empty($entry_sheet_number) ? $entry_sheet_number : null,
            $invoice_number,
            $branch_id,
            $department,
            $user_id,
            $description,
            $extract_date,
            $total_amount,
            $tax_rate,
            $tax_amount,
            $net_amount,
            $status,
            $approval_stage
        ]);

        $extract_id = $db->lastInsertId();
    }
    
    // إدراج أوامر العمل المرتبطة
    $insertWorkOrderQuery = "
        INSERT INTO partial_extract_work_orders (
            partial_extract_id, work_order_id, completion_date, 
            extract_value, added_by, added_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ";
    
    $insertWorkOrderStmt = $db->prepare($insertWorkOrderQuery);
    
    foreach ($work_order_ids as $wo_id) {
        $insertWorkOrderStmt->execute([
            $extract_id,
            intval($wo_id),
            $completion_dates[$wo_id],
            floatval($extract_values[$wo_id]),
            $user_id
        ]);

        // تحديث تاريخ الإنجاز في أمر العمل
        $completionDate = $completion_dates[$wo_id];
        $workOrderId = intval($wo_id);

        // تسجيل محاولة التحديث
        error_log("Attempting to update work order {$workOrderId} with completion date: {$completionDate}");

        // جلب التاريخ الحالي للمقارنة
        $currentDateQuery = "SELECT receipt_date FROM work_orders WHERE id = ?";
        $currentDateStmt = $db->prepare($currentDateQuery);
        $currentDateStmt->execute([$workOrderId]);
        $currentReceiptDate = $currentDateStmt->fetchColumn();

        error_log("Current receipt_date for work order {$workOrderId}: " . ($currentReceiptDate ?: 'NULL'));

        $updateWorkOrderQuery = "
            UPDATE work_orders
            SET receipt_date = ?
            WHERE id = ? AND (receipt_date IS NULL OR receipt_date != ?)
        ";
        $updateWorkOrderStmt = $db->prepare($updateWorkOrderQuery);
        $updateResult = $updateWorkOrderStmt->execute([
            $completionDate,
            $workOrderId,
            $completionDate
        ]);

        $rowsAffected = $updateWorkOrderStmt->rowCount();
        error_log("Update result for work order {$workOrderId}: " . ($updateResult ? 'SUCCESS' : 'FAILED') . ", rows affected: {$rowsAffected}");
    }
    
    // معالجة المرفقات
    $attachments_count = 0;
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../../../uploads/extracts/partial/' . $extract_id . '/';
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $insertAttachmentQuery = "
            INSERT INTO partial_extract_attachments (
                partial_extract_id, file_name, original_name, file_path, 
                file_size, file_type, uploaded_by, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $insertAttachmentStmt = $db->prepare($insertAttachmentQuery);
        
        foreach ($_FILES['attachments']['name'] as $key => $original_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                    $insertAttachmentStmt->execute([
                        $extract_id,
                        $file_name,
                        $original_name,
                        $file_path,
                        $_FILES['attachments']['size'][$key],
                        $_FILES['attachments']['type'][$key],
                        $user_id
                    ]);
                    $attachments_count++;
                }
            }
        }
        
        // تحديث عدد المرفقات
        if ($attachments_count > 0) {
            $updateAttachmentsCountQuery = "UPDATE partial_extracts SET attachments_count = ? WHERE id = ?";
            $updateAttachmentsCountStmt = $db->prepare($updateAttachmentsCountQuery);
            $updateAttachmentsCountStmt->execute([$attachments_count, $extract_id]);
        }
    }
    
    // إضافة نشاط (اختياري)
    try {
        $activity_description = $isEdit ? 'تم تحديث المستخلص الجزئي' : 'تم إنشاء وتقديم المستخلص الجزئي للمراجعة';
        $activity_type = $isEdit ? 'updated' : 'created';

        $insertActivityQuery = "
            INSERT INTO partial_extract_activities (
                partial_extract_id, activity_type, description, performed_by, performed_at
            ) VALUES (?, ?, ?, ?, NOW())
        ";

        $insertActivityStmt = $db->prepare($insertActivityQuery);
        $insertActivityStmt->execute([
            $extract_id,
            $activity_type,
            $activity_description,
            $user_id
        ]);
    } catch (Exception $activityError) {
        // تجاهل خطأ جدول الأنشطة إذا لم يكن موجوداً
        error_log("Activity log error: " . $activityError->getMessage());
    }

    $db->commit();

    // إرسال الاستجابة
    $response = [
        'success' => true,
        'message' => $isEdit ? 'تم تحديث المستخلص الجزئي بنجاح' : 'تم إنشاء وتقديم المستخلص الجزئي بنجاح',
        'extract_id' => $extract_id,
        'extract_number' => $extract_number,
        'redirect_url' => 'index.php'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
