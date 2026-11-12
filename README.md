# TimoClaessens.nl

Personal portfolio website of **Timo Claessens**, showcasing ICT projects, interests and contact details.

**Website:** https://timoclaessens.nl  
**Repository:** https://github.com/timoc04/timoclaessens.nl  
**Language:** British English (portfolio and privacy policy)

## Overview

The main website is a lightweight, responsive portfolio built using plain HTML, CSS and JavaScript. It is designed to be easy to maintain without a framework, package manager or build process.

The repository also contains a **separate PHP-based digital signage application** under `projects/signage/`. It is not part of the portfolio's front-end code and should be treated as a separate application when deploying.

### Portfolio sections

| Section | Description | Edit in |
| --- | --- | --- |
| Home | Introduction, headline and calls to action | `index.html` |
| About | Personal background and quick facts | `index.html` |
| Projects | Cards for AWS, digital signage, monitoring and homelab work | `index.html` |
| Expertise | Main technical interests | `index.html` |
| Contact | Email and social links | `index.html` |
| Privacy | Privacy and data-handling information | `privacy/index.html` |

## Technology

| Component | Technology | Purpose |
| --- | --- | --- |
| Structure | HTML5 | Semantic page content |
| Styling | CSS3 | Layout, responsive design, colours and illustrations |
| Interaction | Vanilla JavaScript | Mobile navigation and automatic copyright year |
| Favicon | SVG | Scalable `tc.` logo |
| Signage application | PHP, HTML, CSS and JavaScript | Separate media player and administration interface |
| Version control | Git and GitHub | Source management and change review |
| Hosting | Junda (cPanel) | Live hosting; deployment method must be configured separately |

The portfolio itself has no npm dependencies, database, build step or third-party analytics scripts in its source code. Hosting services may still process technical requests and logs.

## Repository structure

```text
timoclaessens.nl/
├── index.html                    # Main portfolio
├── README.md                     # This documentation
├── assets/
│   ├── css/
│   │   └── style.css             # Shared website styles
│   ├── icons/
│   │   └── favicon.svg           # tc. favicon
│   ├── images/
│   │   └── .gitkeep              # Reserved for future images
│   └── js/
│       └── main.js               # Mobile navigation and footer year
├── privacy/
│   ├── index.html                # Privacy policy
│   └── privacy.css               # Privacy-specific styling
├── projects/
│   └── signage/                  # Separate PHP signage application
│       ├── index.php             # Signage player
│       ├── admin/                # Administration interface
│       ├── screens/              # Screen settings and media
│       ├── config.example.php    # Configuration template
│       └── .htaccess             # Apache configuration
├── docs/                         # Reserved directory
└── downloads/                    # Reserved directory
```

The `docs/` and `downloads/` directories currently contain placeholder files. Their presence does not mean that downloadable resources have been published.

## Getting started locally

### 1. Clone the repository

```bash
git clone https://github.com/timoc04/timoclaessens.nl.git
cd timoclaessens.nl
code .
```

Alternatively, open an existing local clone in Visual Studio Code.

### 2. Preview the portfolio

**Option A — VS Code Live Server**

1. Install the *Live Server* extension.
2. Right-click the root `index.html`.
3. Select **Open with Live Server**.
4. Edit and save HTML or CSS to see the updated result.

**Option B — Python HTTP server**

From the repository root:

```bash
python -m http.server 8000
```

Open http://localhost:8000/ and stop the server using **Ctrl+C**.

Use a local HTTP server rather than opening the HTML as a `file://` URL, because the website uses root-relative asset links such as `/assets/css/style.css`.

> **Important:** Live Server and Python's basic HTTP server do not execute PHP. The signage application needs a PHP-capable environment and its own configuration.

## Editing the website

### Update text, links and sections

All portfolio content is directly editable in **`index.html`**. Search for the `<!-- EDIT: ... -->` comments to locate the main editable sections.

- Change the hero headline and introduction in the **HERO** section.
- Update personal information in **ABOUT**.
- Change the email address in **CONTACT** and check all `mailto:` links.
- Adjust GitHub and other external URLs as projects change.
- Edit the footer links and text as required.

### Add a project

Inside `index.html`, find the `<div class="project-grid">` container. Duplicate an existing `<article class="project-card"> ... </article>` element and update:

1. The project title and description.
2. The category and technology tags.
3. The destination URL and link label.
4. The illustration class or its CSS rules if a different visual is wanted.

Remove an entire project-card article to hide a project. Keep the HTML structure intact to preserve the grid layout.

### Change colours and layout

Open `assets/css/style.css`. Global design tokens are defined at the top:

```css
:root {
  --bg: #f6f7f5;
  --paper: #fff;
  --text: #192725;
  --muted: #68776f;
  --green: #346d56;
  --dark: #17382d;
  --border: #dce4dd;
  --width: 1160px;
}
```

These variables control the main colour palette and page width. Other CSS rules define the project cards, illustrations, navigation and responsive breakpoints. The homepage illustrations are drawn with CSS rather than external image assets.

### Change the favicon

The favicon lives at `assets/icons/favicon.svg` and is referenced by both the homepage and privacy page. Update the SVG to change the logo. If browsers keep displaying a cached icon, change the version query string in both HTML files (for example `favicon.svg?v=3`) and refresh.

The repository currently uses an SVG favicon rather than separate legacy ICO or Apple touch icon files.

### Update the privacy policy

Edit `privacy/index.html` to update the policy text, contact details and revision date. Privacy-specific visual rules are in `privacy/privacy.css`, while shared styles come from `assets/css/style.css`.

**Review the policy whenever hosting, cookies, analytics, forms, embedded services or data-handling practices change.** The text must match what the live website and its hosting provider actually do.

## Working with Git branches

Use `main` for the stable website and temporary branches for changes.

```bash
git switch main
git pull origin main
git switch -c feature/your-change
```

Make and test changes locally, then commit and push:

```bash
git add .
git commit -m "Describe your change"
git push -u origin feature/your-change
```

Create a GitHub pull request into `main`, review it and merge when ready. Once merged, the temporary branch can normally be deleted. To inspect an existing remote branch:

```bash
git fetch origin
git switch --track origin/branch-name
```

If the branch already exists locally, use `git switch branch-name` instead. Commit or stash local changes before switching branches.

## Deployment to Junda

The public portfolio is hosted separately from GitHub. **Merging into `main` does not, by itself, guarantee a live deployment.** Whether it is published automatically depends on your hosting and deployment configuration.

For a manual deployment using cPanel:

1. Back up the live files first.
2. Confirm the document root configured for `timoclaessens.nl` in cPanel.
3. Upload the changed `index.html`, `assets/` files and `privacy/` files to their corresponding locations.
4. Preserve existing application directories, especially `projects/signage/`, and any server-side configuration.
5. Verify the homepage, privacy page, favicon, mobile navigation and external links in the browser.

**Do not blindly delete or replace the entire hosting document root.** Some files on the hosting server may be deliberately untracked in Git, including private configuration and uploaded media.

An automated GitHub Actions deployment could be added later, but is **not documented here as an existing configured pipeline**.

## Digital signage application

The repository contains a separate PHP application at `projects/signage/` with a player, admin pages and screen media.

The intended public address is **https://signage.timoclaessens.nl**, but the actual relationship between that subdomain and its document root is controlled by Junda/cPanel. Verify the mapping before changing hosting paths.

Key considerations:

- Do not modify or remove `projects/signage/` as part of portfolio-only updates.
- The PHP application requires its own configuration; `config.example.php` is a template, not a production secrets file.
- Do not commit passwords, private configuration or sensitive data to Git.
- Preserve screen media and settings during deployment; some live content may not be represented in this repository.
- Test the player and admin separately after server-side changes.

See `projects/signage/README.md` for the application's own (currently minimal) documentation.

## Pre-release checklist

- [ ] Homepage renders correctly on desktop and mobile.
- [ ] Navigation, anchor links and buttons work.
- [ ] Project descriptions, tags and external links are accurate.
- [ ] Contact email addresses are correct.
- [ ] Privacy policy matches actual data processing and hosting.
- [ ] SVG favicon loads on both pages.
- [ ] Browser console shows no unexpected errors.
- [ ] Signage player and admin remain functional.
- [ ] Hosting document root and deployment scope are confirmed.
- [ ] Changes are reviewed in a pull request before merging.

## Maintenance

The portfolio has no dependency installation or build artefacts to maintain. Regular maintenance mainly involves keeping project content current, checking links, reviewing the privacy policy and testing responsive layouts after design changes.

## Contact

**Timo Claessens**  
Website: https://timoclaessens.nl  
Email: info@timoclaessens.nl  
GitHub: https://github.com/timoc04

---

© Timo Claessens. All rights reserved.
