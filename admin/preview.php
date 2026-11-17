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
// Explicit extension points keep preview rendering independent of incidental
// HTML formatting. Fail closed if the homepage contract changes.
$replacements = [
    'data-cms-preview="false"' => 'data-cms-preview="true"',
    '<!-- CMS_PREVIEW_HEAD -->' => '<meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="/admin/preview.css?v=1">',
    '<!-- CMS_PREVIEW_BODY -->' => '<script defer src="/admin/preview.js?v=1"></script>',
];
foreach ($replacements as $marker => $replacement) {
    if (substr_count($html, $marker) !== 1) {
        error_log('Portfolio CMS preview marker missing or duplicated: ' . $marker);
        http_response_code(503);
        exit('Preview is temporarily unavailable.');
    }
    $html = str_replace($marker, $replacement, $html);
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
echo $html;
