# Security policy

This project exports Moodle Assignment submissions and feedback, which can contain personal data and assessment records. Do not upload any student data, production configuration, private document URLs, access tokens, or credentials to GitHub issues, pull requests, screenshots, workflow artifacts or test fixtures.

## Reporting a vulnerability

While the repository is private, report issues confidentially to the maintainer through an agreed private channel. Do not disclose exploit details in ordinary GitHub issues. If the repository becomes public, enable GitHub Private Vulnerability Reporting and use its **Security > Advisories > Report a vulnerability** process.

## Deployment boundaries

The plugin relies on Moodle login, Assignment access checks, authorised grading capabilities and core PHP/PDF conversion services. Access to exported ZIPs should be controlled under institutional information-handling policy. Restrict repository access to trusted maintainers and reviewers.

Static checks and unit tests are not substitutes for a staging security review. Before production deployment, verify blind marking, marker allocations, groups, Moodle grading permissions, Turnitin-dependent behaviour, PDF generation and conversion, privacy, and handling of failed exports. See [VALIDATION.md](VALIDATION.md).
