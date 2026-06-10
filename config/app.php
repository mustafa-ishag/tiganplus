<?php

declare(strict_types=1);

return [
    'name' => 'نظام تقان لإدارة أعمال المقاولات',
    'version' => '1.0.0',
    'timezone' => 'Asia/Riyadh',
    'locale' => 'ar',
    'charset' => 'UTF-8',
    
    'debug' => $_ENV['APP_DEBUG'] ?? false,
    'env' => $_ENV['APP_ENV'] ?? 'production',
    
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    
    'session' => [
        'name' => 'etgan_session',
        'lifetime' => 120, // minutes
        'path' => '/',
        'domain' => null,
        'secure' => $_ENV['SESSION_SECURE'] ?? false,
        'httponly' => true,
        'samesite' => 'Lax'
    ],
    
    'security' => [
        'password_min_length' => 6,
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'remember_token_lifetime' => 30 * 24 * 60, // 30 days in minutes
    ],
    
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'],
        'upload_path' => 'uploads/',
    ],
    
    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ]
];
