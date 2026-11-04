# Security policy

This project exports Moodle Assignment submissions and feedback, which can contain personal data and assessment records. Do not upload any student data, production configuration, private document URLs, access tokens, or credentials to GitHub issues, pull requests, screenshots, workflow artifacts or test fixtures.

## Reporting a vulnerability

Do **not** disclose vulnerability details, exploitation instructions, student data or secrets in public GitHub Issues, Discussions, pull requests or comments. For confidential reporting, use [GitHub Private Vulnerability Reporting](https://github.com/jontulloch/moodle-assignfeedback_combinedpdf/security/advisories) via **Security > Advisories > Report a vulnerability**, **once the maintainer has enabled that feature**. If the private-reporting option is unavailable, contact the maintainer using an already established confidential channel. Otherwise open an ordinary issue requesting private contact instructions **without including vulnerability details**. The maintainer must enable and verify private vulnerability reporting when the repository becomes public.

## Deployment boundaries

The plugin relies on Moodle login, Assignment access checks, authorised grading capabilities and core PHP/PDF conversion services. Access to exported ZIPs should be controlled under institutional information-handling policy. Restrict repository access to trusted maintainers and reviewers.

The repository runs Moodle Plugin CI, Semgrep, Gitleaks and a full-Git-history Gitleaks pre-publication check; passing tools reduce risk but do not certify the absence of vulnerabilities. See [PUBLICATION_AUDIT.md](PUBLICATION_AUDIT.md) for the public-source review and any outstanding manual settings.

Static checks and unit tests are not substitutes for a staging security review. Before production deployment, verify blind marking, marker allocations, groups, Moodle grading permissions, Turnitin-dependent behaviour, PDF generation and conversion, privacy, and handling of failed exports. See [VALIDATION.md](VALIDATION.md).
