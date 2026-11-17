# GitHub Actions workflows

Workflows validate changes and maintain static asset versions. **Do not assume they deploy to Junda/cPanel.** Check the live document roots and production data separately.

| Workflow | When it runs | Responsibility |
| --- | --- | --- |
| `portfolio-quality.yml` | Relevant PRs and pushes to `main` | Portfolio markup, metadata, asset caching and JS syntax |
| `portfolio-cms.yml` | Relevant PRs and pushes to `main` | PHP lint, CMS unit/HTTP tests, backup and Apache security checks |
| `signage-security.yml` | Signage PRs and pushes to `main` | Signage PHP lint, security and settings regression tests |
| `cache-busting.yml` | Relevant pushes to `main` or manual | Generate PNG icons, update content-hash URLs, commit changes to `main` |
| `lighthouse-audit.yml` | Manual only | Run mobile/desktop Lighthouse against the **live homepage** and upload JSON reports |

## Recommended pull request flow

1. Push a feature branch and open a PR against `main`.
2. Wait for all applicable checks; a skipped workflow can simply mean no matching files changed.
3. Review and merge only after checks pass.
4. If assets changed, wait for the cache-busting workflow's follow-up commit.
5. Deploy/verify the final `main` commit in cPanel. Protect private configuration and persistent signage/CMS data.

## Running Lighthouse

Open **Actions → Live Lighthouse audit → Run workflow** on `main`. The workflow measures Performance, Accessibility, Best Practices and SEO on mobile and desktop, displays scores and key metrics in the job summary, and uploads `lighthouse-live-reports` for 14 days.

The audit tests the public homepage only. Scores vary between runs; a 100/100 result does not guarantee every accessibility requirement, production security or real-user experience.

## Maintaining workflows

When adding a page, script or test, update the appropriate workflow path filters and test commands. Keep workflow permissions minimal. The cache-busting workflow is the exception that needs `contents: write` to commit generated references.

Do not put production credentials in workflows unless a deployment integration is explicitly designed and reviewed. See [repository README](../../README.md).
