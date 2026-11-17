# Maintenance scripts

Run commands from the **repository root**. Scripts are source and test utilities, not public web endpoints; `scripts/.htaccess` denies direct HTTP access when Apache honours it.

## Commands

| Command | Purpose |
| --- | --- |
| `python3 scripts/test_site.py` | Public page structure, metadata, accessibility style guards and asset references |
| `python3 scripts/test_asset_cache.py` | Static asset caching rules |
| `python3 scripts/test_cms_http.py` | Isolated local PHP HTTP integration tests for CMS APIs, drafts and publishing |
| `python3 scripts/test_backup_cms.py` | CMS backup utility safety tests |
| `python3 scripts/test_apache_protection.py` | Checks sensitive source paths have Apache deny rules |
| `python3 scripts/test_public_headers.py` | Checks configured HTML security headers |
| `python3 scripts/version_assets.py` | Recompute content-hash query strings for selected CSS/JS/icon references |
| `node scripts/summarise_lighthouse.cjs` | Summarise existing Lighthouse JSON files in `lighthouse-reports/` |
| `python3 scripts/backup_cms.py --source … --destination …` | Create a private CMS JSON snapshot |

## Asset versioning

The `cache-busting.yml` workflow runs `version_assets.py` on relevant pushes to `main`, after regenerating home-screen PNG icons. It can push an additional commit. Wait for that workflow before deploying and avoid editing generated PNG icons directly.

When adding new assets or pages, review the asset-version script and the workflows' path filters. Asset URLs use short content hashes in `?v=…`; the hash is for browser cache invalidation, not integrity verification.

## CMS backups

See [CMS backup and recovery](../admin/README.md#backup-and-recovery). The source and destination must be separate private directories, outside the public website. This utility is manual and does not configure scheduling or external backup destinations.

## Lighthouse

The manual [Live Lighthouse audit](../.github/workflows/README.md) produces `mobile.json` and `desktop.json` as GitHub Actions artifacts and writes scores to the run summary. To summarise downloaded reports locally, put both JSON files under `lighthouse-reports/` and run:

```bash
node scripts/summarise_lighthouse.cjs
```

Lab scores are diagnostic; they do not replace real-user measurements or manual accessibility testing.
