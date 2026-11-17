<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
cms_start_session();
cms_require_login();

// Reuse the real homepage markup and scripts. Only authenticated preview API
// endpoints supply data; the public API and published files stay unchanged.
$homepage = dirname(__DIR__) . '/index.html';
$html = file_get_contents($homepage);
if ($html === false) {
    http_response_code(503);
    exit('Preview is temporarily unavailable.');
}
$html = preg_replace('/<html\\s+lang="en">/', '<html lang="en" data-cms-preview="true">', $html, 1);
if (!is_string($html)) {
    http_response_code(503);
    exit('Preview is temporarily unavailable.');
}
$html = str_replace('<head>', '<head><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="/admin/preview.css?v=1">', $html);
$html = str_replace('</body>', '<script defer src="/admin/preview.js?v=1"></script></body>', $html);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
echo $html;
