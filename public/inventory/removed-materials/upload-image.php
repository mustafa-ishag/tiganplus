<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التأكد من الصلاحيات
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'لم يتم إرسال ملف أو حدث خطأ في الرفع']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'نوع الملف غير مسموح به']);
    exit;
}

// تحديد مسار الرفع
$uploadDir = __DIR__ . '/../../uploads/removed_materials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// توليد اسم فريد
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('rm_') . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // المسار النسبي للحفظ في قاعدة البيانات
    $relativePath = '/uploads/removed_materials/' . $filename;
    
    echo json_encode([
        'success' => true, 
        'url' => path('uploads/removed_materials/' . $filename),
        'path' => $relativePath
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء حفظ الملف']);
}
