# Signage security rollout (Phase 1)

This change affects the signage admin only; the public fullscreen player remains unchanged.

## Before deployment
1. Back up the live `config.php`, all `screens/` folders and media.
2. Merge this PR, wait for any GitHub workflow, and pull the new files to the **live** signage document root. Confirm the `includes/security.php` file and both `.htaccess` files were deployed.
3. **Configure named accounts** in the untracked server-side `config.php`. This PR **replaces** the former password-only login. Generate a separate hash for each user, preferably interactively:
   `php -r '$password = readline("Password: "); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'`
   Configure the PHP constant:
   ```php
   const SIGNAGE_USERS = [
       'admin' => 'PASTE_HASH_FOR_ADMIN',
       'colleague' => 'PASTE_HASH_FOR_COLLEAGUE',
   ];
   ```
   Remove the old `ADMIN_PASSWORD` and `ADMIN_PASSWORD_HASH` constants. Keep all real hashes and usernames in private `config.php`, never in Git. Deploy the updated `config.php` **together with** the new login code, otherwise login will fail.
4. Ensure HTTPS is enabled for signage. PHP must have the `fileinfo` extension and the host must allow `.htaccess` rules (`AllowOverride`). The upload limit is 100 MB in application code; PHP `upload_max_filesize` and `post_max_size` may be lower.

## What changes
- A CSRF token is required on all admin POST forms, including login; deleting media now uses POST instead of GET.
- Sessions use HttpOnly, SameSite=Lax cookies, strict mode and a regenerated session ID on login.
- Failed login attempts are throttled **per session** (5 failures -> 15-minute lock). This is not IP-level or distributed rate limiting; configure hosting/WAF throttling as an additional protection.
- Uploads are checked against file extensions and actual MIME types, limited to 100 MB, and will not overwrite existing files.
- Requests for JSON settings and executable files under `screens/` are denied by Apache; directory listing is disabled.
- Login requires a configured username and matching password hash in `SIGNAGE_USERS`. Multiple named accounts are supported. Existing sessions are not individually revoked when an account is removed; session invalidation and audit logging are future improvements.

## Manual verification after deployment
- Log in with each configured username and its original password (not the hash), log out, and confirm admin redirects work. Verify incorrect usernames/passwords are rejected.
- Create/delete a test screen; upload an image and MP4; rename, change duration and delete media.
- Try a forged POST without `csrf_token`: expect HTTP 403. A GET with `?delete=...` must no longer delete files.
- Verify `/screens/main/settings.json` returns 403 while `/screens/main/media/test-image1.jpg` remains accessible.
- Verify `/admin/` is private, and the public fullscreen player and 301 redirects still work.
- Confirm that server configuration and uploaded media are not tracked in Git.

## Remaining security work
For stronger public-facing protection add server-side IP-based login throttling/WAF rules, centralised logging, automated tests, safer file operations and ideally non-public persistent storage for screen settings. No live penetration test has been performed as part of this PR.

## Phase 1 completion: additional hardening
- Logout now requires an authenticated POST with CSRF token. Opening `admin/logout.php` directly with GET returns HTTP 405; use the **Uitloggen** button in the admin.
- Existing sessions are rejected if their username is removed from `SIGNAGE_USERS` or their stored password hash changes. Re-authentication is required.
- Failed logins are limited across sessions by remote IP (5 failures within 15 minutes; 15-minute lockout). The original per-session limit remains. The limit uses `REMOTE_ADDR`, not user-supplied proxy headers. Shared NAT IPs may affect multiple users. For distributed/multi-server setups, use a shared rate-limiting service.
- The limiter stores only hashed IP keys and counters, not submitted passwords. It writes to a directory outside the public document root, defaulting to `sys_get_temp_dir() . '/signage-security'`. **On shared hosting, configure a private writable directory** in `config.php`, e.g. `const SIGNAGE_SECURITY_DIR = '/home/ACCOUNT/signage-private-security';` (replace ACCOUNT with your cPanel username). This directory must not be accessible over HTTP and must be writable by PHP. If it cannot be accessed, login fails closed with HTTP 503.
- Admin responses include CSP (currently permitting inline JavaScript for the existing admin UI), frame denial, no-sniff and referrer restrictions. A stricter nonce-based CSP is future work.
- File rename/delete operations now check results, reject symbolic links, and cannot change a media file's extension through rename. The admin prevents overlong image durations.

### Additional checks before merging/deploying
1. Confirm the private rate-limit directory exists or PHP can create it, and is writable by the PHP user. Never place it in `public_html` or the signage document root.
2. Check that valid logins work, and 5 incorrect attempts from the same client block further attempts even with a fresh private browser session. Wait 15 minutes to retest.
3. Remove a test account from `SIGNAGE_USERS` while logged in and verify its next admin request is redirected to login; changing the account hash must do the same.
4. Verify logout button works, direct GET to `/admin/logout.php` returns 405, and session timeout redirects to login with the expired message.
5. Confirm image/MP4 upload, renaming, deleting, creating/deleting screens and public player still work. Confirm the browser console has no CSP errors.
6. Check `/screens/main/settings.json` is forbidden (HTTP 403) while public media remains available.
7. Test `php -l` for all changed PHP files on a PHP 8+ environment. These changes have not been verified on the Junda runtime.

### Limitations
- No formal penetration test, continuous audit logging or automated integration test suite is included.
- Rate limiting uses the web server's `REMOTE_ADDR`; validate reverse-proxy setup if a proxy is later introduced.
- The PHP session is not tied to browser fingerprint or device; password change/account removal invalidates sessions on their next admin request.
