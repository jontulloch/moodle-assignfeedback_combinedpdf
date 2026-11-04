# GitHub initial setup

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

The supplied plugin did not previously have a Moodle PHPUnit suite. The included two smoke tests have **not** been run on a full Moodle installation yet. The first GitHub Actions run is a baseline check; resolve any findings before a release. Further tests of export permissions, marking allocation, anonymous marking, PDF conversion and data privacy are required.

## Version and release scope

The initial repository copy retains the uploaded plugin's existing version: release `1.0.0`, internal version `2026100804`, Moodle 5.2. No GitHub release or public publication is implied by creating the repository. After CI and staging review, publish a separately numbered prerelease with a Moodle-installable ZIP whose single top-level folder is `combinedpdf/`.

## Ownership and licence

The original package currently attributes copyright to Jonathan Tulloch. Confirm institutional copyright/licensing authority before making this repository public or distributing through Moodle Marketplace.
