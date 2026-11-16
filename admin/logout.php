<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
cms_start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
cms_check_csrf();
cms_logout();
header('Location: /admin/login.php', true, 303);
exit;
