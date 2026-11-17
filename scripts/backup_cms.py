#!/usr/bin/env python3
"""Create a private, validated CMS data snapshot. Never include credentials."""
import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import tempfile
import zipfile

FILES = ("content.json", "content.draft.json", "projects.json",
         "projects.draft.json", "projects.previous.json")


def create_backup(source: Path, destination: Path) -> Path:
    source = source.expanduser().resolve(strict=True)
    destination = destination.expanduser().resolve(strict=True)
    if not source.is_dir() or not destination.is_dir():
        raise ValueError("Source and destination must be existing directories.")
    if source == destination or source in destination.parents or destination in source.parents:
        raise ValueError("Backup directory must be separate from CMS storage.")
    if destination.is_symlink():
        raise ValueError("Do not use a symlinked backup directory.")
    snapshot = {}
    for name in FILES:
        path = source / name
        if path.is_symlink():
            raise ValueError(f"Refusing symlinked CMS file: {name}")
        if path.exists():
            if not path.is_file():
                raise ValueError(f"Not a regular file: {name}")
            raw = path.read_bytes()
            json.loads(raw)
            snapshot[name] = raw
    if not snapshot:
        raise ValueError("No published or draft CMS JSON files to back up.")
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    name = f"portfolio-cms-{stamp}-{os.urandom(4).hex()}.zip"
    fd, temporary = tempfile.mkstemp(prefix=".cms-backup-", dir=destination)
    os.chmod(temporary, 0o600)
    try:
        with os.fdopen(fd, "wb") as stream:
            with zipfile.ZipFile(stream, "w", compression=zipfile.ZIP_DEFLATED) as archive:
                for filename, raw in snapshot.items():
                    archive.writestr(filename, raw)
                archive.writestr("manifest.json", json.dumps({
                    "created_utc": datetime.now(timezone.utc).isoformat(),
                    "files": sorted(snapshot),
                }, indent=2))
        result = destination / name
        os.replace(temporary, result)
        return result
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, required=True,
                        help="Private CMS_STORAGE_DIR (never public_html)")
    parser.add_argument("--destination", type=Path, required=True,
                        help="Existing private backup directory outside public_html")
    args = parser.parse_args()
    result = create_backup(args.source, args.destination)
    print(f"CMS backup created: {result}")


if __name__ == "__main__":
    main()
