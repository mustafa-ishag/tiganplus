<?php
/**
 * upload.php
 * ==========
 * معالجة رفع الملفات والصور إلى مجلد محلي
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['url' => null, 'error' => "$errstr in $errfile on line $errline"]);
    exit;
});
set_exception_handler(function($e) {
    echo json_encode(['url' => null, 'error' => $e->getMessage()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['url' => null, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['url' => null, 'error' => 'No file uploaded or upload error']);
    exit;
}

$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$file = $_FILES['file'];
$fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = time() . '_' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 10) . '.' . $fileExt;
$targetPath = $uploadDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // إرجاع الرابط كاملاً لكي تتعرف عليه الواجهة الأمامية كصورة خارجية
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
    $publicUrl = $protocol . $host . $baseDir . '/uploads/' . $fileName;
    
    echo json_encode(['url' => $publicUrl, 'error' => null]);
} else {
    echo json_encode(['url' => null, 'error' => 'Failed to move uploaded file']);
}
