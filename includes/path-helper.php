<?php

/**
 * مساعد المسارات - PathHelper
 * يوفر دوال لحل مشاكل المسارات النسبية في التطبيق
 */

class PathHelper {
    private static $instance = null;
    private $basePath = '';
    
    private function __construct() {
        $this->calculateBasePath();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function calculateBasePath() {
        // الحصول على المسار الحالي
        $currentPath = $_SERVER['SCRIPT_NAME'];
        
        // تحديد عمق المجلد الحالي من public
        $pathParts = explode('/', trim($currentPath, '/'));
        
        // البحث عن مؤشر public
        $publicIndex = array_search('public', $pathParts);
        
        if ($publicIndex !== false) {
            // حساب العمق من public
            $depth = count($pathParts) - $publicIndex - 2; // -2 for public and current file
            $this->basePath = str_repeat('../', max(0, $depth));
        } else {
            $this->basePath = './';
        }
    }
    
    public function getPath($relativePath = '') {
        return $this->basePath . $relativePath;
    }
    
    public function getAssetPath($assetPath = '') {
        return $this->basePath . 'assets/' . $assetPath;
    }
    
    public function isActivePage($page) {
        $currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
        $currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
        
        if (strpos($page, '/') !== false) {
            $pageParts = explode('/', $page);
            $pageDir = $pageParts[0];
            $pageFile = isset($pageParts[1]) ? basename($pageParts[1], '.php') : 'index';
            
            return $currentDir === $pageDir && $currentPage === $pageFile;
        }
        
        return $currentPage === $page;
    }
    
    public function createNavLink($url, $text, $icon = '', $isActive = false) {
        $activeClass = $isActive ? ' active' : '';
        $iconHtml = $icon ? "<i class=\"{$icon} me-2\"></i>" : '';
        
        return "<a href=\"{$this->getPath($url)}\" class=\"nav-link{$activeClass}\">{$iconHtml}{$text}</a>";
    }
}

// دوال مساعدة عامة - استخدام مسارات نسبية
function path($relativePath = '') {
    // إزالة الشرطة المائلة من البداية إذا وجدت
    $relativePath = ltrim($relativePath, '/');

    // استخدام PathHelper للحصول على المسار الصحيح
    return PathHelper::getInstance()->getPath($relativePath);
}

function asset($assetPath = '') {
    return PathHelper::getInstance()->getAssetPath($assetPath);
}

function isActivePage($page) {
    return PathHelper::getInstance()->isActivePage($page);
}

function activeClass($page, $class = 'active') {
    return isActivePage($page) ? $class : '';
}

function loginUrl() {
    return path('auth/login.php');
}

function homeUrl() {
    return path('dashboard.php');
}

function createNavLink($url, $text, $icon = '', $page = null) {
    $isActive = $page ? isActivePage($page) : false;
    return PathHelper::getInstance()->createNavLink($url, $text, $icon, $isActive);
}

// دالة للحصول على المسار الأساسي للتطبيق
function getBasePath() {
    static $basePath = null;
    
    if ($basePath === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $pathInfo = pathinfo($scriptName);
        $dir = $pathInfo['dirname'];
        
        // إزالة /public من المسار إذا كان موجوداً
        $basePath = str_replace('/public', '', $dir);
        
        // التأكد من أن المسار ينتهي بـ /
        if (substr($basePath, -1) !== '/') {
            $basePath .= '/';
        }
    }
    
    return $basePath;
}

// دالة للحصول على URL الكامل
function fullUrl($relativePath = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = getBasePath();
    
    return $protocol . '://' . $host . $basePath . $relativePath;
}

// دالة للتحقق من وجود الملف
function fileExists($relativePath) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . getBasePath() . $relativePath;
    return file_exists($fullPath);
}

// دالة لإنشاء رابط مع معاملات GET
function urlWithParams($relativePath, $params = []) {
    $url = path($relativePath);
    
    if (!empty($params)) {
        $queryString = http_build_query($params);
        $url .= (strpos($url, '?') !== false ? '&' : '?') . $queryString;
    }
    
    return $url;
}

// دالة للحصول على المسار النسبي من URL
function getRelativePathFromUrl($url) {
    $basePath = getBasePath();
    
    if (strpos($url, $basePath) === 0) {
        return substr($url, strlen($basePath));
    }
    
    return $url;
}

// دالة لتنظيف المسار
function cleanPath($path) {
    // إزالة الشرطات المائلة المتعددة
    $path = preg_replace('#/+#', '/', $path);
    
    // إزالة الشرطة المائلة من البداية إذا كانت موجودة
    $path = ltrim($path, '/');
    
    return $path;
}

// دالة للحصول على مسار المجلد الحالي
function getCurrentDirectory() {
    return basename(dirname($_SERVER['SCRIPT_NAME']));
}

// دالة للحصول على اسم الملف الحالي
function getCurrentFile() {
    return basename($_SERVER['SCRIPT_NAME'], '.php');
}

// دالة للتحقق من المسار الحالي
function isCurrentPath($path) {
    $currentPath = $_SERVER['REQUEST_URI'];
    $checkPath = path($path);
    
    return strpos($currentPath, $checkPath) !== false;
}
?>
