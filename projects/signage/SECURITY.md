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
