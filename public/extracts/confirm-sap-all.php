<?php
/**
 * صفحة تأكيد تحديث SAP الشامل
 * Confirm Unified SAP Update
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['sap_all_preview_data'])) {
    header('Location: update-sap-all.php');
    exit();
}

$previewData = $_SESSION['sap_all_preview_data'];

try {
    $db = getDB();
    $db->beginTransaction();

    $partialCount = 0;
    $finalRegularCount = 0;
    $finalForPartialCount = 0;

    // تحديث المستخلصات الجزئية
    if (!empty($previewData['partial_records'])) {
        $stmt = $db->prepare("UPDATE partial_extracts SET entry_sheet_number = ?, po_number = ?, disbursement_date = ? WHERE id = ?");
        foreach ($previewData['partial_records'] as $record) {
            $stmt->execute([
                $record['entry_sheet_number'],
                $record['po_number'],
                $record['disbursement_date'],
                $record['extract_id']
            ]);
            $partialCount++;
        }
    }

    // تحديث المستخلصات النهائية العادية
    if (!empty($previewData['final_regular_records'])) {
        $stmt = $db->prepare("UPDATE final_regular_extracts SET entry_sheet_number = ?, po_number = ?, disbursed_date = ? WHERE id = ?");
        foreach ($previewData['final_regular_records'] as $record) {
            $stmt->execute([
                $record['entry_sheet_number'],
                $record['po_number'],
                $record['disbursed_date'],
                $record['extract_id']
            ]);
            $finalRegularCount++;
        }
    }

    // تحديث المستخلصات النهائية للجزئية
    if (!empty($previewData['final_for_partial_records'])) {
        $stmt = $db->prepare("UPDATE final_for_partial_extracts SET entry_sheet_number = ?, po_number = ?, disbursement_date = ? WHERE id = ?");
        foreach ($previewData['final_for_partial_records'] as $record) {
            $stmt->execute([
                $record['entry_sheet_number'],
                $record['po_number'],
                $record['disbursement_date'],
                $record['extract_id']
            ]);
            $finalForPartialCount++;
        }
    }

    $db->commit();

    $_SESSION['sap_all_update_result'] = [
        'success' => true,
        'total_updated' => $partialCount + $finalRegularCount + $finalForPartialCount,
        'partial_count' => $partialCount,
        'final_regular_count' => $finalRegularCount,
        'final_for_partial_count' => $finalForPartialCount,
        'errors' => $previewData['errors'],
        'skipped_count' => $previewData['skipped_count']
    ];

    unset($_SESSION['sap_all_preview_data']);

    header('Location: update-sap-all.php?success=1');
    exit();

} catch (Exception $e) {
    $db->rollBack();

    $_SESSION['sap_all_update_result'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];

    header('Location: update-sap-all.php?error=1');
    exit();
}
