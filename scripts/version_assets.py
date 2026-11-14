#!/usr/bin/env python3
"""Refresh asset versions before deployment: python3 scripts/version_assets.py."""
from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]
PAGES = [
    "index.html", "privacy/index.html",
    "projects/signage/admin/index.php", "projects/signage/admin/login.php",
]
ASSETS = [
    "assets/css/style.css", "privacy/privacy.css", "assets/js/main.js",
    "assets/icons/favicon.svg", "assets/icons/apple-touch-icon.png",
    "projects/signage/admin/admin.css",
]

def version(path):
    content = path.read_bytes()
    header = ("blob " + str(len(content)) + "\0").encode()
    return hashlib.sha1(header + content).hexdigest()[:7]

for page_name in PAGES:
    page = ROOT / page_name
    source = page.read_text(encoding="utf-8")
    for asset in ASSETS:
        if page_name.startswith("projects/signage/"):
            url = {
                "assets/icons/favicon.svg": "https://timoclaessens.nl/assets/icons/favicon.svg",
                "projects/signage/admin/admin.css": "admin.css",
            }.get(asset)
        else:
            url = "/" + asset
        if url is None:
            continue
        if not (ROOT / asset).exists():
            continue
        pattern = r'(?P<q>["\x27])' + re.escape(url) + r'(?:\?v=[^"\x27]*)?(?P=q)'
        replacement = lambda match: match.group("q") + url + "?v=" + version(ROOT / asset) + match.group("q")
        source = re.sub(pattern, replacement, source)
    page.write_text(source, encoding="utf-8")
print("Asset versions updated.")
