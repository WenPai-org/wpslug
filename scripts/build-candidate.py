#!/usr/bin/env python3
"""Build a deterministic WPSlug ZIP from the committed Git tree."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import time
import zipfile

RUNTIME_ROOTS = ("assets/", "includes/", "languages/")
RUNTIME_FILES = {"readme.txt", "wpslug.php"}


def git(*args: str) -> bytes:
    return subprocess.check_output(("git", *args))


def tracked_runtime_files() -> list[tuple[str, str]]:
    entries: list[tuple[str, str]] = []
    for raw in git("ls-tree", "-rz", "--full-tree", "HEAD").split(b"\0"):
        if not raw:
            continue
        metadata, raw_name = raw.split(b"\t", 1)
        mode, object_type, _object_id = metadata.decode("ascii").split()
        name = raw_name.decode("utf-8")
        if object_type != "blob":
            continue
        if name in RUNTIME_FILES or name.startswith(RUNTIME_ROOTS):
            entries.append((mode, name))
    return sorted(entries, key=lambda item: item[1].encode("utf-8"))


def read_text_from_head(path: str) -> str:
    return git("show", f"HEAD:{path}").decode("utf-8")


def validate_metadata(version: str) -> None:
    plugin = read_text_from_head("wpslug.php")
    readme = read_text_from_head("readme.txt")
    package = json.loads(read_text_from_head("package.json"))
    package_lock = json.loads(read_text_from_head("package-lock.json"))

    expected = {
        "plugin header": re.search(r"^Version:\s*(\S+)\s*$", plugin, re.MULTILINE),
        "plugin constant": re.search(r'define\("WPSLUG_VERSION",\s*"([^"]+)"\);', plugin),
        "Stable tag": re.search(r"^Stable tag:\s*(\S+)\s*$", readme, re.MULTILINE),
    }
    for label, match in expected.items():
        actual = match.group(1) if match else "<missing>"
        if actual != version:
            raise SystemExit(f"{label} mismatch: expected {version}, got {actual}")

    versions = {
        "package.json": package.get("version"),
        "package-lock.json": package_lock.get("version"),
        "package-lock root package": package_lock.get("packages", {}).get("", {}).get("version"),
    }
    for label, actual in versions.items():
        if actual != version:
            raise SystemExit(f"{label} mismatch: expected {version}, got {actual}")

    requirements = {
        "plugin Requires at least": re.search(r"^Requires at least:\s*(\S+)\s*$", plugin, re.MULTILINE),
        "readme Requires at least": re.search(r"^Requires at least:\s*(\S+)\s*$", readme, re.MULTILINE),
        "plugin Requires PHP": re.search(r"^Requires PHP:\s*(\S+)\s*$", plugin, re.MULTILINE),
        "readme Requires PHP": re.search(r"^Requires PHP:\s*(\S+)\s*$", readme, re.MULTILINE),
    }
    actual_requirements = {label: match.group(1) if match else "<missing>" for label, match in requirements.items()}
    if actual_requirements["plugin Requires at least"] != actual_requirements["readme Requires at least"]:
        raise SystemExit("WordPress requirement mismatch between plugin header and readme")
    if actual_requirements["plugin Requires PHP"] != actual_requirements["readme Requires PHP"]:
        raise SystemExit("PHP requirement mismatch between plugin header and readme")

    update_uri = re.search(r"^Update URI:\s*(\S+)\s*$", plugin, re.MULTILINE)
    if not update_uri or update_uri.group(1) != "https://updates.wenpai.net":
        raise SystemExit("Update URI must remain https://updates.wenpai.net")


def zip_datetime(epoch: int) -> tuple[int, int, int, int, int, int]:
    epoch = max(epoch, 315532800)
    utc = time.gmtime(epoch)
    return (utc.tm_year, utc.tm_mon, utc.tm_mday, utc.tm_hour, utc.tm_min, utc.tm_sec - utc.tm_sec % 2)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--output-dir", default="dist")
    parser.add_argument("--archive-name")
    args = parser.parse_args()

    validate_metadata(args.version)
    source_date_epoch = int(os.environ.get("SOURCE_DATE_EPOCH", git("show", "-s", "--format=%ct", "HEAD").strip()))
    timestamp = zip_datetime(source_date_epoch)
    output_dir = Path(args.output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    archive_name = args.archive_name or f"wpslug-{args.version}-candidate.zip"
    archive_path = output_dir / archive_name
    manifest_path = output_dir / f"{PurePosixPath(archive_name).stem}.manifest.txt"
    checksum_path = output_dir / f"{archive_name}.sha256"

    manifest_lines = [
        f"# WPSlug {args.version} package manifest",
        f"# git_head {git('rev-parse', 'HEAD').decode().strip()}",
        f"# source_date_epoch {source_date_epoch}",
        "# sha256  size  path",
    ]

    with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for mode, name in tracked_runtime_files():
            data = git("show", f"HEAD:{name}")
            path_in_zip = f"wpslug/{name}"
            info = zipfile.ZipInfo(path_in_zip, timestamp)
            info.create_system = 3
            info.external_attr = ((0o755 if mode == "100755" else 0o644) & 0xFFFF) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
            manifest_lines.append(f"{hashlib.sha256(data).hexdigest()}  {len(data)}  {path_in_zip}")

    manifest_path.write_text("\n".join(manifest_lines) + "\n", encoding="utf-8")
    archive_sha = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    checksum_path.write_text(f"{archive_sha}  {archive_path.name}\n", encoding="ascii")
    print(f"archive={archive_path}")
    print(f"sha256={archive_sha}")
    print(f"manifest={manifest_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
