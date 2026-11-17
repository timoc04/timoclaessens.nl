# TimoClaessens.nl

Source for [timoclaessens.nl](https://timoclaessens.nl) and the separately hosted [digital signage application](https://signage.timoclaessens.nl).

The portfolio is a static HTML/CSS/JavaScript site with an optional PHP CMS. Signage is a separate PHP application in the same repository. Production hosting is on Junda/cPanel; the two applications have different hostnames, configuration and authentication.

## Where to find things

| Path | Responsibility |
| --- | --- |
| `index.html` | Public portfolio homepage and static fallback content |
| `assets/css/style.css` | Shared portfolio design, responsive styles and accessibility |
| `assets/js/main.js` | Navigation and published CMS text integration |
| `assets/js/projects.js` | Public project-card rendering |
| `assets/icons/` | Shared SVG logo and generated home-screen icons |
| `privacy/` | Privacy page and page-specific styles |
| `404.html`, `404.css`, `.htaccess` | Custom error page and Apache rules |
| `admin/` | Private portfolio CMS: content, project drafts and publishing |
| `projects/signage/` | Independent fullscreen signage player and admin |
| `scripts/` | Asset versioning, backups, Lighthouse summaries and regression tests |
| `.github/workflows/` | CI, icon/cache-version updates and on-demand Lighthouse audit |
| `robots.txt`, `sitemap.xml`, `site.webmanifest` | Discovery and installation metadata |

Read the component-specific guides before making changes:

- [Portfolio CMS: setup, drafts, security and backups](admin/README.md)
- [Digital signage: setup, player and operations](projects/signage/README.md)
- [Signage security details](projects/signage/SECURITY.md)
- [Scripts and local test commands](scripts/README.md)
- [GitHub Actions workflow reference](.github/workflows/README.md)

## Editing the website

**Content:** edit Hero, About, Expertise and Contact through `/admin/` on the live site. Edit project cards, URLs, tags and ordering through `/admin/projects.php`. Both editors support saved drafts, explicit publication, discard and a private preview of **saved** drafts. Changes in the CMS are stored as private JSON on the server; **they are not committed to GitHub**.

**Design and structure:** change `index.html`, `assets/css/style.css` and `assets/js/` in Git. The static text and four project cards in `index.html` are fallbacks if the CMS API or JavaScript is unavailable. Edit `privacy/index.html` and `privacy/privacy.css` for the privacy page. Update privacy wording if data practices change.

**Icons:** edit `assets/icons/favicon.svg`. The `cache-busting.yml` workflow regenerates PNG home-screen icons and updates versioned asset references after relevant pushes to `main`.

**Signage:** all source code is in `projects/signage/`, but the live player runs on `signage.timoclaessens.nl`. The portfolio's `/projects/signage/` route redirects to that hostname. Never assume the repository directory is a separate deployment environment.

## Development and tests

Requirements: Python 3, Node.js for JavaScript syntax checks, and PHP 8.2 for PHP tests. A static local server cannot execute the CMS or signage PHP.

For a **static-only** preview, from the repository root:

```bash
python3 -m http.server 8000
```

Open `http://localhost:8000`. CMS APIs will not work with this server; the portfolio's HTML fallback remains visible.

Run the main local checks:

```bash
python3 scripts/test_site.py
python3 scripts/test_asset_cache.py
python3 scripts/test_cms_http.py
python3 scripts/test_backup_cms.py
python3 scripts/test_apache_protection.py
python3 scripts/test_public_headers.py
node --check assets/js/main.js
node --check assets/js/projects.js
php admin/tests/content_test.php
php admin/tests/revisions_test.php
php admin/tests/projects_test.php
php admin/tests/drafts_test.php
php admin/tests/project_drafts_test.php
php projects/signage/tests/security_test.php
php projects/signage/tests/settings_test.php
```

The HTTP integration test starts an isolated local PHP server and uses test-only configuration. See [scripts/README.md](scripts/README.md) for details. GitHub Actions runs the appropriate subsets automatically on relevant changes.

## Git and deployment

1. Create a feature branch from `main`; open a pull request.
2. Review the diff and wait for the applicable GitHub Actions checks.
3. Merge into `main`. If asset files changed, wait for **Update asset cache versions**; it may create a further commit on `main`.
4. Confirm the latest `main` is deployed to the correct Junda/cPanel document root. Verify the deployed commit and live pages.
5. Smoke-test the portfolio, privacy page, CMS login/draft/publish and signage player/admin where relevant.

**Do not assume a green CI result means production has been deployed.** The workflows in this repository check and prepare code; deployment must be verified in cPanel. Check the hosting configuration before changing document roots or Apache rules.

### Production data that must survive deployment

- `admin/config.php`: untracked CMS credentials and settings.
- `CMS_STORAGE_DIR`: private published/draft JSON, project backup, locks and rate-limit state; configure **outside** the public document root.
- `projects/signage/config.php`: untracked signage credentials and paths.
- Signage `screens/` settings and uploaded media: persistent live data, not disposable Git assets.
- Signage private security/rate-limit directory.

Never commit secrets, upload production JSON to the repository or overwrite live media with example files. Back up production state before changing storage or deploying signage. The [CMS backup script](admin/README.md#private-cms-backup-and-recovery) creates manual private snapshots on the hosting account; scheduling and off-host copies are **not** configured by that script.

## Quality and monitoring

The homepage has canonical metadata, a sitemap and a custom 404 page. The **Live Lighthouse audit** workflow can be started manually under GitHub Actions and tests the **live homepage** on mobile and desktop. Its lab scores are not a guarantee of accessibility or performance for every user and do not test CMS/signage workflows. See [.github/workflows/README.md](.github/workflows/README.md).

## Release checklist

- [ ] Relevant CI checks pass and the correct commit is deployed.
- [ ] Home, privacy, mobile navigation, links, icons and 404 response work.
- [ ] CMS login, saved drafts, preview, publish and discard work; public APIs show published data only.
- [ ] CMS storage and credentials remain private and unchanged.
- [ ] Signage screens play fullscreen; admin media and duration operations work.
- [ ] Live signage settings, uploads and private configuration are preserved.
- [ ] HTTPS, Apache access restrictions and backups are verified on Junda.

## Contact

[Website](https://timoclaessens.nl) · [GitHub](https://github.com/timoc04) · info@timoclaessens.nl

© Timo Claessens. All rights reserved.
