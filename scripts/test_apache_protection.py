#!/usr/bin/env python3
"""Verify that Apache access rules protect CMS internals."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class ApacheProtectionTests(unittest.TestCase):
    def test_private_directories_are_denied(self):
        for relative in ("admin/includes/.htaccess", "admin/tests/.htaccess", "scripts/.htaccess"):
            with self.subTest(path=relative):
                rules = (ROOT / relative).read_text()
                self.assertIn("Require all denied", rules)

    def test_cms_configuration_is_denied(self):
        rules = (ROOT / "admin/.htaccess").read_text()
        self.assertIn('Require all denied', rules)
        self.assertIn('config', rules)
        self.assertIn('FilesMatch', rules)


if __name__ == "__main__":
    unittest.main()
