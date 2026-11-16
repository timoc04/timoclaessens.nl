# Digital signage

PHP digital signage application hosted at [signage.timoclaessens.nl](https://signage.timoclaessens.nl). This application is separate from the static portfolio, even though both live in the same Git repository.

## Components

| Path | Purpose |
| --- | --- |
| `index.php` | Public fullscreen player, one playlist per screen |
| `admin/index.php` | Authenticated screen and media management |
| `admin/login.php` / `logout.php` | Sign-in and CSRF-protected sign-out |
| `admin/admin.css` | Shared admin/login/logout styling |
| `includes/security.php` | Sessions, CSRF, account validation and login throttling |
| `includes/settings.php` | Shared JSON settings reader and atomic writer |
| `tests/` | Security and settings regression tests |
| `config.example.php` | Template for the **private** `config.php` |
| `screens/<screen>/media/` | Publicly served images and MP4 videos |
| `screens/<screen>/settings.json` | Display duration settings; blocked from public HTTP access |
| `.htaccess` / `screens/.htaccess` | Routing and Apache access restrictions |
| `SECURITY.md` | Security controls, deployment checks and limitations |

## Configuration

Requires PHP 8+ with `fileinfo`, Apache rules enabled, HTTPS, and a writable screen directory. Copy `config.example.php` to `config.php` **on the server** and configure:

- `SIGNAGE_USERS`: usernames and password hashes, generated with PHP `password_hash()`; never store plaintext passwords.
- `APP_BASE_PATH` and `APP_BASE_URL`: match the actual signage host/document root.
- `SIGNAGE_SECURITY_DIR`: an absolute, writable directory **outside `public_html`** for shared login rate limiting. Replace the example cPanel username.
- `SCREENS_DIR`, allowed extensions and session timeout as needed.

Do **not** commit `config.php`, real hashes, private credentials or production security state. The template intentionally contains placeholders.

## Using signage

Open `/admin/` on the signage hostname and log in. Create or select a screen, upload JPG/JPEG/PNG/WebP images or MP4 video, set image durations, rename or delete files. The `main` screen cannot be deleted. Open `/<screen>` on the signage hostname for that screen's fullscreen playlist; the root uses the default screen.

Uploaded files and settings are **live data**. Back them up before deployments. The repository currently includes a legacy `screens/main/` example with a test image; do not assume it reflects production content.

## Deploy and verify

1. Back up `config.php` and all live `screens/` settings and media.
2. Merge the change into `main`, wait for relevant GitHub Actions checks, and update the cPanel Git checkout / deployment.
3. Ensure the private security directory is writable and both Apache `.htaccess` files are active.
4. Test login, logout, session timeout, media upload/rename/delete, and screen creation/deletion.
5. Confirm `/screens/main/settings.json` is inaccessible over HTTP, while media URLs and fullscreen playback work.

See [SECURITY.md](SECURITY.md) for the detailed security verification list. GitHub Actions provides PHP lint and smoke tests, **not** an end-to-end security audit.

## Development

A static HTTP server cannot execute this application. Use a PHP-capable local environment with a private test `config.php`; never point tests at production media. The admin requires session cookies and CSRF tokens, so test its workflows through a browser.

## Automated tests (Phase 2)

Run the security regression suite locally with PHP 8+:

```bash
php projects/signage/tests/security_test.php
php projects/signage/tests/settings_test.php
```

Run it from the repository root. The security suite tests password verification, invalidated sessions and the shared IP login limiter. The settings suite checks saving/loading JSON, temporary-file cleanup and rejection of malformed JSON. It creates a temporary, isolated rate-limit directory and removes it afterwards; it does not read production `config.php`, contact Junda or modify live media.

GitHub Actions runs both suites and PHP syntax checks for signage pull requests. These are unit-level checks; browser-based admin workflows and public playback still need manual verification before deployment.

## Player reliability

The player skips missing or unreadable media, limits image/video startup waits to 15 seconds, and reloads the playlist after a full cycle. An empty playlist displays a message and retries after 30 seconds, so a screen can recover when media is added later. Image durations are validated and constrained server-side. Playback stays fullscreen.

After deploying player changes, manually test: an empty screen, a missing image, an invalid video, an image with an invalid duration, and a normal image/video playlist. These browser scenarios are not covered by the PHP security tests.

## Screen settings reliability

The player and admin share `includes/settings.php`. Settings updates are written to a temporary file in the same directory and renamed into place, reducing the risk of partially written JSON. Invalid JSON is not silently overwritten: the admin stops with an error and the player falls back to default durations, logging the read error server-side. Back up and repair damaged `settings.json` files rather than deleting them blindly. Atomic replacement does not prevent lost updates from simultaneous admin requests; concurrent editing and friendlier save-error messages remain future improvements.
