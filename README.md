# timoclaessens.nl

Source code for [timoclaessens.nl](https://timoclaessens.nl) and its separate digital signage application at [signage.timoclaessens.nl](https://signage.timoclaessens.nl).

The portfolio is a lightweight HTML/CSS/JavaScript site with an optional PHP content manager. Signage is a PHP application with a fullscreen player, an authenticated admin interface and file-based screen settings. Both live sites are hosted through Junda/cPanel.

## Repository layout

```text
.
├── .github/workflows/
│   ├── cache-busting.yml       # Refresh versioned asset references and icons
│   ├── portfolio-cms.yml       # CMS PHP and JSON regression checks
│   ├── portfolio-quality.yml   # Static page, SEO and JS checks
│   └── signage-security.yml    # PHP lint, authentication and settings tests
├── assets/
│   ├── css/style.css           # Portfolio styles
│   ├── icons/                  # Shared logo, favicon and generated PNG icons
│   ├── js/main.js              # Portfolio interactions
│   └── js/projects.js          # CMS project cards
├── admin/                      # Portfolio CMS (separate from signage)
│   ├── index.php               # Authenticated text editor
│   ├── projects.php            # Project manager (CRUD + ordering)
│   ├── login.php / logout.php  # CMS authentication
│   ├── api/content.php         # Public plain-text content endpoint
│   ├── api/projects.php        # Public project feed
│   ├── includes/              # Auth and JSON storage helpers
│   ├── config.example.php      # Private config template
│   └── README.md               # CMS setup and backup guidance
├── index.html                  # Portfolio homepage
├── 404.html / 404.css          # Branded missing-page response
├── .htaccess                   # Apache 404 error document
├── privacy/
│   ├── index.html
│   └── privacy.css
├── projects/signage/
│   ├── .htaccess               # Redirect and routing rules
│   ├── index.php               # Public fullscreen player
│   ├── admin/                  # Login, logout, admin UI and styles
│   ├── includes/              # Security and atomic settings storage
│   ├── screens/                # Screen data and public media (see note below)
│   ├── config.example.php      # Template only; no credentials
│   ├── tests/                  # PHP regression tests
│   ├── README.md               # Signage setup and operation
│   └── SECURITY.md             # Security rollout and verification notes
├── scripts/version_assets.py   # Content-based asset URL versioning
├── scripts/test_site.py        # Public page regression tests
├── robots.txt / sitemap.xml    # Search engine discovery
├── site.webmanifest            # Portfolio home-screen metadata
└── README.md
```

## Edit the portfolio

Edit text, links, project cards and contact details directly in `index.html`. Look for the `<!-- EDIT: ... -->` markers. Shared design tokens and responsive layout are in `assets/css/style.css`; the mobile navigation is in `assets/js/main.js`. The privacy page is in `privacy/index.html` with its own `privacy/privacy.css`.

For a local preview, open the repository in VS Code with Live Server, or run:

```bash
python -m http.server 8000
```

Then visit `http://localhost:8000`. This previews **only the static portfolio**; it does not execute the PHP signage app.

### Icons

`assets/icons/favicon.svg` is the shared logo. The cache-busting workflow generates `apple-touch-icon.png`, `icon-192.png` and `icon-512.png` from it. Edit the SVG, not the generated PNGs. The portfolio and signage each have web manifests.

## Portfolio CMS

A separate, optional PHP CMS at `/admin/` edits Hero, About, Expertise and Contact text. Project titles, descriptions, URLs, technology tags and ordering are managed separately in `/admin/projects.php`. It keeps the existing static HTML as a fallback and loads published plain-text changes from `/admin/api/content.php`. It does **not** share signage accounts or configuration.

**Server-side setup is required before the CMS works:** create an untracked `admin/config.php` from `admin/config.example.php`, set a password-hashed account and a private writable storage directory outside `public_html`. See [CMS installation and safety notes](admin/README.md). Never commit real credentials or delete the private CMS storage during cPanel deployments. The project manager at `/admin/projects.php` supports adding, editing, deleting and ordering up to 20 projects with predefined artwork styles. The project data lives in private `projects.json` (plus `projects.previous.json` backup), outside Git and the document root. It does not yet support uploaded images, draft publishing or server-side SEO rendering.

## Custom 404 page

The root `.htaccess` sets `ErrorDocument 404 /404.html`, serving a branded page with the normal HTTP 404 status on the portfolio hostname. The page uses the shared portfolio stylesheet plus `404.css`. **Before deployment, check whether cPanel already has an untracked `public_html/.htaccess`**: merge the `ErrorDocument` directive into existing rules rather than replacing them. Ensure `404.html` and `404.css` are deployed in the portfolio document root. Test a non-existent URL and verify its HTTP status is 404, not 200. The signage subdomain has separate Apache routing and is not automatically covered by the portfolio's root error page.

## Signage

The signage source lives under `projects/signage/`, but its live hostname and document root are configured in cPanel. Do not assume the portfolio's `/projects/signage/` URL is the live app; the Apache rules redirect that route to the signage subdomain.

See **[Signage README](projects/signage/README.md)** for setup, screens, configuration, testing and backup guidance. See **[Security notes](projects/signage/SECURITY.md)** for the implemented security controls and their limitations.

**Important:** `projects/signage/screens/` contains a tracked example screen and test image from earlier development. Treat live screen settings and uploaded media as persistent data, not disposable deployment artefacts. Never delete or overwrite them without a verified backup. The real `projects/signage/config.php` is private and must never be committed.

## Git workflow

Use `main` as the stable branch. Make changes on a feature or chore branch, open a pull request, review the checks and merge when ready.

```bash
git switch main
git pull origin main
git switch -c feature/describe-change
# Edit and test
git add .
git commit -m "Describe change"
git push -u origin feature/describe-change
```

After merging, delete obsolete feature branches if they are no longer needed.

## Deployment (Junda/cPanel)

1. Merge the pull request into `main`.
2. If assets changed, wait for **Update asset cache versions** in GitHub Actions to finish. It may commit updated asset references or icons to `main`.
3. In **cPanel → Git Version Control**, use **Pull or Update** for the latest `main`. Complete any additional deployment step required by the configured document root.
4. Verify the portfolio, privacy page, signage player, admin login/logout and media management.

**GitHub Actions does not automatically deploy to Junda.** Do not replace the untracked production `config.php` or live screen data during updates. Back up production files before changing signage. Also preserve `admin/config.php` and the CMS storage directory outside `public_html` when deploying the CMS.

### Automated checks

- `.github/workflows/cache-busting.yml` runs on relevant pushes to `main` (or manually), renders home-screen icons and runs `scripts/version_assets.py`. The script adds content hashes such as `?v=77e1e34` to configured asset URLs.
- `.github/workflows/portfolio-cms.yml` lints CMS PHP, checks the JavaScript syntax and runs CMS content and project storage regression tests.
- `.github/workflows/signage-security.yml` runs on signage-related pull requests and pushes to `main`. It checks PHP syntax, authentication and rate limiting, plus JSON settings storage and malformed-file handling. It is **not** a full integration or penetration test.

If you add a new asset reference or PHP page, update `scripts/version_assets.py` and the relevant workflow path filters as needed.

## Release checklist

- [ ] Portfolio renders on desktop and mobile; links and navigation work.
- [ ] Privacy text and contact details are accurate.
- [ ] Icons, manifests and updated CSS/JS URLs load.
- [ ] GitHub checks pass and cPanel has pulled the latest `main`.
- [ ] Signage admin login, logout, upload, rename and deletion work.
- [ ] Fullscreen signage player still displays media, including recovery from missing or broken files.
- [ ] Image durations save correctly and existing screen settings are preserved.
- [ ] Live `config.php`, screen settings and uploaded media are preserved.
- [ ] CMS login, save, logout and public text updates work after PHP setup.
- [ ] Private `admin/config.php` and CMS storage remain outside Git/deployments.

## Contact

Website: [timoclaessens.nl](https://timoclaessens.nl)  
Email: info@timoclaessens.nl  
GitHub: [timoc04](https://github.com/timoc04)

© Timo Claessens. All rights reserved.

**CMS editing locations:** `/admin/` edits non-project homepage text; `/admin/projects.php` exclusively manages projects. Legacy project keys in private `content.json` are preserved for compatibility, not shown in the main editor.

## Site quality and search visibility

The public portfolio has canonical URLs for the homepage and privacy page, plus a minimal `robots.txt` and `sitemap.xml`. These files help discovery but do not control access: `/admin/` is protected by PHP authentication and Apache rules, **not** by `robots.txt`. Only actual public pages belong in the sitemap.

The `portfolio-quality.yml` workflow checks public markup, canonical metadata, sitemap contents, local asset references and JavaScript syntax. Run locally with `python3 scripts/test_site.py`, `node --check assets/js/main.js` and `node --check assets/js/projects.js`. The `cache-busting.yml` workflow also versions the dynamic project renderer and the branded 404 page assets. Deploy the latest `main` commit **after** the cache-busting workflow finishes, so the versioned references match deployed files.

Production checks still required: confirm HTTPS and canonical host redirects, real HTTP 404 status, protected CMS configuration, correct CMS JSON storage outside the document root, successful project editing and a mobile/browser check. Do not remove private CMS or signage data when deploying. GitHub checks cannot verify cPanel's live configuration.

### Safe CMS project editing

The project manager uses an optimistic revision check inside its existing file lock to reject stale updates (HTTP 409), preventing two tabs or administrators from silently overwriting each other. Project deletion asks for confirmation in the browser. Changes still publish immediately; this is not draft publishing or full version history. See `admin/README.md` for the restore procedure.

### Homepage copy drafts

`/admin/` supports private drafts for Hero, About, Expertise and Contact text. **Save draft** writes `content.draft.json` outside the web root without changing the live site; **Publish saved draft** updates `content.json`, and **Discard draft** removes only the draft. Stale tab edits are rejected. Project management continues to publish immediately; drafts for projects, preview and revision history are future phases. Preserve the private draft file during deployments. Run `php admin/tests/drafts_test.php` to test this workflow.

### Project drafts

The project manager now saves additions, edits, removals and ordering to private `projects.draft.json` until **Publish project draft** is clicked. The public project API continues to serve only `projects.json`. The draft is preserved across sessions and deployments, with conflict checks and explicit discard. See `admin/README.md` and run `php admin/tests/project_drafts_test.php`.

### Draft preview

Use **Preview drafts** from either CMS editor to open `/admin/preview.php`. The authenticated, no-store, noindex page reuses the real homepage layout and loads unpublished content and project drafts through private, session-protected read-only endpoints. The public homepage and public APIs continue to show published content. Preview only reflects **saved** drafts, not unsaved form edits. The preview has a persistent unpublished banner. Sign in again if your CMS session expires; never share preview links as public links.
