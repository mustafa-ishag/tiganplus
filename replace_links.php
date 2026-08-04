<?php
$files = [
    'public/productivity/work-items/view.php',
    'public/productivity/work-items/index.php',
    'public/productivity/work-items/edit.php',
    'public/productivity/work-items/create.php',
    'public/productivity/daily-logs/index.php',
    'public/productivity/daily-logs/create.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $content = str_replace("path('productivity/work-orders/index.php')", "path('work-orders/index.php')", $content);
        file_put_contents($path, $content);
        echo "Updated $file\n";
    }
}
