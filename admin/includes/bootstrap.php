<?php
declare(strict_types=1);

/** Load the private CMS configuration, or fail closed until configured. */
$config = dirname(__DIR__) . '/config.php';
if (!is_file($config)) {
    http_response_code(503);
    exit('CMS is not configured. See admin/README.md.');
}
require_once $config;
if (!defined('CMS_USERS') || !is_array(CMS_USERS) ||
    !defined('CMS_STORAGE_DIR') || !is_string(CMS_STORAGE_DIR) ||
    CMS_STORAGE_DIR === '' || !str_starts_with(CMS_STORAGE_DIR, '/')) {
    http_response_code(503);
    exit('CMS configuration is invalid.');
}
