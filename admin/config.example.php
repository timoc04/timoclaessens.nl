<?php
/**
 * Copy to admin/config.php on the Junda server and replace both placeholders.
 * Never commit admin/config.php, a real password hash, or CMS storage files.
 *
 * Generate a hash with PHP password_hash($password, PASSWORD_DEFAULT).
 */
const CMS_USERS = [
    'admin' => 'REPLACE_WITH_A_REAL_PASSWORD_HASH',
];

// Absolute path to a writable directory OUTSIDE public_html and all web roots.
const CMS_STORAGE_DIR = '/home/YOUR_CPANEL_USERNAME/portfolio-cms-private';
