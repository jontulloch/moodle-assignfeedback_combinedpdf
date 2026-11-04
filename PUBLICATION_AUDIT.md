# Public-source pre-publication audit

**Repository:** `jontulloch/moodle-assignfeedback_combinedpdf`  
**Audit date:** 4 November 2026  
**Scope:** readiness for publishing source code on GitHub, *not* Moodle Plugins directory approval or institutional production authorisation.

## Review of existing material

- Reviewed all seven commits reachable from `main` at the audit's start, the six existing branches, and their tracked file inventories. All observed files were ordinary plugin source, automated checks, documentation or release tooling; no obvious backups, production configuration, student files or untracked binary payloads were seen.
- Reviewed the five existing pull requests: no separate ordinary issues or PR discussion comments existed when checked.
- Enumerated the 29 GitHub Actions workflow runs and their artifacts: three existing workflow artifacts contained the expected Combined PDF release-package bundle; no other published workflow artifacts were found.
- Inspected 53 available, non-skipped historical workflow job logs using indicators for common credential patterns, unmasked email addresses and named institutional system URLs. No matches were identified. This is a targeted review, **not** a guarantee that every possible private detail has been detected.
- Git history contains earlier versions with the original personal copyright attribution, now corrected to **2026 University of East London**, consistent with [LICENSING.md](LICENSING.md). Historical commits and old CI failures will remain visible if the repository becomes public.
- Main-branch Moodle 5.2 CI, Semgrep and Gitleaks checks passed for the published `v1.0.1-rc1` candidate; see [VALIDATION.md](VALIDATION.md). A separate **full-history** Gitleaks job is now included in GitHub Actions. **Confirm its result is green before making the repository public.**

## Actions taken

- Public-facing README, licence documentation, contribution and security instructions cross-reference the project, GitHub Issues, confidential vulnerability reporting and current release status.
- Archived setup notes are explicitly marked historical so readers do not mistake 1.0.0 bootstrap instructions for current install/release guidance.
- The already published prerelease's GitHub release notes will be synchronised with the source documentation by the existing gated release workflow after the audit PR is merged. The validated plugin ZIP and checksum stay tied to the original release commit.
- Added a read-only GitHub Actions Gitleaks scan of **all fetched branches and tags**, with fully redacted findings and no output artifact.

## Remaining owner-side publication gates

1. **Confirm the full-history Gitleaks GitHub Actions job passes** on the final audit commit. A green result is supporting evidence, not a warranty.
2. **Correct the GitHub About description.** The existing description refers to a single PDF and a 'Plagiary Report', while the plugin exports a ZIP of feedback packages. Suggested description: `Moodle 5.2 Assignment feedback plugin for exporting selected submissions and saved feedback as combined PDF packages in a ZIP.`
3. **Make the repository public only after institutional authorisation.** UEL copyright and publication authorisation were confirmed by the maintainer; the decision to change GitHub visibility remains separate. Prior commit history, branches, PR discussions, Actions logs and release artifacts will become publicly accessible.
4. **Enable and test GitHub Private Vulnerability Reporting** for the public repository under Security settings. Do not ask reporters to post exploit details in public issues.
5. **Enable appropriate protection of `main`**, e.g. required CI status checks and pull-request review, as available under the user's GitHub plan and repository settings. Existing `main` was not branch-protected at audit time.
6. **Retain institutional approval and acceptance evidence** required for production deployment; a public source repository and published prerelease do not imply Moodle Plugins directory acceptance or production certification.

## Interpretation

No obvious exposure was identified in the accessible material reviewed. Automated scanning and regular-expression-based checks can miss secrets, internal-only context and legal restrictions. If exposure is subsequently discovered, rotate affected credentials and address Git history; removing a token from the current branch alone does not make historical copies safe.
