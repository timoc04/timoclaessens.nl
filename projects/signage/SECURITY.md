# Signage security rollout (Phase 1)

This change affects the signage admin only; the public fullscreen player remains unchanged.

## Before deployment
1. Back up the live `config.php`, all `screens/` folders and media.
2. Merge this PR, wait for any GitHub workflow, and pull the new files to the **live** signage document root. Confirm the `includes/security.php` file and both `.htaccess` files were deployed.
3. **Migrate the admin password** in the untracked server-side `config.php`. Generate a hash locally with:
   `php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`
   Set `const ADMIN_PASSWORD_HASH = '...';` to the generated value and **remove** the old `ADMIN_PASSWORD` constant. Never commit the real hash, password or live `config.php`.
4. Ensure HTTPS is enabled for signage. PHP must have the `fileinfo` extension and the host must allow `.htaccess` rules (`AllowOverride`). The upload limit is 100 MB in application code; PHP `upload_max_filesize` and `post_max_size` may be lower.

## What changes
- A CSRF token is required on all admin POST forms, including login; deleting media now uses POST instead of GET.
- Sessions use HttpOnly, SameSite=Lax cookies, strict mode and a regenerated session ID on login.
- Failed login attempts are throttled **per session** (5 failures -> 15-minute lock). This is not IP-level or distributed rate limiting; configure hosting/WAF throttling as an additional protection.
- Uploads are checked against file extensions and actual MIME types, limited to 100 MB, and will not overwrite existing files.
- Requests for JSON settings and executable files under `screens/` are denied by Apache; directory listing is disabled.
- Legacy `ADMIN_PASSWORD` remains temporarily supported for migration. **Use a password hash before considering this rollout complete.**

## Manual verification after deployment
- Log in with the hashed password, log out, and confirm admin redirects work.
- Create/delete a test screen; upload an image and MP4; rename, change duration and delete media.
- Try a forged POST without `csrf_token`: expect HTTP 403. A GET with `?delete=...` must no longer delete files.
- Verify `/screens/main/settings.json` returns 403 while `/screens/main/media/test-image1.jpg` remains accessible.
- Verify `/admin/` is private, and the public fullscreen player and 301 redirects still work.
- Confirm that server configuration and uploaded media are not tracked in Git.

## Remaining security work
For stronger public-facing protection add server-side IP-based login throttling/WAF rules, centralised logging, automated tests, safer file operations and ideally non-public persistent storage for screen settings. No live penetration test has been performed as part of this PR.
