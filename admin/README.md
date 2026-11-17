# Portfolio CMS

Private PHP content management for [timoclaessens.nl](https://timoclaessens.nl). This is **not** the signage admin: accounts, sessions, configuration and storage are separate.

## Pages and responsibilities

| Path | Purpose |
| --- | --- |
| `/admin/` (`index.php`) | Edit Hero, About, Expertise and Contact text |
| `/admin/projects.php` | Add, edit, reorder and remove project cards (maximum 20) |
| `/admin/preview.php` | Authenticated preview of **saved** content and project drafts |
| `/admin/login.php`, `logout.php` | Authentication and logout |
| `/admin/api/content.php` | Public **published** text only |
| `/admin/api/projects.php` | Public **published** projects only |
| `/admin/api/preview-content.php`, `preview-projects.php` | Session-protected draft preview APIs |
| `includes/` | Field validation, authentication, revisions, JSON storage and publishing |
| `tests/` | PHP regression tests |

The public page is static HTML plus JavaScript. It uses `textContent` and safe DOM construction, not CMS-supplied HTML. If the API fails, visitors see the HTML fallback, which may be older than the published CMS content. Visitors without JavaScript and some crawlers may also see that fallback.

## How publishing works

1. Open the content editor or **Manage projects**.
2. Make changes and **Save draft**. Saving does **not** change the live website.
3. Use **Preview drafts** to inspect saved drafts in the real homepage layout.
4. Click **Publish saved draft** / **Publish project draft** to update the public content.
5. **Discard draft** removes unpublished changes without modifying published data.

Preview shows **saved** drafts, not unsaved form edits. Content and projects have separate drafts and separate publish operations. Revision checks reject stale changes from another tab/session instead of silently overwriting them; reload and review your work if a conflict occurs.

Project URLs must use HTTP or HTTPS and cannot contain embedded credentials. Tags are validated and displayed as plain text. Projects use predefined artwork styles; there is no CMS media-upload library or server-side SEO rendering.

## Junda/cPanel setup

Requirements: PHP 8.2 recommended, HTTPS, working Apache access restrictions and a writable **private** storage directory outside the document root.

1. Copy `admin/config.example.php` to **untracked** `admin/config.php` on the server.
2. Configure named accounts in `CMS_USERS` using PHP `password_hash(..., PASSWORD_DEFAULT)`; never commit plaintext passwords or hashes.
3. Configure `CMS_STORAGE_DIR` with an absolute path outside `public_html`, for example `/home/USERNAME/portfolio-cms-private`. PHP must be able to create/write it.
4. Confirm the web server denies direct access to `/admin/config.php`, `/admin/includes/` and `/admin/tests/`.
5. Log in, save a harmless draft, preview, publish, refresh the public page, and verify the result.
6. Back up the configuration and private JSON files before updates.

Do not reuse signage credentials. A static development server cannot execute the CMS.

## Private storage

The configured directory can contain:

| File | Meaning |
| --- | --- |
| `content.json` | Published portfolio text |
| `content.draft.json` | Unpublished text |
| `projects.json` | Published project list |
| `projects.draft.json` | Unpublished project changes |
| `projects.previous.json` | Previous published project list (one version) |
| `*.lock`, rate-limit files | Internal concurrency and login protection state |

Older `content.json` files may contain legacy project keys; the current editor does not expose them. Do not delete or hand-edit live files casually. The public API never serves drafts. Published project data and drafts must survive deployments.

## Security and limitations

- Named password-hash accounts, server-side sessions, idle timeout, login throttling, session regeneration, CSRF protection and POST-only logout.
- Plain-text validation and output encoding; no user-supplied HTML rendering.
- Atomic JSON writes and revision checks for draft/publish workflows.
- Private storage outside the public document root and Apache rules for sensitive source/config files.
- Public endpoints use no-store caching; preview is authenticated, noindex and no-store.

GitHub tests do **not** prove that the live cPanel environment is configured correctly. Verify HTTPS, access denial, file permissions and login flows on the deployed site. The CMS does not provide a full audit log, multi-version history, uploaded project images or server-rendered published copy.

## Backup and recovery

`scripts/backup_cms.py` creates a **manual** ZIP snapshot of available published/draft JSON plus a manifest. It excludes credentials, password hashes, rate-limit state and locks. Back up `admin/config.php` separately using secure access-controlled hosting backups.

From the repository root on the hosting account, replacing paths:

```bash
mkdir -p /home/USERNAME/portfolio-cms-backups
chmod 700 /home/USERNAME/portfolio-cms-backups
python3 scripts/backup_cms.py \
  --source /home/USERNAME/portfolio-cms-private \
  --destination /home/USERNAME/portfolio-cms-backups
```

Keep **both** directories outside `public_html`. The script rejects symlinks, validates JSON, writes private archives and refuses a backup destination within the repository. It does **not** schedule jobs or create copies outside hosting.

To recover: take a fresh backup, stop editing, inspect the ZIP manifest, restore only required JSON into `CMS_STORAGE_DIR`, check private permissions, then test the public API and editor. Prefer testing recovery on a staging copy. Do not blindly replace newer published content.

## Tests

Run from the repository root:

```bash
php admin/tests/content_test.php
php admin/tests/revisions_test.php
php admin/tests/projects_test.php
php admin/tests/drafts_test.php
php admin/tests/project_drafts_test.php
python3 scripts/test_cms_http.py
python3 scripts/test_backup_cms.py
python3 scripts/test_apache_protection.py
python3 scripts/test_public_headers.py
```

The HTTP test uses isolated temporary configuration/storage and a local PHP server, not production. GitHub runs these in `portfolio-cms.yml`. See [repository guide](../README.md) and [scripts reference](../scripts/README.md).
