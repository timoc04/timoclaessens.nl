#!/usr/bin/env python3
"""Guard the scope of portfolio asset browser caching."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]


class AssetCacheTests(unittest.TestCase):
    def test_cache_is_scoped_to_assets(self):
        rules = (ROOT / "assets/.htaccess").read_text()
        self.assertIn("<IfModule mod_headers.c>", rules)
        self.assertIn('Cache-Control "public, max-age=604800"', rules)
        match = re.search(r'<FilesMatch "([^"]+)">', rules)
        self.assertIsNotNone(match)
        pattern = re.compile(match.group(1))
        for name in ("style.css", "main.js", "favicon.svg", "icon.png", "font.woff2"):
            self.assertIsNotNone(pattern.search(name), name)
        for name in ("index.html", "index.php", "content.json", "login.php"):
            self.assertIsNone(pattern.search(name), name)

    def test_assets_have_versioned_references(self):
        homepage = (ROOT / "index.html").read_text()
        for asset in ("assets/css/style.css", "assets/js/main.js", "assets/js/projects.js"):
            self.assertRegex(homepage, re.escape("/" + asset) + r"\\?v=[a-f0-9]{7}")


if __name__ == "__main__":
    unittest.main()
