<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/drafts.php';
cms_start_session();
if (!cms_authenticated()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    exit('{"error":"Authentication required"}');
}
$_SESSION['cms_last_activity'] = time();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('{"error":"Method not allowed"}');
}
try {
    echo json_encode(cms_read_content_draft() ?? cms_read_content(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('CMS preview error: ' . $exception->getMessage());
    http_response_code(503);
    echo '{"error":"Preview unavailable"}';
}
