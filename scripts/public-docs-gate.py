#!/usr/bin/env python3
"""Gate: public WenPai product repos must not carry internal docs.

Accident: 2026-10-11 WPSlug public FeiCode/GitHub tree exposed SOP / release
ledgers / APPROVAL / HANDOFF / audits under docs/. Zip whitelist was fine;
the git tree was not.

Allowed under docs/: only docs/README.md (optional stub).
Forbidden anywhere (except vendor leftovers): APPROVAL.md, HANDOFF.md,
and paths that look like internal runbooks.

Usage:
  python3 scripts/wenpai/wenpai-public-docs-gate.py --cwd ~/Projects/wpslug
  # exit 0 = pass; exit 1 = fail (JSON on stdout when --json)
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

SCHEMA = 1

ALLOWED_DOCS = {Path("docs/README.md")}

# Never ship these basenames in a public product tree.
FORBIDDEN_BASENAMES = {
    "APPROVAL.md",
    "HANDOFF.md",
    "MEMORY.md",
    "CLAUDE.md",
}

# Path prefixes under docs/ that are always internal.
FORBIDDEN_DOCS_PREFIXES = (
    "docs/ops/",
    "docs/qa/",
    "docs/release/",
    "docs/audits/",
    "docs/ai-context/",
    "docs/admin-ui-",
    "docs/dev/",
    "docs/plans/",
    "docs/systems/",
)

# Filename / path fragments that usually mean internal process notes.
FORBIDDEN_NAME_RE = re.compile(
    r"(?i)(^|/)("
    r"sop[-_]|packaging\.md|after-gate|changelog-draft|"
    r"feicode-.*approval|release-closeout|full-audit|"
    r"session-brief|write.gate"
    r")"
)

SKIP_DIR_NAMES = {
    ".git",
    "node_modules",
    "vendor",
    "lib",  # leftover updaters / kit; kit has own NOTICE, not our SOP
    "dist",
    ".forgejo",
    ".github",
}


def iter_tracked_or_all(cwd: Path) -> list[Path]:
    """Prefer git ls-files; fall back to walk."""
    import subprocess

    try:
        out = subprocess.check_output(
            ["git", "-C", str(cwd), "ls-files", "-z"],
            stderr=subprocess.DEVNULL,
        )
        names = [n.decode("utf-8") for n in out.split(b"\0") if n]
        return [cwd / n for n in names]
    except Exception:
        files: list[Path] = []
        for p in cwd.rglob("*"):
            if not p.is_file():
                continue
            if any(part in SKIP_DIR_NAMES for part in p.relative_to(cwd).parts):
                continue
            files.append(p)
        return files


def classify(rel: str) -> str | None:
    """Return violation reason or None if allowed."""
    if rel in {str(p) for p in ALLOWED_DOCS}:
        return None

    name = Path(rel).name
    if name in FORBIDDEN_BASENAMES:
        return f"forbidden basename {name}"

    if rel == "docs" or rel.startswith("docs/"):
        if rel == "docs":
            return None
        for prefix in FORBIDDEN_DOCS_PREFIXES:
            if rel.startswith(prefix):
                return f"internal docs prefix {prefix.rstrip('/')}"
        # any other file under docs/ except README.md
        if rel != "docs/README.md":
            return "docs/ may only contain README.md on public product repos"

    if FORBIDDEN_NAME_RE.search(rel):
        # Allow readme.txt / README.md product files
        if name.lower() in {"readme.md", "readme.txt", "license", "license.md"}:
            return None
        if rel.startswith("lib/"):
            return None
        return "path looks like an internal runbook"

    return None


def scan(cwd: Path) -> list[dict[str, str]]:
    hits: list[dict[str, str]] = []
    for path in iter_tracked_or_all(cwd):
        try:
            rel = path.relative_to(cwd).as_posix()
        except ValueError:
            continue
        if any(part in SKIP_DIR_NAMES for part in Path(rel).parts[:-1]):
            # skip files inside skipped dirs (lib/vendor/...)
            if Path(rel).parts and Path(rel).parts[0] in SKIP_DIR_NAMES:
                continue
        reason = classify(rel)
        if reason:
            hits.append({"path": rel, "reason": reason})
    return sorted(hits, key=lambda h: h["path"])


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--cwd", default=".", help="product repo root")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()
    cwd = Path(args.cwd).resolve()
    if not cwd.is_dir():
        print(json.dumps({"ok": False, "error": f"not a dir: {cwd}"}), file=sys.stderr)
        raise SystemExit(2)

    hits = scan(cwd)
    payload = {
        "ok": not hits,
        "command": "public-docs-gate",
        "schema_version": SCHEMA,
        "cwd": str(cwd),
        "violations": hits,
        "rule": "docs/ only README.md; no APPROVAL/HANDOFF/SOP/release ledgers in public product trees",
    }
    if args.json or not sys.stdout.isatty():
        print(json.dumps(payload, ensure_ascii=False))
    else:
        if hits:
            print(f"FAIL public-docs-gate ({len(hits)}):", file=sys.stderr)
            for h in hits:
                print(f"  {h['path']}: {h['reason']}", file=sys.stderr)
        else:
            print("OK public-docs-gate")
    raise SystemExit(0 if not hits else 1)


if __name__ == "__main__":
    main()
