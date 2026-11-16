<?php
declare(strict_types=1);

// Isolated unit tests: no production credentials, web calls or site media.
$directory = sys_get_temp_dir() . '/portfolio-cms-test-' . bin2hex(random_bytes(6));
define('CMS_STORAGE_DIR', $directory);
require_once dirname(__DIR__) . '/includes/content.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

try {
    $defaults = cms_read_content();
    check(count($defaults) === 14, 'Expected fourteen editable fields.');
    check($defaults['project_1_title'] === 'AWS Infrastructure & Automation', 'Unexpected project default.');

    $modified = $defaults;
    $modified['hero_intro'] = 'Hello from the CMS test';
    $modified['project_1_title'] = 'Updated project';
    cms_save_content($modified);
    $loaded = cms_read_content();
    check($loaded['hero_intro'] === 'Hello from the CMS test', 'Hero change was not saved.');
    check($loaded['project_1_title'] === 'Updated project', 'Project change was not saved.');
    check(is_file($directory . '/content.json'), 'Settings file missing.');

    $bad = $modified;
    $bad['hero_intro'] = '<' . str_repeat('x', 700);
    try {
        cms_save_content($bad);
        throw new RuntimeException('Oversized value was incorrectly accepted.');
    } catch (InvalidArgumentException $expected) {}
    check(cms_read_content() === $loaded, 'Invalid input overwrote stored data.');

    file_put_contents($directory . '/content.json', '{broken');
    try {
        cms_read_content();
        throw new RuntimeException('Malformed JSON was incorrectly accepted.');
    } catch (RuntimeException $expected) {
        check(str_contains($expected->getMessage(), 'Invalid CMS content JSON'), 'Wrong error for malformed JSON.');
    }
    echo "CMS content tests passed.\n";
} finally {
    if (is_dir($directory)) {
        foreach (scandir($directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') unlink($directory . '/' . $file);
        }
        rmdir($directory);
    }
}
