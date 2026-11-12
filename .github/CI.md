# Moodle Plugin CI

The workflow is adapted from the [official Moodle Plugin CI template](https://github.com/moodlehq/moodle-plugin-ci/blob/main/gha.dist.yml) and [documentation](https://moodlehq.github.io/moodle-plugin-ci/).

## Execution

Pull requests, pushes to main, and manual workflow dispatches run the checks. The matrix targets Moodle 5.2 / PHP 8.3 with MariaDB 10.11 and PostgreSQL 16. Existing job names are preserved where CI already existed, so branch-protection check names remain valid. The workflow does not establish compatibility with every older version allowed by version.php.

## Checks

- PHP syntax, Moodle PHPCS and PHPDoc (zero permitted warnings), plugin structure, and upgrade savepoints are mandatory.
- PHP Mess Detector runs in advisory mode, matching the upstream template. Review its findings; an advisory result does not prove the plugin is free of maintainability problems.
- Grunt selects applicable AMD/YUI builds, JavaScript lint, CSS/SCSS lint and Gherkin lint, and detects stale compiled assets. Plugins without matching assets have no build work to perform.
- Mustache lint runs when templates exist.
- PHPUnit runs when test files exist. Existing privacy checks remain in place where already configured.
- Behat runs with Chrome and SCSS deprecation checks when feature files exist. Failure dumps are retained as GitHub Actions artifacts for seven days.
- A cancelled job is explicitly failed, following the upstream template.

The coverage inventory writes discovered PHPUnit, Behat and Mustache file counts to the run summary. Missing tests/templates are explicitly skipped; a skipped suite is not test coverage. No new browser scenarios or unit tests are introduced by this workflow update.

The template's deprecated PHP Copy/Paste Detector is deliberately omitted. This test workflow uses read-only repository permissions, pinned action commits, no deployment credentials, and no production data. Existing release and security workflows are separate from this update.
