<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/settings.php';
function check_settings(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
$dir = sys_get_temp_dir() . '/signage-settings-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$file = $dir . '/settings.json';
try {
    check_settings(signage_load_settings($file) === [], 'missing settings');
    signage_save_settings($file, ['photo.jpg' => ['duration' => 12]]);
    check_settings(signage_load_settings($file)['photo.jpg']['duration'] === 12, 'round trip');
    check_settings(count(glob($dir . '/.settings-*') ?: []) === 0, 'no leftover temporary files');
    file_put_contents($file, '{bad json');
    $thrown = false;
    try { signage_load_settings($file); } catch (RuntimeException $e) { $thrown = true; }
    check_settings($thrown, 'malformed settings rejected');
    check_settings(file_get_contents($file) === '{bad json', 'malformed settings preserved');
} finally {
    foreach (glob($dir . '/*') ?: [] as $path) unlink($path);
    rmdir($dir);
}
