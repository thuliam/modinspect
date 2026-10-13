#!/usr/bin/env python3
"""Produce an offline, allowlisted ModInspect preview archive.

No network access, database access, credentials, FTP, or server mutations.
Requires only Python 3 standard library and Git.
"""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "dist"
ARCHIVE = OUTPUT / "modinspect-preview.zip"
MANIFEST = OUTPUT / "manifest.json"
CHECKSUMS = OUTPUT / "checksums.sha256"

REQUIRED = {
    "app/bootstrap.php",
    "config/app.php",
    "public/index.php",
    "public/.htaccess",
}
ASSET_EXTENSIONS = {
    ".css", ".js", ".jpg", ".jpeg", ".png", ".webp",
    ".gif", ".svg", ".ico", ".woff", ".woff2", ".ttf", ".otf",
}
# A development-only README lives under the public assets directory.
INTENTIONAL_EXCLUSIONS = {"public/assets/admin/README.md"}


def fail(reason: str) -> None:
    raise RuntimeError("DEPLOYMENT_PACKAGE_HOLD: " + reason)


def include(relative: str) -> bool:
    p = PurePosixPath(relative)
    if p.is_absolute() or ".." in p.parts or "\\" in relative:
        fail("unsafe repository path")
    if any(part.startswith(".") for part in p.parts) and relative != "public/.htaccess":
        fail("hidden or environment-like file in deploy scope: " + relative)
    if relative in INTENTIONAL_EXCLUSIONS:
        return False
    if p.parts[0] in {"app", "config"}:
        if p.suffix != ".php":
            fail("unreviewed non-PHP file in application/config: " + relative)
        return True
    if relative in {"public/index.php", "public/.htaccess"}:
        return True
    if len(p.parts) >= 3 and p.parts[:2] == ("public", "assets"):
        if p.suffix.lower() not in ASSET_EXTENSIONS:
            fail("unreviewed asset type: " + relative)
        return True
    fail("unreviewed file in deploy scope: " + relative)


def tracked_files() -> list[str]:
    raw = subprocess.check_output(
        ["git", "ls-files", "--cached", "-z", "--", "app/", "config/", "public/"],
        cwd=ROOT,
    )
    paths = sorted(set(p.decode("utf-8") for p in raw.split(b"\0") if p))
    if not paths:
        fail("no tracked source files in allowlisted directories")
    return paths


def read_allowed_file(relative: str) -> bytes:
    path = ROOT / relative
    # Reject symlinks at any level, including links resolving inside the repo.
    cursor = path
    while cursor != ROOT:
        if cursor.is_symlink():
            fail("symlink in deploy scope: " + relative)
        cursor = cursor.parent
    if not path.is_file() or not path.resolve().is_relative_to(ROOT):
        fail("missing/non-regular/out-of-root file: " + relative)
    return path.read_bytes()


def main() -> None:
    candidates = tracked_files()
    selected = [p for p in candidates if include(p)]
    if not REQUIRED.issubset(selected):
        fail("required web application files are absent")
    if not any(p.startswith("public/assets/") for p in selected):
        fail("public assets are absent")
    if len(selected) != len(set(selected)):
        fail("duplicate package paths")

    OUTPUT.mkdir(exist_ok=True)
    files = []
    with zipfile.ZipFile(ARCHIVE, mode="w", compression=zipfile.ZIP_DEFLATED,
                         compresslevel=9, allowZip64=True) as out:
        for relative in selected:
            content = read_allowed_file(relative)
            archive_entry = zipfile.ZipInfo(relative, date_time=(2020, 1, 1, 0, 0, 0))
            archive_entry.create_system = 3
            archive_entry.external_attr = (0o100644 << 16)
            out.writestr(archive_entry, content, compress_type=zipfile.ZIP_DEFLATED)
            files.append({
                "path": relative,
                "bytes": len(content),
                "sha256": hashlib.sha256(content).hexdigest(),
            })

    with zipfile.ZipFile(ARCHIVE) as check:
        if check.testzip() is not None:
            fail("ZIP integrity verification failed")
        if check.namelist() != selected:
            fail("ZIP member list differs from allowlist")
        for item in files:
            if hashlib.sha256(check.read(item["path"])).hexdigest() != item["sha256"]:
                fail("ZIP member checksum mismatch")

    archive_digest = hashlib.sha256(ARCHIVE.read_bytes()).hexdigest()
    checkout_commit = subprocess.check_output(
        ["git", "rev-parse", "HEAD"], cwd=ROOT, text=True
    ).strip()
    manifest = {
        "schema_version": 1,
        "purpose": "REVIEW_ONLY_NOT_FOR_AUTOMATIC_DEPLOYMENT",
        "repository": "thuliam/modinspect",
        "checkout_commit": checkout_commit,
        "server_target_after_separate_approval":
            "/home/thuliam/domains/thuliamdev.com/modinspect",
        "web_document_root_after_separate_approval": "public/",
        "source_directories": ["app/", "config/", "public/"],
        "file_count": len(files),
        "archive_sha256": archive_digest,
        "excluded": [
            ".env and any environment-specific secrets",
            "database dumps, migrations and seeds",
            "tests, CLI, docs and development README",
            "storage and runtime data",
            "existing hosting files (including public/index.html placeholder)",
        ],
        "release_gates": [
            "owner approval for any server change",
            "fresh server backup and rollback plan",
            "manual server-side environment configuration",
            "isolated production database decision and migration approval",
            "runtime writable storage and required PHP extensions check",
            "explicit approval to remove or replace default public/index.html",
        ],
        "files": files,
    }
    MANIFEST.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    CHECKSUMS.write_text(
        archive_digest + "  " + ARCHIVE.name + "\n", encoding="ascii"
    )

    print("Deployment package preview verified")
    print("Files:", len(files))
    print("ZIP SHA-256:", archive_digest)
    print("FTPS used: NO; hosting writes: NONE; database writes: NONE")
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
            summary.write("## ModInspect — deployment package DRY RUN\n")
            summary.write("- Gate: **PREVIEW ONLY — no production deploy**\n")
            summary.write(f"- PHP deployment files: **{len(files)}**\n")
            summary.write(f"- ZIP SHA-256: \`{archive_digest}\`\n")
            summary.write("- Upload target: **NONE** (GitHub Artifact only)\n")
            summary.write("- FTP, secrets, database access: **NONE**\n")
            summary.write("- Blockers: server .env, DB, storage/permissions, PHP extensions, placeholder retirement, rollback require separate approval\n")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, subprocess.CalledProcessError, zipfile.BadZipFile) as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)
