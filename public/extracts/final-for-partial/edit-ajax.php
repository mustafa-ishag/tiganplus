<?php
/**
 * معالج AJAX لتعديل المستخلص النهائي للجزئية
 * AJAX handler for editing final for partial extract
 */

session_start();

// منع الوصول المباشر
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // التحقق من الصلاحيات
    if (!hasPermission('extracts_edit')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل المستخلصات'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $db = getDB();
    
    // التحقق من البيانات المطلوبة
    $requiredFields = ['extract_id', 'extract_number', 'related_partial_extract_id', 'branch_id', 'extract_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new InvalidArgumentException("الحقل $field مطلوب");
        }
    }
    
    $extractId = (int) $_POST['extract_id'];
    $userId = $_SESSION['user_id'];
    
    // التحقق من وجود المستخلص وإمكانية تعديله
    $stmt = $db->prepare("
        SELECT id, approval_stage, created_by, related_partial_extract_id
        FROM final_for_partial_extracts
        WHERE id = ? AND (approval_stage = 'draft' OR approval_stage IS NULL)
    ");
    $stmt->execute([$extractId]);
    $existingExtract = $stmt->fetch();
    
    if (!$existingExtract) {
        throw new Exception('المستخلص غير موجود أو لا يمكن تعديله (يجب أن يكون في مرحلة المسودة)');
    }
    
    // التحقق من أن المستخلص الجزئي المرتبط لم يتغير
    if ($_POST['related_partial_extract_id'] != $existingExtract['related_partial_extract_id']) {
        throw new Exception('لا يمكن تغيير المستخلص الجزئي المرتبط بعد الإنشاء');
    }
    
    // التحقق من أوامر العمل
    if (!isset($_POST['work_order_ids']) || !is_array($_POST['work_order_ids']) || empty($_POST['work_order_ids'])) {
        throw new InvalidArgumentException('يجب إضافة أمر عمل واحد على الأقل');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // حساب المبالغ
    $totalAmount = 0;
    $totalPenalty = 0;
    
    foreach ($_POST['work_order_ids'] as $workOrderId) {
        $extractValue = floatval($_POST['extract_values'][$workOrderId] ?? 0);
        $penaltyAmount = floatval($_POST['penalty_amounts'][$workOrderId] ?? 0);

        $totalAmount += $extractValue;
        $totalPenalty += $penaltyAmount;
    }

    // جلب ضريبة المستخلص الجزئي المرتبط
    $stmt_tax = $db->prepare("SELECT tax_amount FROM partial_extracts WHERE id = ?");
    $stmt_tax->execute([$_POST['related_partial_extract_id']]);
    $partial_tax = $stmt_tax->fetch();
    $partialExtractTaxAmount = $partial_tax ? floatval($partial_tax['tax_amount']) : 0;

    // حساب المبالغ
    // الصافي = مجموع قيم أوامر العمل + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة
    $taxRate = 15.00;
    $taxAmount = $totalAmount * ($taxRate / 100);
    $netAmount = $totalAmount + $taxAmount + $partialExtractTaxAmount - $totalPenalty;
    
    // تحديث المستخلص النهائي للجزئي
    $stmt = $db->prepare("
        UPDATE final_for_partial_extracts SET
            extract_number = ?,
            invoice_number = ?,
            extract_date = ?,
            branch_id = ?,
            related_partial_extract_id = ?,
            description = ?,
            total_amount = ?,
            tax_rate = ?,
            tax_amount = ?,
            total_penalty_amount = ?,
            net_amount = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $_POST['extract_number'],
        $_POST['invoice_number'] ?? null,
        $_POST['extract_date'],
        $_POST['branch_id'],
        $_POST['related_partial_extract_id'],
        $_POST['description'] ?? null,
        $totalAmount,
        $taxRate,
        $taxAmount,
        $totalPenalty,
        $netAmount,
        $extractId
    ]);
    
    if (!$result) {
        throw new Exception('فشل في تحديث المستخلص');
    }
    
    // حذف أوامر العمل القديمة
    $stmt = $db->prepare("DELETE FROM final_for_partial_extract_work_orders WHERE final_for_partial_extract_id = ?");
    $stmt->execute([$extractId]);
    
    // إضافة أوامر العمل الجديدة
    $stmt = $db->prepare("
        INSERT INTO final_for_partial_extract_work_orders 
        (final_for_partial_extract_id, work_order_id, completion_date, extract_value, penalty_amount, notes, added_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($_POST['work_order_ids'] as $workOrderId) {
        $completionDate = $_POST['completion_dates'][$workOrderId] ?? null;
        $extractValue = floatval($_POST['extract_values'][$workOrderId] ?? 0);
        $penaltyAmount = floatval($_POST['penalty_amounts'][$workOrderId] ?? 0);
        $notes = $_POST['work_order_notes'][$workOrderId] ?? null;
        
        if (empty($completionDate)) {
            throw new InvalidArgumentException("تاريخ الإنجاز مطلوب لأمر العمل $workOrderId");
        }
        
        if ($extractValue <= 0) {
            throw new InvalidArgumentException("قيمة المستخلص يجب أن تكون أكبر من صفر لأمر العمل $workOrderId");
        }
        
        $result = $stmt->execute([
            $extractId,
            $workOrderId,
            $completionDate,
            $extractValue,
            $penaltyAmount,
            $notes,
            $userId
        ]);
        
        if (!$result) {
            throw new Exception("فشل في إضافة أمر العمل $workOrderId");
        }
    }
    
    $db->commit();
    
    // إرسال الاستجابة
    $response = [
        'success' => true,
        'message' => 'تم تحديث المستخلص النهائي للجزئية بنجاح',
        'extract_id' => $extractId,
        'extract_number' => $_POST['extract_number'],
        'redirect_url' => 'view.php?id=' . $extractId
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
