<?php
/**
 * خدمة إرسال إشعارات الواتساب
 * WhatsApp Notification Service
 */

class WhatsAppService
{
    // رابط خادم الـ Node.js على سيرفر Hostinger VPS
    private string $apiUrl = 'http://92.113.31.147:3000/send-message';

    /**
     * إرسال رسالة واتساب
     */
    public function sendMessage(string $phoneNumber, string $message, bool $isGroup = false): bool
    {
        $data = [
            'number' => $phoneNumber,
            'message' => $message,
            'isGroup' => $isGroup
        ];

        $payload = json_encode($data);

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // مهلة 10 ثوانٍ
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return true;
        }

        error_log("[WhatsAppService] Failed to send message to $phoneNumber. HTTP Code: $httpCode. Response: $result");
        return false;
    }

    /**
     * إرسال إشعار بإنشاء أمر عمل جديد
     */
    public function sendWorkOrderNotification(array $workOrder, string $recipientNumber, bool $isGroup = false): bool
    {
        $message = $this->buildWorkOrderTemplate($workOrder);
        return $this->sendMessage($recipientNumber, $message, $isGroup);
    }

    /**
     * بناء قالب رسالة الواتساب لأمر العمل
     */
    private function buildWorkOrderTemplate(array $workOrder): string
    {
        $departmentName = $workOrder['department'] === 'connections' ? 'التوصيلات' : 'المشاريع';
        $location = !empty($workOrder['location']) ? $workOrder['location'] : 'غير محدد';
        $notes = !empty($workOrder['notes']) ? $workOrder['notes'] : 'لا توجد ملاحظات';
        
        $message = "🛠️ *إشعار: أمر عمل جديد* 🛠️\n\n";
        $message .= "تم إنشاء أمر عمل جديد في النظام التابع لقسم (*{$departmentName}*).\n\n";
        
        $message .= "📋 *تفاصيل أمر العمل:*\n";
        $message .= "▪️ رقم أمر العمل: {$workOrder['work_order_number']}\n";
        $message .= "▪️ الموقع: {$location}\n";
        $message .= "▪️ القيمة المقدرة: " . number_format($workOrder['estimated_value'], 2) . " ريال\n";
        
        if (!empty($workOrder['assignment_date'])) {
            $message .= "▪️ تاريخ التكليف: {$workOrder['assignment_date']}\n";
        }
        
        $message .= "\n📝 *الملاحظات:*\n{$notes}\n\n";
        $message .= "🌐 يمكنك الدخول للنظام لمشاهدة التفاصيل وإدارة أمر العمل.";
        
        return $message;
    }

    /**
     * إرسال إشعار بطلب صرف مواد (للمدير/المستودع/القروب)
     * @param bool $isResubmission إذا كان الطلب معاد إرساله بعد تعديل
     */
    public function sendMaterialRequestNotification(array $request, array $details, string $recipientNumber, bool $isGroup = false, bool $isResubmission = false): bool
    {
        $message = $this->buildMaterialRequestTemplate($request, $details, $isResubmission);
        return $this->sendMessage($recipientNumber, $message, $isGroup);
    }

    /**
     * إرسال إشعار باعتماد طلب الصرف (لمقدم الطلب)
     */
    public function sendMaterialRequestApprovalNotification(array $request, array $details, string $recipientNumber, string $levelName): bool
    {
        $requestNumber = $request['request_number'] ?? 'N/A';
        $workOrderNum  = $request['work_order_number'] ?? '-';
        $requesterName = $request['requested_by_name'] ?? 'مقدم الطلب';

        $message = "✅ *تم اعتماد طلبك بنجاح!*\n\n";
        $message .= "مرحباً *$requesterName*،\n";
        $message .= "نود إعلامك بأنه قد تمت الموافقة على طلب الصرف الخاص بك.\n\n";
        $message .= "🔖 *رقم الطلب:* $requestNumber\n";
        $message .= "⚙️ *أمر العمل:* $workOrderNum\n";
        $message .= "🏷️ *مرحلة الاعتماد:* $levelName\n\n";
        $message .= "_(هذه رسالة آلية من نظام تِقان)_";

        return $this->sendMessage($recipientNumber, $message);
    }

    /**
     * إرسال إشعار بطلب تعديل لمقدم الطلب
     */
    public function sendRevisionRequestNotification(array $request, string $recipientNumber, string $approverName, string $notes): bool
    {
        $requestNumber = $request['request_number'] ?? 'N/A';
        $workOrderNum  = $request['work_order_number'] ?? '-';
        $requesterName = $request['requested_by_name'] ?? 'مقدم الطلب';

        $message = "🔄 *مطلوب تعديل على طلب الصرف* 🔄\n\n";
        $message .= "مرحباً *$requesterName*،\n";
        $message .= "تم طلب تعديل على طلب الصرف الخاص بك من قبل المعتمد.\n\n";
        $message .= "🔖 *رقم الطلب:* $requestNumber\n";
        $message .= "⚙️ *أمر العمل:* $workOrderNum\n";
        $message .= "👤 *طالب التعديل:* $approverName\n\n";
        $message .= "📝 *ملاحظات التعديل المطلوب:*\n$notes\n\n";
        $message .= "يرجى الدخول للنظام لتعديل الطلب وإعادة إرساله.\n";
        $message .= "🌐 *نظام تِقان - Etgan ERP*";

        return $this->sendMessage($recipientNumber, $message);
    }

    /**
     * بناء قالب الواتساب لطلب الصرف
     */
    private function buildMaterialRequestTemplate(array $request, array $details, bool $isResubmission = false): string
    {
        $requestNumber  = $request['request_number'] ?? 'N/A';
        $requestDate    = isset($request['request_date']) ? date('d/m/Y', strtotime($request['request_date'])) : date('d/m/Y');
        $workOrderNum   = $request['work_order_number'] ?? '-';
        $requesterName  = $request['requested_by_name'] ?? '-';
        $branchName     = $request['branch_name'] ?? '-';
        $notes          = $request['notes'] ?? '';

        // ترويسة الرسالة حسب نوع الإرسال
        if ($isResubmission) {
            $msg = "🔄 *تم تعديل وإعادة إرسال طلب صرف* 🔄\n";
            $msg .= "تم تعديل طلب الصرف رقم *$requestNumber* وإعادة إرساله للمراجعة.\n\n";
        } else {
            $msg = "📦 *طلب صرف مواد جديد* 📦\n";
            $msg .= "تم استلام طلب صرف جديد ويستوجب مراجعتك.\n\n";
        }

        // معلومات الطلب
        $msg .= "📄 *رقم الطلب:* $requestNumber\n";
        $msg .= "📅 *تاريخ الطلب:* $requestDate\n";
        $msg .= "👤 *مقدم الطلب:* $requesterName\n";
        $msg .= "🏢 *الفرع:* $branchName\n";
        $msg .= "⚙️ *أمر العمل:* $workOrderNum\n";
        
        if (!empty($notes)) {
            $msg .= "📝 *ملاحظات:* $notes\n";
        }

        $msg .= "\n📋 *الـمـــــواد المطلوبة:*\n";
        $msg .= "====================\n";

        // تفاصيل المواد
        $numbers = ['1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟'];
        
        foreach ($details as $i => $item) {
            $icon = isset($numbers[$i]) ? $numbers[$i] : '🔸';
            $itemNum = $item['item_number'] ?? '-';
            $desc    = $item['description'] ?? '-';
            $qty     = (float)($item['requested_quantity'] ?? 0);
            $unit    = $item['unit'] ?? '-';
            $stock   = (float)($item['current_stock'] ?? 0);
            
            // تحذير إذا كان المخزون أقل من المطلوب
            $stockAlert = ($stock < $qty) ? " ⚠️ *(غير كافٍ)*" : " ✅";

            $msg .= "$icon *رقم البند:* $itemNum\n";
            $msg .= "🔹 *الوصف:* $desc\n";
            $msg .= "🔹 *الكمية المطلوبة:* $qty $unit\n";
            $msg .= "🔹 *المخزون الحالي:* $stock $unit $stockAlert\n";
            $msg .= "--------------------\n";
        }

        $msg .= "\nيرجى الدخول للنظام لمراجعة الطلب واعتماده.\n";
        $msg .= "🌐 *نظام تِقان - Etgan ERP*";

        return $msg;
    }
}
?>
