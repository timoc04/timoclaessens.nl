# Portfolio CMS — phase 1

A small PHP content editor for the portfolio at `timoclaessens.nl`. This is **not** the signage admin and does not share its users, cookies or passwords.

## What you can edit

Log in to `https://timoclaessens.nl/admin/` to edit the hero introduction, About text, four project titles/descriptions, expertise introduction and contact introduction. The visual layout remains HTML/CSS. The homepage requests public plain-text content from `/admin/api/content.php` and replaces matching text nodes using `textContent`. If the CMS is unconfigured or unavailable, the static HTML copy remains visible; it is the original fallback, not necessarily your most recent edit.

This first phase **does not** edit images, navigation, layout, project count/order, project links, tags or the privacy policy. It does not update GitHub files automatically. Since the homepage remains static with JavaScript content substitution, SEO crawlers and visitors with JS disabled may see the fallback copy instead of CMS edits. Server-rendered content can be implemented later.

## Configure on Junda/cPanel (required before login)

1. Confirm PHP 8+ and HTTPS work on the portfolio hostname.
2. Copy `admin/config.example.php` to `admin/config.php` **on the server** (the real file is Git-ignored).
3. Generate a password hash with PHP `password_hash(..., PASSWORD_DEFAULT)` and configure your chosen username in `CMS_USERS`. Do not reuse or commit secrets from signage.
4. Set `CMS_STORAGE_DIR` to an absolute, PHP-writable directory **outside public_html/document root**, e.g. `/home/YOUR_CPANEL_USERNAME/portfolio-cms-private`. Replace the placeholder with your actual account path. The CMS creates this directory if permitted and stores `content.json`, `content.lock` and IP login-rate-limit files there.
5. Visit `/admin/login.php`, log in and save a harmless test edit. Refresh the homepage to check it appears.
6. Confirm `/admin/config.php` returns 403 and `/admin/includes/auth.php` is blocked from public HTTP access. If `.htaccess` protection is disabled, fix Apache configuration first.

**Back up the private storage directory and `admin/config.php` before deployment**. They are not tracked by Git and will not be recovered by pulling GitHub. Deployments must preserve them. Do not delete the server filesystem before pulling updates.

## Security and operational notes

- Named accounts with PHP password hashes, login rate limiting per server-reported client IP (5 failed attempts / 15-minute window), HttpOnly/SameSite session cookies, 30-minute idle timeout, session regeneration on login, CSRF tokens, POST-only logout and basic CSP/response headers.
- Content is plain text: encoded when shown in the editor and inserted with `textContent` on the public website. No HTML is rendered from editable fields.
- Server-side field validation and atomic JSON replacement reduce the risk of broken files. Reads reject malformed JSON; make backups. Concurrent edits are last-write-wins (no version conflict handling yet).
- CMS requests do not touch or authenticate to `signage.timoclaessens.nl`.
- The public content endpoint has `no-store` caching. The static website remains accessible even if PHP stops serving the CMS endpoint.
- **Production verification remains required**; the GitHub PHP checks are not an end-to-end security audit.

## Development and tests

Run locally from the repository root with PHP 8+:

```bash
php admin/tests/content_test.php
```

The unit test uses a temporary directory outside the site and cleans up after itself. The `Portfolio CMS checks` GitHub Action runs PHP syntax checks and this test on relevant pull requests. Use a PHP-enabled local web server and a private test config for browser testing, not a static Live Server.

For future expansion: images/media, editable project links/tags, content previews, revision history and proper server-side rendering.
