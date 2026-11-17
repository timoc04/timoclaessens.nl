# Portfolio CMS — project management

A small PHP content editor for the portfolio at `timoclaessens.nl`. This is **not** the signage admin and does not share its users, cookies or passwords.

## What you can edit

Log in to `https://timoclaessens.nl/admin/` to edit the hero introduction, About text, four project titles, descriptions, destination URLs and technology tags, expertise introduction and contact introduction. The visual layout remains HTML/CSS. The homepage requests public plain-text content from `/admin/api/content.php` and replaces matching text nodes using `textContent`. If the CMS is unconfigured or unavailable, the static HTML copy remains visible; it is the original fallback, not necessarily your most recent edit.

Project cards can now be added, edited, deleted and reordered at `/admin/projects.php` (up to 20). Choose one of the four existing artwork styles. This phase **does not** upload images, edit navigation or the privacy policy. It does not update GitHub files automatically. Since the homepage remains static with JavaScript content substitution, SEO crawlers and visitors with JS disabled may see the fallback copy instead of CMS edits. Server-rendered content can be implemented later.

## Project metadata and compatibility

Each existing project card now has an editable destination URL (HTTP or HTTPS only; embedded credentials are rejected) and 1–6 comma-separated technology tags (maximum 40 bytes each). The frontend uses DOM text nodes for tags and validates link protocols before updating href attributes. It does not interpret HTML from the CMS. The static four cards in `index.html` remain a fallback. The CMS project endpoint dynamically renders ordered project cards using the four existing artwork templates; newly added projects reuse a selected style.

Existing `content.json` files from phase 1 remain compatible: missing metadata keys receive the defaults on read and are included on the next save. No database migration, new PHP dependencies or change to `admin/config.php` is needed. Back up the JSON file before deployment as usual.

## Project storage, backups and rollback

The first visit to the project manager imports the four default cards from existing CMS text fields. Nothing is written until you save, reorder, add or delete a project. On first save, the manager writes `projects.json` in the configured private `CMS_STORAGE_DIR`. Later saves create `projects.previous.json`, containing the immediately preceding version. Preserve both files during deployments. For manual rollback, back up the current file and restore `projects.previous.json` to `projects.json` in the private storage directory. This backup is **only one version deep**, not a full revision history. Old project text fields remain in `content.json` for backwards compatibility, but after project management is activated, edit project data only in `/admin/projects.php`.

Project changes publish immediately. Project deletion now requires browser confirmation. The manager also checks a project-list revision under an exclusive file lock before saving: if another tab or administrator changed projects since you loaded the page, the stale save is rejected with HTTP 409. Refresh before making a new edit; copy any unsaved text first. This prevents silent overwrites, but does not provide drafts, preview or a full revision history. There is not yet an image upload library. Visitors without JavaScript, and some crawlers, still see the original static cards.

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
php admin/tests/projects_test.php
```

The unit test uses a temporary directory outside the site and cleans up after itself. The `Portfolio CMS checks` GitHub Action runs PHP syntax checks and this test on relevant pull requests. Use a PHP-enabled local web server and a private test config for browser testing, not a static Live Server.

For future expansion: image uploads/media library, drafts with preview, revision history, concurrent editing protection and server-side rendering.

## Editing page text versus projects

The main `/admin/` editor contains only Hero, About, Expertise and Contact text. All project titles, descriptions, URLs, tags and ordering are edited exclusively at `/admin/projects.php` (Manage projects). The original project fields in private `content.json` are retained for backwards compatibility and initial project import; saving the main page does not change those legacy values. Do not delete `content.json` during deployment.
