#!/usr/bin/env python3
"""Dependency-free checks for public portfolio markup and CMS integration."""
from pathlib import Path
import re
import unittest
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]


class PortfolioChecks(unittest.TestCase):
    def test_homepage_cms_hooks(self):
        html = (ROOT / "index.html").read_text(encoding="utf-8")
        for field in ("hero_intro", "about_lead", "about_body", "about_closing",
                      "expertise_intro", "contact_intro"):
            self.assertIn(f'data-cms="{field}"', html)
        self.assertIn('src="/assets/js/projects.js?v=', html)
        self.assertIn('src="/assets/js/main.js?v=', html)
        self.assertEqual(len(re.findall(r'^<article class="project-card">', html, flags=re.MULTILINE)), 4)
        for i in range(1, 5):
            self.assertIn(f'data-cms="project_{i}_title"', html)
            self.assertIn(f'data-cms="project_{i}_description"', html)

    def test_authenticated_draft_preview(self):
        preview = (ROOT / "admin/preview.php").read_text(encoding="utf-8")
        self.assertIn("cms_require_login();", preview)
        self.assertIn("data-cms-preview", preview)
        self.assertIn("noindex,nofollow", preview)
        self.assertIn("no-store", preview)
        for kind, draft_reader, live_reader in (
            ("content", "cms_read_content_draft()", "cms_read_content()"),
            ("projects", "cms_read_project_draft()", "cms_read_projects()"),
        ):
            endpoint = (ROOT / f"admin/api/preview-{kind}.php").read_text(encoding="utf-8")
            self.assertIn("cms_authenticated()", endpoint)
            self.assertIn("http_response_code(401)", endpoint)
            self.assertIn(draft_reader, endpoint)
            self.assertIn(live_reader, endpoint)
            self.assertIn("no-store", endpoint)
        for script, endpoint in (
            ("main.js", "preview-content.php"),
            ("projects.js", "preview-projects.php"),
        ):
            source = (ROOT / "assets/js" / script).read_text(encoding="utf-8")
            self.assertIn("dataset.cmsPreview === 'true'", source)
            self.assertIn(endpoint, source)

    def test_canonical_pages(self):
        for name, url in (
            ("index.html", "https://timoclaessens.nl/"),
            ("privacy/index.html", "https://timoclaessens.nl/privacy/"),
        ):
            html = (ROOT / name).read_text(encoding="utf-8")
            self.assertIn(f'<link rel="canonical" href="{url}">', html)

    def test_sitemap(self):
        tree = ET.parse(ROOT / "sitemap.xml")
        urls = {node.text for node in tree.findall(
            ".//{http://www.sitemaps.org/schemas/sitemap/0.9}loc"
        )}
        self.assertEqual(urls, {
            "https://timoclaessens.nl/",
            "https://timoclaessens.nl/privacy/",
        })

    def test_versioned_assets_exist(self):
        for name in ("index.html", "privacy/index.html", "404.html"):
            html = (ROOT / name).read_text(encoding="utf-8")
            for path in re.findall(r'(?:href|src)="(/(?:assets|privacy)/[^"]+)"', html):
                file_path = path.split("?", 1)[0].lstrip("/")
                self.assertTrue((ROOT / file_path).is_file(), f"{name}: {path}")


if __name__ == "__main__":
    unittest.main()
