#!/usr/bin/env python3
"""HTTP integration smoke tests against a temporary local PHP CMS server."""
import contextlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import unittest

ROOT = Path(__file__).resolve().parents[1]
CONFIG = ROOT / "admin/config.php"


class CmsHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if CONFIG.exists():
            raise RuntimeError("Refusing to replace an existing CMS configuration.")
        cls.tmp = tempfile.TemporaryDirectory(prefix="portfolio-cms-test-")
        cls.password = "integration-test-password"
        password_hash = subprocess.check_output(
            ["php", "-r", "echo password_hash($argv[1], PASSWORD_DEFAULT);", cls.password],
            text=True,
        ).strip()
        directory = cls.tmp.name.replace("\\", "\\\\").replace("'", "\\'")
        password_hash = password_hash.replace("'", "\\'")
        CONFIG.write_text(
            "<?php\n"
            f"define('CMS_USERS', ['tester' => '{password_hash}']);\n"
            f"define('CMS_STORAGE_DIR', '{directory}');\n",
            encoding="utf-8",
        )
        try:
            with socket.socket() as sock:
                sock.bind(("127.0.0.1", 0))
                port = sock.getsockname()[1]
            cls.base = f"http://127.0.0.1:{port}"
            cls.server = subprocess.Popen(
                ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT)],
                cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            )
            cls.opener = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
                urllib.request.HTTPHandler(),
            )
            for _ in range(60):
                try:
                    cls.request("/admin/api/content.php")
                    break
                except urllib.error.URLError:
                    time.sleep(0.1)
            else:
                raise RuntimeError("PHP test server did not start.")
        except BaseException:
            cls.tearDownClass()
            raise

    @classmethod
    def tearDownClass(cls):
        server = getattr(cls, "server", None)
        if server is not None:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()
        CONFIG.unlink(missing_ok=True)
        tmp = getattr(cls, "tmp", None)
        if tmp is not None:
            tmp.cleanup()

    @classmethod
    def request(cls, path, fields=None, method=None):
        data = None if fields is None else urllib.parse.urlencode(fields).encode()
        request = urllib.request.Request(
            cls.base + path, data=data, method=method or ("POST" if data is not None else "GET"),
        )
        try:
            with cls.opener.open(request, timeout=5) as response:
                return response.status, response.headers, response.read().decode()
        except urllib.error.HTTPError as error:
            return error.code, error.headers, error.read().decode()

    def test_01_public_endpoints_and_method_limits(self):
        for path, expected_type in (
            ("/admin/api/content.php", dict),
            ("/admin/api/projects.php", list),
        ):
            status, headers, body = self.request(path)
            self.assertEqual(status, 200)
            self.assertIsInstance(json.loads(body), expected_type)
            self.assertIn("no-store", headers["Cache-Control"])
            status, headers, _ = self.request(path, method="POST")
            self.assertEqual(status, 405)
            self.assertEqual(headers["Allow"], "GET")

    def test_02_preview_requires_authentication(self):
        for path in ("/admin/api/preview-content.php", "/admin/api/preview-projects.php"):
            status, _, body = self.request(path)
            self.assertEqual(status, 401)
            self.assertEqual(json.loads(body)["error"], "Authentication required")

    def test_03_authenticated_draft_preview_and_publish(self):
        status, _, login = self.request("/admin/login.php")
        self.assertEqual(status, 200)
        token = re.search(r'name="csrf_token" value="([^"]+)"', login).group(1)
        status, _, _ = self.request("/admin/login.php", {
            "csrf_token": token, "username": "tester", "password": self.password,
        })
        self.assertEqual(status, 200)  # 303 redirect followed to editor

        status, _, editor = self.request("/admin/")
        self.assertEqual(status, 200)
        hidden = dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"', editor))
        published = json.loads(self.request("/admin/api/content.php")[2])
        changed = dict(published, hero_intro="Integration test draft headline")
        fields = {
            "csrf_token": hidden["csrf_token"],
            "draft_revision": hidden["draft_revision"],
            "live_revision": hidden["live_revision"],
            "action": "save_draft",
        }
        fields.update({f"content[{key}]": value for key, value in changed.items()})
        status, _, _ = self.request("/admin/", fields)
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(self.request("/admin/api/content.php")[2]), published)
        self.assertEqual(
            json.loads(self.request("/admin/api/preview-content.php")[2])["hero_intro"],
            changed["hero_intro"],
        )
        status, _, preview = self.request("/admin/preview.php")
        self.assertEqual(status, 200)
        self.assertIn('data-cms-preview="true"', preview)
        self.assertIn("noindex,nofollow", preview)
        status, _, editor = self.request("/admin/")
        hidden = dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"', editor))
        status, _, _ = self.request("/admin/", {
            "csrf_token": hidden["csrf_token"],
            "draft_revision": hidden["draft_revision"],
            "live_revision": hidden["live_revision"],
            "action": "publish",
        })
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(self.request("/admin/api/content.php")[2]), changed)
        self.assertEqual(json.loads(self.request("/admin/api/preview-content.php")[2]), changed)

    def test_04_project_preview_matches_public_without_draft(self):
        public = json.loads(self.request("/admin/api/projects.php")[2])
        preview = json.loads(self.request("/admin/api/preview-projects.php")[2])
        self.assertEqual(preview, public)

    def test_05_project_manager_draft_lifecycle(self):
        """Add, edit, reorder, delete and publish through the real project editor."""
        path = "/admin/projects.php"

        def revisions():
            status, _, html = self.request(path)
            self.assertEqual(status, 200)
            hidden = dict(re.findall(
                r'<input type="hidden" name="([^"]+)" value="([^"]*)"', html
            ))
            return {
                "csrf_token": hidden["csrf_token"],
                "revision": hidden["revision"],
                "live_revision": hidden["live_revision"],
            }

        def submit(action, project_id="", project=None):
            fields = dict(revisions(), action=action, id=project_id)
            if project is not None:
                fields.update({
                    f"project[{key}]": ", ".join(value) if key == "tags" else value
                    for key, value in project.items() if key != "id"
                })
            status, _, _ = self.request(path, fields)
            self.assertEqual(status, 200, f"Project action failed: {action}")

        def public():
            return json.loads(self.request("/admin/api/projects.php")[2])

        def preview():
            return json.loads(self.request("/admin/api/preview-projects.php")[2])

        initial = public()
        self.assertEqual(preview(), initial)
        new = {
            "title": "Integration test project",
            "description": "A temporary project used for HTTP tests.",
            "url": "https://example.org/test",
            "tags": ["PHP", "Testing"],
            "category": "TESTING",
            "art_label": "TEST CARD",
            "style": "api",
        }
        submit("add", project=new)
        self.assertEqual(public(), initial, "Draft add changed public projects")
        self.assertEqual(len(preview()), len(initial) + 1)
        added = preview()[-1]
        self.assertEqual(added["title"], new["title"])

        updated = dict(added, title="Edited integration project")
        submit("save", added["id"], updated)
        self.assertEqual(preview()[-1]["title"], updated["title"])
        self.assertEqual(public(), initial)

        submit("up", added["id"])
        self.assertEqual(preview()[-2]["id"], added["id"])
        submit("down", added["id"])
        self.assertEqual(preview()[-1]["id"], added["id"])

        submit("publish")
        self.assertEqual(public(), preview())
        self.assertEqual(public()[-1]["title"], updated["title"])

        submit("delete", added["id"])
        self.assertEqual(len(preview()), len(initial))
        self.assertEqual(len(public()), len(initial) + 1)
        submit("publish")
        self.assertEqual(public(), initial)
        self.assertEqual(preview(), initial)

        # A discarded draft must not change the published project list.
        submit("add", project=new)
        self.assertEqual(len(preview()), len(initial) + 1)
        submit("discard")
        self.assertEqual(public(), initial)
        self.assertEqual(preview(), initial)


if __name__ == "__main__":
    unittest.main()
