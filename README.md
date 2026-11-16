# timoclaessens.nl

Source code for [timoclaessens.nl](https://timoclaessens.nl) and its separate digital signage application at [signage.timoclaessens.nl](https://signage.timoclaessens.nl).

The portfolio is a lightweight HTML/CSS/JavaScript site. Signage is a PHP application with a fullscreen player, an authenticated admin interface and file-based screen settings. Both live sites are hosted through Junda/cPanel.

## Repository layout

```text
.
├── .github/workflows/
│   ├── cache-busting.yml       # Refresh versioned asset references and icons
│   └── signage-security.yml    # PHP lint, authentication and settings tests
├── assets/
│   ├── css/style.css           # Portfolio styles
│   ├── icons/                  # Shared logo, favicon and generated PNG icons
│   └── js/main.js              # Portfolio interactions
├── index.html                  # Portfolio homepage
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

**GitHub Actions does not automatically deploy to Junda.** Do not replace the untracked production `config.php` or live screen data during updates. Back up production files before changing signage.

### Automated checks

- `.github/workflows/cache-busting.yml` runs on relevant pushes to `main` (or manually), renders home-screen icons and runs `scripts/version_assets.py`. The script adds content hashes such as `?v=77e1e34` to configured asset URLs.
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

## Contact

Website: [timoclaessens.nl](https://timoclaessens.nl)  
Email: info@timoclaessens.nl  
GitHub: [timoc04](https://github.com/timoc04)

© Timo Claessens. All rights reserved.
