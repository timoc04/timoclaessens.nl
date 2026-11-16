<?php

// Generate with: php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
const ADMIN_PASSWORD_HASH = 'REPLACE_WITH_PASSWORD_HASH';
// Legacy ADMIN_PASSWORD is temporarily supported for migration, but should be removed.

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