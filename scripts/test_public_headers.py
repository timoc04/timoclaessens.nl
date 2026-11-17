#!/usr/bin/env python3
"""Regression checks for portfolio-only Apache security headers."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class PublicHeaderTests(unittest.TestCase):
    def test_static_html_security_headers(self):
        rules = (ROOT / ".htaccess").read_text()
        self.assertIn("<IfModule mod_headers.c>", rules)
        self.assertIn('<FilesMatch "\\.html$">', rules)
        for header in (
            "X-Content-Type-Options", "Referrer-Policy",
            "X-Frame-Options", "Permissions-Policy",
        ):
            with self.subTest(header=header):
                self.assertIn(f"Header always set {header} ", rules)

    def test_no_unverified_hsts_or_csp(self):
        rules = (ROOT / ".htaccess").read_text()
        self.assertNotIn("Strict-Transport-Security", rules)
        self.assertNotIn("Content-Security-Policy", rules)


if __name__ == "__main__":
    unittest.main()
