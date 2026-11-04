#!/usr/bin/env python3
"""Create a deterministic, Moodle-installable Combined PDF release ZIP."""

from __future__ import annotations

import hashlib
import re
import shutil
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZIP_STORED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
DEST = ROOT / "dist"
RELEASE = "1.0.1-rc1"
EXPECTED_VERSION = "2026110400"
ARCHIVE_NAME = f"moodle-assignfeedback_combinedpdf-v{RELEASE}.zip"
NOTES_NAME = f"RELEASE_NOTES_v{RELEASE}.md"
ZIP_FOLDER = "combinedpdf/"
FIXED_ZIP_TIME = (2026, 11, 4, 0, 0, 0)
FILES = (
    "version.php",
    "locallib.php",
    "LICENSE",
    "LICENSING.md",
    "README.md",
    "CHANGES.md",
    "SECURITY.md",
)
DIRECTORIES = ("classes", "lang", "tests")


def validate_version() -> None:
    version = (ROOT / "version.php").read_text(encoding="utf-8")
    expected = {
        "component": r"\$plugin->component\s*=\s*'assignfeedback_combinedpdf';",
        "version": rf"\$plugin->version\s*=\s*{EXPECTED_VERSION};",
        "release": rf"\$plugin->release\s*=\s*'{re.escape(RELEASE)}';",
        "maturity": r"\$plugin->maturity\s*=\s*MATURITY_RC;",
        "supported": r"\$plugin->supported\s*=\s*\[502,\s*502\];",
    }
    for description, pattern in expected.items():
        if re.search(pattern, version) is None:
            raise SystemExit(f"Invalid {description} in version.php")


def package_paths() -> list[Path]:
    files = [ROOT / name for name in FILES]
    for dirname in DIRECTORIES:
        folder = ROOT / dirname
        if not folder.is_dir():
            raise SystemExit(f"Missing plugin folder: {dirname}")
        files.extend(path for path in folder.rglob("*") if path.is_file())
    for path in files:
        if not path.is_file() or path.is_symlink():
            raise SystemExit(f"Unsafe or missing package file: {path}")
        if path.suffix == ".pyc" or "__pycache__" in path.parts:
            raise SystemExit(f"Unwanted Python cache file: {path}")
    return sorted(files, key=lambda p: p.relative_to(ROOT).as_posix())


def archive_entry(name: str, is_dir: bool = False) -> ZipInfo:
    info = ZipInfo(name, date_time=FIXED_ZIP_TIME)
    info.create_system = 3
    info.external_attr = ((0o40755 if is_dir else 0o100644) << 16)
    info.compress_type = ZIP_STORED if is_dir else ZIP_DEFLATED
    return info


def main() -> None:
    validate_version()
    sources = package_paths()
    if not any(path.name == "locallib.php" for path in sources):
        raise SystemExit("Missing Moodle plugin implementation")
    DEST.mkdir(parents=True, exist_ok=True)
    filename = DEST / ARCHIVE_NAME
    with ZipFile(filename, "w") as archive:
        archive.writestr(archive_entry(ZIP_FOLDER, is_dir=True), b"")
        for source in sources:
            filename_in_zip = ZIP_FOLDER + source.relative_to(ROOT).as_posix()
            archive.writestr(
                archive_entry(filename_in_zip),
                source.read_bytes(),
                compress_type=ZIP_DEFLATED,
                compresslevel=9,
            )

    with ZipFile(filename, "r") as archive:
        names = archive.namelist()
        if archive.testzip() is not None:
            raise SystemExit("ZIP integrity verification failed")
        if not names or names[0] != ZIP_FOLDER:
            raise SystemExit("ZIP must have one combinedpdf/ top-level folder")
        if any(not name.startswith(ZIP_FOLDER) for name in names):
            raise SystemExit("ZIP contains an unexpected top-level path")
        if len(names) != len(sources) + 1:
            raise SystemExit("ZIP file count does not match the source list")
        for source in sources:
            name = ZIP_FOLDER + source.relative_to(ROOT).as_posix()
            if archive.read(name) != source.read_bytes():
                raise SystemExit(f"ZIP content differs from source: {name}")

    digest = hashlib.sha256(filename.read_bytes()).hexdigest()
    checksum_file = DEST / f"{ARCHIVE_NAME}.sha256"
    checksum_file.write_text(f"{digest}  {ARCHIVE_NAME}\n", encoding="utf-8")
    notes = ROOT / ".github" / "release-notes" / f"v{RELEASE}.md"
    if not notes.is_file():
        raise SystemExit(f"Missing release notes: {notes}")
    shutil.copyfile(notes, DEST / NOTES_NAME)
    print(f"Verified {len(sources)} plugin files in {filename.name}")
    print(f"SHA-256: {digest}")
    print(f"Release notes: {NOTES_NAME}")


if __name__ == "__main__":
    main()
