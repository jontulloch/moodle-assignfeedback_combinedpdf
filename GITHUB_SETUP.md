# GitHub initial setup (historical record)

> This document describes the repository's **original bootstrap process**, not current release or publication instructions. The original CI failures were corrected, and a separately numbered `v1.0.1-rc1` prerelease with an installer and checksum was published. For current status, see [README.md](README.md), [VALIDATION.md](VALIDATION.md) and [PUBLICATION_AUDIT.md](PUBLICATION_AUDIT.md).

## Create the repository

1. Visit https://github.com/new while signed in as the intended owner.
2. Repository name: `moodle-assignfeedback_combinedpdf` (recommended).
3. Choose **Private**. Do not initialise with a README, .gitignore or licence because these already exist in the source bundle.
4. Create the repository.
5. Extract the GitHub source bundle locally. Upload its **contents** to the repository root using **Add file > Upload files**, keeping the `.github/workflows/moodle-ci.yml`, `classes/`, `lang/`, and `tests/` paths. The repository root should contain `version.php`, not `combinedpdf/version.php`.
6. Commit the upload and open **Actions** to view the new CI runs. Enable workflows if GitHub asks.

## Initial CI scope

The workflow installs Moodle 5.2 on PHP 8.3 against MariaDB and PostgreSQL and runs:

- PHP syntax checking
- Moodle plugin validation
- Initial PHPUnit tests for PDF source handling
- Moodle coding standards (PHPCS)
- Moodle PHPDoc validation

**Historical note:** The supplied plugin initially lacked an executable Moodle PHPUnit CI result. Those two tests have since run successfully in the repository's Moodle 5.2 CI workflows. The required integration, export-permission, anonymity, PDF conversion and data-privacy acceptance scenarios remain described in [VALIDATION.md](VALIDATION.md).

## Version and release scope

The initial repository copy retains the uploaded plugin's existing version: release `1.0.0`, internal version `2026100804`, Moodle 5.2. This paragraph describes the original creation date: no release existed at initial upload. The subsequently published `v1.0.1-rc1` prerelease includes a Moodle-installable ZIP with the single top-level folder `combinedpdf/`.

## Ownership and licence

The original ZIP named Jonathan Tulloch as copyright holder. The repository's corrected attribution is Copyright (C) 2026 University of East London; the University has authorised open-source publication under `GPL-3.0-or-later`, as confirmed by the maintainer. [jontulloch](https://github.com/jontulloch) is the repository maintainer, not the asserted copyright owner. See [LICENSING.md](LICENSING.md). This documentation change does not itself publish a GitHub release or complete Marketplace approval.
