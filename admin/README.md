# Portfolio CMS — project management

A small PHP content editor for the portfolio at `timoclaessens.nl`. This is **not** the signage admin and does not share its users, cookies or passwords.

## What you can edit

Log in to `https://timoclaessens.nl/admin/` to edit Hero, About, Expertise and Contact copy. Use `/admin/projects.php` for project titles, descriptions, links, tags and ordering. The visual layout remains HTML/CSS. The homepage requests public plain-text content from `/admin/api/content.php` and replaces matching text nodes using `textContent`. If the CMS is unconfigured or unavailable, the static HTML copy remains visible; it is the original fallback, not necessarily your most recent edit.

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

## Homepage copy drafts (first publishing phase)

The main `/admin/` content editor now separates **Save draft** from **Publish saved draft**. Saving creates or replaces a validated `content.draft.json` in the private `CMS_STORAGE_DIR` and does **not** modify the live `content.json` used by `/admin/api/content.php`. A saved draft is loaded back into the editor on the next visit. Publishing explicitly copies the saved draft to live content and removes the draft; discarding deletes only the draft. Unsaved form changes are **not** included when clicking Publish: save the draft first.

The editor uses a revision check to reject stale drafts from another tab. Publishing also verifies the live content revision from when the page was loaded. If a conflict occurs, reload and review the draft before publishing. Drafts are private server-side JSON and cannot be requested through the public API. They are not a preview feature or revision history. Existing `content.json` files, CMS accounts and project management are unaffected. Back up `content.draft.json` alongside live CMS files; do not delete it during cPanel deployments.

Project management at `/admin/projects.php` **still publishes immediately**. Project drafts, previews and version history are planned separately.

## Project drafts

The project manager now edits a private `projects.draft.json` file in `CMS_STORAGE_DIR`. Saving a project, adding one, deleting one, or moving one changes **only the draft**, not the public `projects.json`. Use **Publish project draft** to publish the entire saved project list or **Discard project draft** to remove unpublished changes. Draft and live revisions are checked to prevent stale tabs from overwriting changes. After publication, the existing live project backup `projects.previous.json` is still maintained. Preserve `projects.draft.json` during deployments. Project edits within different cards must each be saved before publishing; unsaved form fields are not published. This is not a public preview or revision-history feature.

### Draft preview

Use **Preview drafts** from either CMS editor to open `/admin/preview.php`. The authenticated, no-store, noindex page reuses the real homepage layout and loads unpublished content and project drafts through private, session-protected read-only endpoints. The public homepage and public APIs continue to show published content. Preview only reflects **saved** drafts, not unsaved form edits. The preview has a persistent unpublished banner. Sign in again if your CMS session expires; never share preview links as public links.


## Private CMS backup and recovery

The repository includes `scripts/backup_cms.py`, a manual, dependency-free backup utility. It reads only the CMS JSON files (`content.json`, `content.draft.json`, `projects.json`, `projects.draft.json`, `projects.previous.json`) and writes a timestamped ZIP containing the files that actually exist plus a manifest. It deliberately excludes `admin/config.php`, password hashes, login-rate-limit records and lock files. **Back up `admin/config.php` separately using a secure, access-controlled method.**

On Junda/cPanel, use Terminal or SSH (if enabled), and replace the example absolute paths with your own:

```bash
mkdir -p /home/USERNAME/portfolio-cms-backups
chmod 700 /home/USERNAME/portfolio-cms-backups
python3 scripts/backup_cms.py \
  --source /home/USERNAME/portfolio-cms-private \
  --destination /home/USERNAME/portfolio-cms-backups
```

Both paths must be private and outside `public_html`. The script refuses to save archives inside the repository directory, rejects symlinked CMS files, validates JSON and writes ZIP files with owner-only permissions. It **does not schedule backups** or copy them off-server. For real disaster recovery, download encrypted copies to separate storage and configure retention and a schedule using your hosting backup facility or a controlled cron job.

To restore, first take a fresh backup, stop CMS edits, inspect the ZIP manifest and extract only the needed JSON files into the configured private `CMS_STORAGE_DIR`. Keep file permissions private and confirm the public APIs and CMS editor afterwards. Do not extract an archive into `public_html`, and do not blindly overwrite newer published content. Test recovery on a staging copy before relying on it.
