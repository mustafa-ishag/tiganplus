<?php
/**
 * تكوين مركزي لحالات طلبات الصرف
 */

// تسميات الحالات للعرض
function getMaterialRequestStatusLabels() {
    return [
        'draft' => ['مسودة', 'secondary'],
        'submitted' => ['مرسل', 'info'],
        'warehouse_approved' => ['موافقة المستودع', 'primary'],
        'approved' => ['معتمد نهائياً', 'success'],
        'project_approved' => ['معتمد نهائياً', 'success'], // للتوافق مع البيانات القديمة
        'branch_approved' => ['معتمد نهائياً', 'success'], // للتوافق مع البيانات القديمة
        'rejected' => ['مرفوض', 'danger']
    ];
}

// تسميات الحالات للتصدير (نص فقط)
function getMaterialRequestStatusLabelsText() {
    return [
        'draft' => 'مسودة',
        'submitted' => 'مرسل',
        'warehouse_approved' => 'موافقة المستودع',
        'approved' => 'معتمد نهائياً',
        'project_approved' => 'معتمد نهائياً',
        'branch_approved' => 'معتمد نهائياً',
        'rejected' => 'مرفوض'
    ];
}

// دالة للحصول على معلومات الحالة
function getMaterialRequestStatusInfo($status) {
    $labels = getMaterialRequestStatusLabels();
    return $labels[$status] ?? ['غير معروف', 'secondary'];
}

// دالة للحصول على نص الحالة
function getMaterialRequestStatusText($status) {
    $labels = getMaterialRequestStatusLabelsText();
    return $labels[$status] ?? 'غير معروف';
}
?>