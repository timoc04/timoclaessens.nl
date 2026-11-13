#!/usr/bin/env python3
"""Refresh cache-busting query strings before publishing the static website.

Run: python3 scripts/version_assets.py
Uses Git blob SHA-1 prefixes, matching GitHub's file hashes. No dependencies.
"""
from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]
PAGES = [ROOT / "index.html", ROOT / "privacy/index.html",
         ROOT / "projects/signage/admin/index.php",
         ROOT / "projects/signage/admin/login.php"]
ASSETS = [
    "assets/css/style.css", "privacy/privacy.css",
    "assets/js/main.js", "assets/icons/favicon.svg",
    "projects/signage/admin/admin.css",
]

def git_blob_hash(content: bytes) -> str:
    header = f"blob {len(content)}\\0".encode().replace(b"\\0", b"\0")
    return hashlib.sha1(header + content).hexdigest()[:7]

for page in PAGES:
    source = page.read_text(encoding="utf-8")
    for asset in ASSETS:
        target = ROOT / asset
        if not target.is_file():
            continue
        if page.parent == ROOT / "projects/signage/admin":
            if asset == "projects/signage/admin/admin.css":
                url = "admin.css"
            elif asset == "assets/icons/favicon.svg":
                url = "../../../assets/icons/favicon.svg"
            else:
                continue
        else:
            url = "/" + asset
        version = git_blob_hash(target.read_bytes())
        pattern = r'(?P<quote>["\\\'])' + re.escape(url) + r'(?:\\?v=[^"\\\']*)?(?P=quote)'
        source = re.sub(pattern, lambda m: m.group("quote") + url + "?v=" + version + m.group("quote"), source)
    page.write_text(source, encoding="utf-8")
print("Asset versions updated.")
