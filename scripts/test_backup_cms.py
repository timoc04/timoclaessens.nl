#!/usr/bin/env python3
"""Isolated backup/restore safety checks; no production CMS data."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

spec = importlib.util.spec_from_file_location("backup_cms", Path(__file__).with_name("backup_cms.py"))
backup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(backup)


class BackupTests(unittest.TestCase):
    def test_snapshot_and_no_credentials(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root)
            source, destination = base / "cms", base / "backups"
            source.mkdir()
            destination.mkdir()
            (source / "content.json").write_text('{"hero_intro":"Hello"}')
            (source / "projects.draft.json").write_text("[]")
            (source / "login-private.json").write_text('{"secret":"not backed up"}')
            result = backup.create_backup(source, destination)
            self.assertEqual(result.stat().st_mode & 0o777, 0o600)
            with zipfile.ZipFile(result) as archive:
                self.assertEqual(set(archive.namelist()), {
                    "content.json", "projects.draft.json", "manifest.json",
                })
                self.assertEqual(json.loads(archive.read("content.json"))["hero_intro"], "Hello")
                self.assertEqual(json.loads(archive.read("manifest.json"))["files"],
                                 ["content.json", "projects.draft.json"])
            with self.assertRaises(ValueError):
                backup.create_backup(source, source)
            (source / "nested").mkdir()
            with self.assertRaises(ValueError):
                backup.create_backup(source, source / "nested")

    def test_invalid_json_and_symlinks_rejected(self):
        with tempfile.TemporaryDirectory() as root:
            base = Path(root)
            source, destination = base / "cms", base / "backups"
            source.mkdir()
            destination.mkdir()
            (source / "content.json").write_text("{broken")
            with self.assertRaises(json.JSONDecodeError):
                backup.create_backup(source, destination)
            (source / "content.json").unlink()
            (source / "content.json").symlink_to(base / "missing.json")
            with self.assertRaises(ValueError):
                backup.create_backup(source, destination)


if __name__ == "__main__":
    unittest.main()
