<?php
/**
 * نموذج طلبات الصرف
 * Material Request Model
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../includes/EmailService.php';

class MaterialRequest extends BaseModel
{
    protected $table = 'material_requests';

    /**
     * البحث عن طلب بواسطة رقم الطلب
     */
    public function findByRequestNumber($requestNumber)
    {
        return $this->findOneWhere('request_number = ?', [$requestNumber]);
    }

    /**
     * الحصول على الطلبات بواسطة الحالة
     */
    public function findByStatus($status, $branchId = null)
    {
        $condition = 'status = ?';
        $params = [$status];

        if ($branchId) {
            $condition .= ' AND branch_id = ?';
            $params[] = $branchId;
        }

        return $this->fetchAll("SELECT * FROM {$this->table} WHERE {$condition} ORDER BY created_at DESC", $params);
    }

    /**
     * الحصول على الطلبات المعلقة للموافقة
     */
    public function getPendingApprovalRequests($stepId, $branchId = null)
    {
        try {
            require_once __DIR__ . '/ApprovalAssignment.php';
            $approvalModel = new ApprovalAssignment();
            $step = $approvalModel->getStepById($stepId);
            if (!$step) return [];

            // الخطوة الأولى تنتظر حالة submitted
            // الخطوات اللاحقة تنتظر حالة step_X_approved (حيث X هو ترتيب الخطوة السابقة)
            $firstStep = $approvalModel->getFirstActiveStep();
            if ($firstStep && $step['id'] == $firstStep['id']) {
                $expectedStatus = 'submitted';
            } else {
                $expectedStatus = 'pending_step_' . $step['step_order'];
            }

            return $this->findByStatus($expectedStatus, $branchId);
        } catch (Exception $e) {
            error_log('Error in getPendingApprovalRequests: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * إنشاء طلب صرف جديد
     */
    public function createRequest($data, $details = [], $action = 'save_draft')
    {
        try {
            $this->beginTransaction();

            // توليد رقم الطلب
            $data['request_number'] = $this->generateRequestNumber();
            // التأكد من أن الحالة ليست NULL - استخدام 'draft' كقيمة افتراضية
            $data['status'] = $action === 'submit' ? 'submitted' : 'draft';
            if (empty($data['status'])) {
                $data['status'] = 'draft';
            }
            $data['created_at'] = getCurrentDateTime();
            $data['updated_at'] = getCurrentDateTime();

            // إدراج الطلب الرئيسي
            $requestId = $this->insert($data);

            // إدراج تفاصيل الطلب
            if (!empty($details)) {
                $detailsResult = $this->insertRequestDetails($requestId, $details);
                if (!$detailsResult['success']) {
                    $this->rollback();
                    return $detailsResult;
                }
            }

            $this->commit();

            // تسجيل النشاط (مغلف بـ try/catch لتجنب إيقاف بقية العمليات)
            try {
                $actionText = $action === 'submit' ? 'وإرسال' : '';
                logActivity($_SESSION['user_id'] ?? 0, 'create_material_request', "تم إنشاء {$actionText} طلب صرف جديد: {$data['request_number']}");
            } catch (Exception $logEx) {
                error_log('[MaterialRequest] logActivity failed: ' . $logEx->getMessage());
            }

            // ===== إرسال بريد إلكتروني عند تقديم الطلب =====
            if ($action === 'submit' && !empty($details)) {
                try {
                    // جلب تفاصيل المواد كاملة لإرسالها في البريد
                    $detailsWithMaterials = $this->fetchAll(
                        "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
                         FROM material_request_details mrd
                         JOIN materials m ON mrd.material_id = m.id
                         LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                         WHERE mrd.request_id = ?
                         ORDER BY mc.description",
                        [$requestId]
                    );
                    $emailData = array_merge($data, ['id' => $requestId]);
                    $emailService = new EmailService();
                    $emailService->sendMaterialRequestNotification($emailData, $detailsWithMaterials);
                    error_log('[MaterialRequest] Email sent for request: ' . $data['request_number']);
                } catch (Exception $emailEx) {
                    error_log('[MaterialRequest] فشل إرسال البريد: ' . $emailEx->getMessage());
                }
            }

            return ['success' => true, 'request_id' => $requestId, 'request_number' => $data['request_number']];

        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في إنشاء الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * إدراج تفاصيل الطلب
     */
    private function insertRequestDetails($requestId, $details)
    {
        try {
            $materialModel = new Material();

            foreach ($details as $detail) {
                // التحقق من توفر المادة
                $material = $materialModel->findByIdFull($detail['material_id']);
                if (!$material) {
                    return ['success' => false, 'message' => "المادة غير موجودة: {$detail['material_id']}"];
                }

                // التحقق من توفر الكمية
                if ($material['current_stock'] < $detail['requested_quantity']) {
                    $materialName = $material['description'] ?? $material['item_number'];
                    return ['success' => false, 'message' => "الكمية المطلوبة غير متوفرة للمادة: {$materialName}"];
                }

                // إدراج التفصيل
                $detailData = [
                    'request_id' => $requestId,
                    'material_id' => $detail['material_id'],
                    'requested_quantity' => $detail['requested_quantity'],
                    'approved_quantity' => 0,
                    'purpose' => $detail['purpose'] ?? '',
                    'notes' => $detail['notes'] ?? '',
                    'created_at' => getCurrentDateTime(),
                    'updated_at' => getCurrentDateTime()
                ];

                $sql = "INSERT INTO material_request_details (request_id, material_id, requested_quantity, approved_quantity, purpose, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $this->query($sql, array_values($detailData));
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إدراج تفاصيل الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * توليد رقم الطلب
     */
    private function generateRequestNumber()
    {
        $date = date('Ymd');

        // البحث عن آخر رقم لنفس اليوم
        $lastNumber = $this->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(request_number, -4) AS UNSIGNED)) 
             FROM material_requests 
             WHERE DATE(created_at) = CURDATE()"
        ) ?: 0;

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "REQ-{$date}-{$newNumber}";
    }

    /**
     * تقديم الطلب للموافقة
     */
    public function submitRequest($requestId)
    {
        try {
            $request = $this->findById($requestId);
            if (!$request) {
                return ['success' => false, 'message' => 'الطلب غير موجود'];
            }

            if (!in_array($request['status'], ['draft', 'revision_requested'])) {
                return ['success' => false, 'message' => 'الطلب ليس في حالة مسودة أو طلب تعديل'];
            }

            $result = $this->update($requestId, [
                'status' => 'submitted',
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                // حجز المخزون عند تقديم الطلب
                $reservationResult = $this->reserveStockForRequest($requestId);
                if (!$reservationResult['success']) {
                    // تحذير فقط، لا نرجع الخطأ
                    try {
                        logActivity($_SESSION['user_id'] ?? 0, 'warning_stock_reservation', "تحذير: فشل حجز المخزون للطلب: {$request['request_number']}");
                    } catch (Exception $e) {
                    }
                }

                try {
                    logActivity($_SESSION['user_id'] ?? 0, 'submit_material_request', "تم تقديم طلب الصرف: {$request['request_number']}");
                } catch (Exception $e) {
                }
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'فشل في تقديم الطلب'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تقديم الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * حجز المخزون عند تقديم الطلب
     */
    private function reserveStockForRequest($requestId)
    {
        try {
            // الحصول على تفاصيل الطلب
            $details = $this->fetchAll(
                "SELECT mrd.*, m.current_stock
                 FROM material_request_details mrd
                 JOIN materials m ON mrd.material_id = m.id
                 WHERE mrd.request_id = ?",
                [$requestId]
            );

            if (empty($details)) {
                return ['success' => true, 'message' => 'لا توجد مواد للحجز'];
            }

            // حجز كل مادة في جدول materials
            foreach ($details as $detail) {
                $this->query(
                    "UPDATE materials
                     SET reserved_quantity = reserved_quantity + ?
                     WHERE id = ?",
                    [$detail['requested_quantity'], $detail['material_id']]
                );
            }

            return ['success' => true, 'message' => 'تم حجز المخزون بنجاح'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حجز المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * الموافقة على الطلب — نظام ديناميكي
     * @param int $requestId معرف الطلب
     * @param int $stepId معرف خطوة الاعتماد
     * @param int $approvedBy معرف المعتمد
     * @param string $notes ملاحظات
     */
    public function approveRequest($requestId, $stepId, $approvedBy, $notes = '')
    {
        try {
            $this->beginTransaction();

            $request = $this->findById($requestId);
            if (!$request) {
                $this->rollback();
                return ['success' => false, 'message' => 'الطلب غير موجود'];
            }

            require_once __DIR__ . '/ApprovalAssignment.php';
            $approvalModel = new ApprovalAssignment();

            // جلب الخطوة المطلوبة
            $step = $approvalModel->getStepById($stepId);
            if (!$step || !$step['is_active']) {
                $this->rollback();
                return ['success' => false, 'message' => 'خطوة الاعتماد غير صحيحة أو غير فعالة'];
            }

            // التحقق من صلاحية المعتمد
            if (!$approvalModel->canUserApproveStep($approvedBy, $stepId, $request['branch_id'], $request['work_order_id'])) {
                $this->rollback();
                return ['success' => false, 'message' => 'ليس لديك صلاحية للموافقة على هذا الطلب في هذه المرحلة'];
            }

            // التحقق من أن الطلب في الحالة الصحيحة لهذه الخطوة
            $expectedStatus = $this->getExpectedStatusForStep($step);
            if ($request['status'] !== $expectedStatus) {
                $this->rollback();
                return ['success' => false, 'message' => 'الطلب ليس في الحالة المناسبة لهذه الخطوة (الحالة الحالية: ' . $request['status'] . ', المتوقعة: ' . $expectedStatus . ')'];
            }

            // تحديد الحالة التالية
            $nextStep = $approvalModel->getNextStep($stepId);
            $isFinal = $step['is_final'] || !$nextStep;
            $newStatus = $isFinal ? 'approved' : 'pending_step_' . $nextStep['step_order'];
            $newApprovalStep = $isFinal ? $step['step_order'] : $nextStep['step_order'];

            // تحديث بيانات الموافقة
            $updateData = [
                'status' => $newStatus,
                'current_approval_step' => $newApprovalStep,
                'updated_at' => getCurrentDateTime()
            ];

            $result = $this->update($requestId, $updateData);

            if ($result) {
                // تسجيل عملية الاعتماد في السجل
                $approvalModel->logApproval($requestId, $stepId, 'approved', $approvedBy, $notes);

                $this->commit();

                try {
                    logActivity($approvedBy, 'approve_material_request', "تم اعتماد طلب الصرف ({$step['step_name']}): {$request['request_number']}");
                } catch (Exception $e) {
                }

                // خصم المواد من المخزون عند الموافقة النهائية
                if ($isFinal) {
                    $deductionResult = $this->deductMaterialsFromStock($requestId);
                    if (!$deductionResult['success']) {
                        error_log("[approveRequest] Deduction failed for request #{$requestId}: " . $deductionResult['message']);
                        return [
                            'success' => true,
                            'warning' => $deductionResult['message'],
                            'message' => 'تم اعتماد الطلب لكن فشل خصم المواد من المخزون: ' . $deductionResult['message']
                        ];
                    }
                }

                return ['success' => true, 'is_final' => $isFinal];
            }

            $this->rollback();
            return ['success' => false, 'message' => 'فشل في الموافقة على الطلب'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في الموافقة على الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * تحديد الحالة المتوقعة لخطوة معينة
     */
    public function getExpectedStatusForStep($step)
    {
        require_once __DIR__ . '/ApprovalAssignment.php';
        $approvalModel = new ApprovalAssignment();
        $firstStep = $approvalModel->getFirstActiveStep();

        if ($firstStep && $step['id'] == $firstStep['id']) {
            return 'submitted';
        }

        return 'pending_step_' . $step['step_order'];
    }

    /**
     * الحصول على الخطوة الحالية للطلب
     */
    public function getCurrentStepForRequest($request)
    {
        require_once __DIR__ . '/ApprovalAssignment.php';
        $approvalModel = new ApprovalAssignment();

        $status = $request['status'];

        if ($status === 'submitted') {
            return $approvalModel->getFirstActiveStep();
        }

        if (preg_match('/^pending_step_(\d+)$/', $status, $matches)) {
            $stepOrder = (int) $matches[1];
            return $this->fetchOne(
                "SELECT * FROM approval_steps WHERE step_order = ? AND is_active = 1",
                [$stepOrder]
            );
        }

        return null;
    }

    /**
     * رفض الطلب - مع إرجاع المخزون المحجوز
     */
    public function rejectRequest($requestId, $rejectedBy, $rejectionReason, $stepId = null)
    {
        try {
            $this->beginTransaction();

            $request = $this->findById($requestId);
            if (!$request) {
                return ['success' => false, 'message' => 'الطلب غير موجود'];
            }

            // إرجاع المخزون المحجوز
            $releaseResult = $this->releaseReservedStock($requestId);
            if (!$releaseResult['success']) {
                $this->rollback();
                return $releaseResult;
            }

            $result = $this->update($requestId, [
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => getCurrentDateTime(),
                'rejection_reason' => $rejectionReason,
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                // تسجيل الرفض في السجل
                if ($stepId) {
                    require_once __DIR__ . '/ApprovalAssignment.php';
                    $approvalModel = new ApprovalAssignment();
                    $approvalModel->logApproval($requestId, $stepId, 'rejected', $rejectedBy, $rejectionReason);
                }

                $this->commit();
                try {
                    logActivity($rejectedBy, 'reject_material_request', "تم رفض طلب الصرف: {$request['request_number']}");
                } catch (Exception $e) {
                }
                return ['success' => true];
            }

            $this->rollback();
            return ['success' => false, 'message' => 'فشل في رفض الطلب'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في رفض الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * طلب تعديل — إعادة الطلب لمقدمه لإجراء تعديلات
     * @param int $requestId معرف الطلب
     * @param int $requestedBy معرف المعتمد الذي طلب التعديل
     * @param string $notes ملاحظات التعديل المطلوب
     * @param int|null $stepId معرف الخطوة
     */
    public function requestRevision($requestId, $requestedBy, $notes, $stepId = null)
    {
        try {
            $this->beginTransaction();

            $request = $this->findById($requestId);
            if (!$request) {
                $this->rollback();
                return ['success' => false, 'message' => 'الطلب غير موجود'];
            }

            // تحرير المخزون المحجوز عند طلب التعديل (سيُعاد حجزه عند إعادة الإرسال)
            $this->releaseReservedStock($requestId);

            // تحديث حالة الطلب إلى طلب تعديل
            $result = $this->update($requestId, [
                'status' => 'revision_requested',
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                // تسجيل طلب التعديل في السجل
                if ($stepId) {
                    require_once __DIR__ . '/ApprovalAssignment.php';
                    $approvalModel = new ApprovalAssignment();
                    $approvalModel->logApproval($requestId, $stepId, 'revision_requested', $requestedBy, $notes);
                }

                $this->commit();
                try {
                    logActivity($requestedBy, 'request_revision_material_request', "تم طلب تعديل طلب الصرف: {$request['request_number']}");
                } catch (Exception $e) {
                }

                // إرسال إشعار لمقدم الطلب بأنه مطلوب تعديل
                try {
                    require_once __DIR__ . '/../includes/EmailService.php';
                    require_once __DIR__ . '/../includes/WhatsAppService.php';

                    // جلب بيانات الطلب الكاملة مع معلومات مقدم الطلب
                    $fullRequest = $this->fetchOne(
                        "SELECT mr.*,
                                wo.work_order_number,
                                u1.full_name as requested_by_name,
                                u1.email as requested_by_email,
                                u1.phone as requested_by_phone
                         FROM material_requests mr
                         LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
                         LEFT JOIN users u1 ON mr.requested_by = u1.id
                         WHERE mr.id = ?",
                        [$requestId]
                    );

                    // جلب اسم المعتمد الذي طلب التعديل
                    $approver = $this->fetchOne(
                        "SELECT full_name FROM users WHERE id = ?",
                        [$requestedBy]
                    );
                    $approverName = $approver['full_name'] ?? 'المعتمد';

                    $whatsappService = new WhatsAppService();
                    $emailService = new EmailService();

                    // إرسال واتساب لمقدم الطلب
                    $requesterPhone = $fullRequest['requested_by_phone'] ?? '';
                    if (!empty($requesterPhone)) {
                        $whatsappService->sendRevisionRequestNotification($fullRequest, $requesterPhone, $approverName, $notes);
                    }

                    // إرسال بريد إلكتروني لمقدم الطلب
                    $requesterEmail = $fullRequest['requested_by_email'] ?? '';
                    if (!empty($requesterEmail)) {
                        $emailService->sendRevisionRequestEmail($fullRequest, $requesterEmail, $approverName, $notes);
                    }

                    error_log('[MaterialRequest] Revision notification sent to requester for: ' . $request['request_number']);
                } catch (Exception $notifEx) {
                    error_log('[MaterialRequest] فشل إرسال إشعار طلب التعديل: ' . $notifEx->getMessage());
                }

                return ['success' => true];
            }

            $this->rollback();
            return ['success' => false, 'message' => 'فشل في طلب التعديل'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في طلب التعديل: ' . $e->getMessage()];
        }
    }

    /**
     * الحصول على الطلب مع التفاصيل
     */
    public function getRequestWithDetails($requestId)
    {
        $request = $this->findById($requestId);
        if (!$request) {
            return null;
        }

        $details = $this->fetchAll(
            "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
             FROM material_request_details mrd
             JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE mrd.request_id = ?
             ORDER BY mc.description",
            [$requestId]
        );

        $request['details'] = $details;
        return $request;
    }

    /**
     * الحصول على إحصائيات الطلبات
     */
    public function getRequestStats($branchId = null, $dateFrom = null, $dateTo = null)
    {
        $whereConditions = [];
        $params = [];

        if ($branchId) {
            $whereConditions[] = 'branch_id = ?';
            $params[] = $branchId;
        }

        if ($dateFrom) {
            $whereConditions[] = 'request_date >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = 'request_date <= ?';
            $params[] = $dateTo;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $stats = [];

        // إحصائيات حسب الحالة
        $sql = "
            SELECT status, 
                   COUNT(*) as count,
                   SUM(total_items) as total_value
            FROM material_requests 
            {$whereClause}
            GROUP BY status
        ";
        $stats['by_status'] = $this->fetchAll($sql, $params);

        // الطلبات المعلقة
        $stats['pending_approval'] = $this->count("status IN ('submitted', 'warehouse_approved')" . ($branchId ? " AND branch_id = $branchId" : ""));

        return $stats;
    }

    /**
     * الحصول على إحصائيات طلبات الصرف للواجهة
     */
    public function getMaterialRequestStats($branchId = null)
    {
        $whereConditions = ['1=1'];
        $params = [];

        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $stats = $this->fetchOne(
            "SELECT
                COUNT(*) as total_requests,
                SUM(CASE WHEN mr.status = 'draft' THEN 1 ELSE 0 END) as draft_requests,
                SUM(CASE WHEN mr.status = 'submitted' THEN 1 ELSE 0 END) as submitted_requests,
                SUM(CASE WHEN mr.status = 'warehouse_approved' THEN 1 ELSE 0 END) as warehouse_approved_requests,
                SUM(CASE WHEN mr.status = 'approved' THEN 1 ELSE 0 END) as project_approved_requests,
                SUM(CASE WHEN mr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN mr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN mr.status IN ('submitted', 'warehouse_approved') THEN 1 ELSE 0 END) as pending_requests
             FROM material_requests mr
             LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
             {$whereClause}",
            $params
        );

        // حساب إجمالي الكمية للطلبات المعتمدة
        $totalQuantitySql = "SELECT SUM(mrd.requested_quantity)
                         FROM material_request_details mrd
                         JOIN material_requests mr ON mrd.request_id = mr.id
                         JOIN work_orders wo ON mr.work_order_id = wo.id
                         WHERE mr.status = 'approved'";

        $totalQuantityParams = [];
        if ($branchId) {
            $totalQuantitySql .= " AND wo.branch_id = ?";
            $totalQuantityParams[] = $branchId;
        }

        $totalQuantity = $this->fetchColumn($totalQuantitySql, $totalQuantityParams) ?: 0;

        return [
            'total_requests' => (int) ($stats['total_requests'] ?? 0),
            'draft_requests' => (int) ($stats['draft_requests'] ?? 0),
            'submitted_requests' => (int) ($stats['submitted_requests'] ?? 0),
            'warehouse_approved_requests' => (int) ($stats['warehouse_approved_requests'] ?? 0),
            'project_approved_requests' => (int) ($stats['project_approved_requests'] ?? 0),
            'approved_requests' => (int) ($stats['approved_requests'] ?? 0),
            'rejected_requests' => (int) ($stats['rejected_requests'] ?? 0),
            'pending_requests' => (int) ($stats['pending_requests'] ?? 0),
            'total_quantity' => (float) $totalQuantity
        ];
    }

    /**
     * تحديث طلب الصرف
     */
    public function updateRequest($requestId, $requestData, $materials, $action = 'save_draft')
    {
        try {
            $this->beginTransaction();

            // التحقق من وجود الطلب
            $request = $this->findById($requestId);
            if (!$request) {
                $this->rollback();
                return ['success' => false, 'message' => 'الطلب غير موجود'];
            }

            // التحقق من إمكانية التحديث
            if (!in_array($request['status'], ['draft', 'revision_requested'])) {
                $this->rollback();
                return ['success' => false, 'message' => 'لا يمكن تحديث الطلب بعد إرساله'];
            }

            $wasRevisionRequested = $request['status'] === 'revision_requested';

            // تحديث الحالة حسب الإجراء
            $requestData['status'] = $action === 'submit' ? 'submitted' : ($wasRevisionRequested ? 'revision_requested' : 'draft');
            $requestData['updated_at'] = getCurrentDateTime();

            // عند إعادة الإرسال: إعادة تعيين مسار الموافقات ليبدأ من الخطوة الأولى
            if ($action === 'submit') {
                require_once __DIR__ . '/ApprovalAssignment.php';
                $approvalModel = new ApprovalAssignment();
                $firstStep = $approvalModel->getFirstActiveStep();
                $requestData['current_approval_step'] = $firstStep ? $firstStep['step_order'] : 1;

                // مسح بيانات الرفض السابقة
                $requestData['rejected_by'] = null;
                $requestData['rejected_at'] = null;
                $requestData['rejection_reason'] = null;
            }

            // تحرير المخزون المحجوز القديم قبل تحديث التفاصيل (إذا كانت المواد قد تغيرت)
            if ($action === 'submit' || $wasRevisionRequested) {
                $this->releaseReservedStock($requestId);
            }

            // تحديث بيانات الطلب
            $updateResult = $this->update($requestId, $requestData);
            if (!$updateResult) {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في تحديث بيانات الطلب'];
            }

            // حذف التفاصيل القديمة
            $this->query(
                "DELETE FROM material_request_details WHERE request_id = ?",
                [$requestId]
            );

            // تحويل المواد إلى التنسيق المطلوب وإضافة التفاصيل الجديدة
            $details = [];
            foreach ($materials as $material) {
                if (!empty($material['material_id']) && !empty($material['quantity'])) {
                    $details[] = [
                        'material_id' => $material['material_id'],
                        'requested_quantity' => $material['quantity'],
                        'purpose' => '',
                        'notes' => ''
                    ];
                }
            }

            if (!empty($details)) {
                $detailsResult = $this->insertRequestDetails($requestId, $details);
                if (!$detailsResult['success']) {
                    $this->rollback();
                    return $detailsResult;
                }
            }

            $this->commit();

            // بعد نجاح التحديث: حجز المخزون الجديد وإرسال الإشعارات عند الإرسال
            if ($action === 'submit') {
                // حجز المخزون للمواد الجديدة
                $reservationResult = $this->reserveStockForRequest($requestId);
                if (!$reservationResult['success']) {
                    try {
                        logActivity($_SESSION['user_id'] ?? 0, 'warning_stock_reservation', "تحذير: فشل حجز المخزون للطلب: {$request['request_number']}");
                    } catch (Exception $e) {
                    }
                }

                // إرسال الإشعارات (بريد إلكتروني + واتساب) عند الإرسال
                try {
                    require_once __DIR__ . '/../includes/WhatsAppService.php';

                    // جلب بيانات الطلب الكاملة مع معلومات أمر العمل والفرع
                    $fullRequest = $this->fetchOne(
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
                         WHERE mr.id = ?",
                        [$requestId]
                    );

                    $detailsWithMaterials = $this->fetchAll(
                        "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
                         FROM material_request_details mrd
                         JOIN materials m ON mrd.material_id = m.id
                         LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                         WHERE mrd.request_id = ?
                         ORDER BY mc.description",
                        [$requestId]
                    );

                    $emailService = new EmailService();
                    $whatsappService = new WhatsAppService();

                    // جلب إعدادات الإشعارات
                    $notifications = $this->fetchAll(
                        "SELECT * FROM notification_settings WHERE event_name = 'material_request_submit' AND is_active = 1"
                    );

                    $defaultEmailSent = false;

                    foreach ($notifications as $notification) {
                        $recipient = $notification['recipient'];
                        if ($notification['notification_type'] === 'whatsapp_personal') {
                            $whatsappService->sendMaterialRequestNotification($fullRequest, $detailsWithMaterials, $recipient, false, $wasRevisionRequested);
                        } elseif ($notification['notification_type'] === 'whatsapp_group') {
                            $whatsappService->sendMaterialRequestNotification($fullRequest, $detailsWithMaterials, $recipient, true, $wasRevisionRequested);
                        } elseif ($notification['notification_type'] === 'email' && !$defaultEmailSent) {
                            $emailService->sendMaterialRequestNotification($fullRequest, $detailsWithMaterials, $wasRevisionRequested);
                            $defaultEmailSent = true;
                        }
                    }

                    // إرسال بريد افتراضي إذا لم تكن هناك إعدادات إشعارات
                    if (!$defaultEmailSent && count($notifications) === 0) {
                        $emailService->sendMaterialRequestNotification($fullRequest, $detailsWithMaterials, $wasRevisionRequested);
                    }

                    error_log('[MaterialRequest] Notifications sent for resubmitted request: ' . $request['request_number']);
                } catch (Exception $notifEx) {
                    error_log('[MaterialRequest] فشل إرسال الإشعارات عند إعادة الإرسال: ' . $notifEx->getMessage());
                }
            }

            $actionText = $action === 'submit' ? 'وإرسال' : '';
            try {
                logActivity($_SESSION['user_id'] ?? 0, 'update_material_request', "تم تحديث {$actionText} طلب الصرف: {$request['request_number']}");
            } catch (Exception $e) {
            }

            return ['success' => true, 'request_id' => $requestId];

        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في تحديث الطلب: ' . $e->getMessage()];
        }
    }

    /**
     * خصم المواد من المخزون عند الموافقة النهائية
     */
    private function deductMaterialsFromStock($requestId)
    {
        try {
            $this->beginTransaction();

            // الحصول على تفاصيل المواد المطلوبة
            $requestDetails = $this->fetchAll(
                "SELECT mrd.*, m.current_stock, mc.description, m.item_number
                 FROM material_request_details mrd
                 JOIN materials m ON mrd.material_id = m.id
                 LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                 WHERE mrd.request_id = ?",
                [$requestId]
            );

            if (empty($requestDetails)) {
                $this->rollback();
                return ['success' => false, 'message' => 'لا توجد مواد في الطلب'];
            }

            // التحقق من توفر الكميات قبل الخصم
            $insufficientStock = [];
            foreach ($requestDetails as $detail) {
                if ($detail['current_stock'] < $detail['requested_quantity']) {
                    $insufficientStock[] = [
                        'item_number' => $detail['item_number'],
                        'description' => $detail['description'],
                        'requested' => $detail['requested_quantity'],
                        'available' => $detail['current_stock']
                    ];
                }
            }

            if (!empty($insufficientStock)) {
                $this->rollback();
                $errorMessage = "المخزون غير كافي للمواد التالية:\n";
                foreach ($insufficientStock as $item) {
                    $errorMessage .= "- {$item['item_number']}: {$item['description']} (مطلوب: {$item['requested']}, متوفر: {$item['available']})\n";
                }
                return ['success' => false, 'message' => $errorMessage];
            }

            // خصم المواد من المخزون (تحديث ذري لمنع Race Condition)
            $updateStmt = $this->db->prepare(
                "UPDATE materials SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ? AND current_stock >= ?"
            );

            foreach ($requestDetails as $detail) {
                $updateStmt->execute([
                    $detail['requested_quantity'],
                    $detail['material_id'],
                    $detail['requested_quantity']
                ]);

                if ($updateStmt->rowCount() === 0) {
                    $this->rollback();
                    return ['success' => false, 'message' => "فشل في خصم المادة: {$detail['description']} - الكمية غير كافية"];
                }
            }

            // إنشاء معاملة صادر واحدة تحتوي على جميع المواد
            $transactionResult = $this->createOutgoingTransaction($requestId, $requestDetails);
            if (!$transactionResult) {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في إنشاء معاملة الصرف'];
            }

            // تحرير الكمية المحجوزة بعد نجاح الخصم
            $this->releaseReservedStock($requestId);

            $this->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في خصم المواد من المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * إنشاء معاملة صرف واحدة تحتوي على جميع مواد الطلب
     */
    private function createOutgoingTransaction($requestId, $materialDetails)
    {
        try {
            $request = $this->findById($requestId);
            if (!$request) {
                return false;
            }

            // الحصول على branch_id من work_order
            $workOrder = $this->fetchOne(
                'SELECT branch_id FROM work_orders WHERE id = ?',
                [$request['work_order_id']]
            );

            $transactionNumber = $this->generateTransactionNumber('OUT');

            // إنشاء معاملة صرف واحدة
            $this->query(
                "INSERT INTO inventory_transactions
                    (transaction_number, transaction_type, branch_id, reference_number,
                     transaction_date, work_order_id, material_request_id, notes, status,
                     created_by, created_at, updated_at)
                 VALUES (?, 'outgoing', ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())",
                [
                    $transactionNumber,
                    $workOrder['branch_id'] ?? null,
                    $request['request_number'],
                    date('Y-m-d'),
                    $request['work_order_id'],
                    $requestId,
                    "صرف تلقائي من طلب الصرف: {$request['request_number']}",
                    $request['requested_by'],
                ]
            );

            $lastTransactionId = $this->db->lastInsertId();

            if (!$lastTransactionId) {
                return false;
            }

            // إضافة جميع المواد كتفاصيل في المعاملة الواحدة
            foreach ($materialDetails as $detail) {
                $detailResult = $this->query(
                    "INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $lastTransactionId,
                        $detail['material_id'],
                        $detail['requested_quantity'],
                        "صرف من طلب: {$request['request_number']}",
                    ]
                );

                if (!$detailResult) {
                    error_log("فشل في إضافة تفاصيل المادة {$detail['material_id']} للمعاملة {$transactionNumber}");
                    return false;
                }
            }

            return true;

        } catch (Exception $e) {
            error_log("فشل في إنشاء معاملة الصرف: " . $e->getMessage());
            return false;
        }
    }


    /**
     * إرجاع المخزون المحجوز عند رفض الطلب
     */
    private function releaseReservedStock($requestId)
    {
        try {
            // الحصول على تفاصيل الطلب
            $details = $this->fetchAll(
                "SELECT mrd.*
                 FROM material_request_details mrd
                 WHERE mrd.request_id = ?",
                [$requestId]
            );

            if (empty($details)) {
                return ['success' => true, 'message' => 'لا توجد مواد محجوزة'];
            }

            // إرجاع كل مادة محجوزة
            foreach ($details as $detail) {
                $this->query(
                    "UPDATE materials
                     SET reserved_quantity = GREATEST(0, reserved_quantity - ?)
                     WHERE id = ?",
                    [$detail['requested_quantity'], $detail['material_id']]
                );
            }

            return ['success' => true, 'message' => 'تم إرجاع المخزون المحجوز'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إرجاع المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * توليد رقم معاملة جديد
     */
    private function generateTransactionNumber($type = 'OUT')
    {
        $prefix = $type . date('Ym');
        $lastNumber = $this->fetchOne(
            "SELECT transaction_number FROM inventory_transactions
             WHERE transaction_number LIKE ?
             ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );

        if ($lastNumber && isset($lastNumber['transaction_number'])) {
            $lastSequence = (int) substr($lastNumber['transaction_number'], -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }
}
?>