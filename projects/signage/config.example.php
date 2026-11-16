<?php

// Generate a hash for EACH user locally with:
// php -r '$password = readline("Password: "); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
// Copy the hashes below into your private server-side config.php; never commit real credentials.
const SIGNAGE_USERS = [
    'admin' => 'REPLACE_WITH_HASH_FOR_ADMIN',
    // 'colleague' => 'REPLACE_WITH_HASH_FOR_COLLEAGUE',
];

/*
|--------------------------------------------------------------------------
| Signage URL settings
|--------------------------------------------------------------------------
|
| Voor signage.timoclaessens.nl:
| APP_BASE_PATH = ''
| APP_BASE_URL = 'https://signage.timoclaessens.nl'
|
| Voor timoclaessens.nl/projects/signage:
| APP_BASE_PATH = '/projects/signage'
| APP_BASE_URL = 'https://timoclaessens.nl'
|
*/

const APP_BASE_PATH = '';
const APP_BASE_URL = 'https://signage.timoclaessens.nl';

const SCREENS_DIR = __DIR__ . '/screens';
const SCREENS_URL = APP_BASE_PATH . '/screens';

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'mp4'];

const IMAGE_DEFAULT_DURATION = 10;

const SESSION_TIMEOUT = 1800;

// Writable directory OUTSIDE public_html for login rate limiting.
// Replace JOUW_CPANEL_GEBRUIKERSNAAM with your actual cPanel username.
const SIGNAGE_SECURITY_DIR = '/home/JOUW_CPANEL_GEBRUIKERSNAAM/signage-private-security';

function app_url(string $path = ''): string
{
    $base = rtrim(APP_BASE_PATH, '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function app_full_url(string $path = ''): string
{
    return rtrim(APP_BASE_URL, '/') . app_url($path);
}