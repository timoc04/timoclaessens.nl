# timoclaessens.nl

Personal portfolio website of Timo Claessens, built with plain HTML, CSS and a tiny amount of JavaScript. No build process, framework, dependencies, fonts from third parties or tracking scripts are required.

## Editing content

Open `index.html`. Search for the `<!-- EDIT: ... -->` comments. Every headline, paragraph, project card, link and footer item is directly editable as HTML. Duplicate or remove a complete `<article class="project-card"> ... </article>` block to manage projects. Update the email address in the contact section and links when necessary.

## Changing the design

Open `assets/css/style.css`. Colours, fonts and widths live together in the `:root` block at the top. Everything else is responsive styling. The project art and hero illustration are made in CSS, so no external images are needed.

## Files touched by the redesign

- `index.html`: new homepage
- `assets/css/style.css`: new visual design
- `assets/js/main.js`: mobile navigation and automatic copyright year
- `README.md`: this guide

Existing folders including `projects/signage/`, `privacy/`, icons and other assets remain untouched. The separately configured subdomain `signage.timoclaessens.nl` should continue to work provided its hosting/DNS mapping remains unchanged. If your subdomain serves the `projects/signage` folder, keep that folder deployed and do not replace the entire webroot.

## Preview and deploy

Preview locally using a local HTTP server (for example `python -m http.server 8000` in the repository root), then open `http://localhost:8000`. Copy changed files to the hosting document root, preserving all untouched folders. This repository's branch is for review; the live website will not change until you deploy or merge it through your existing deployment workflow.
