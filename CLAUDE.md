# NHR Issue Reporter — project notes

Slug `nhrrob-issue-reporter` · display name "NHR Issue Reporter" · menu label **Report Issue** (Tools → Report Issue; never "NHR" in the UI).

Private repository, version 0.x, not on WordPress.org. `.ai/PRD.md` holds the scope, what was left out on purpose and the candidates for a paid version: read it before proposing a feature.

## Rules that are specific to this plugin

1. **A report can end up in a public repository.** The environment details are versions and names only: never the site's address, a path, an option value or anything about users. Everything that is sent is shown in the form first and can be edited.
2. **The token goes to `api.github.com` and nowhere else**, and is never printed, not even masked. `GitHub::request()` is the only place that makes an HTTP request.
3. **A repository is always `owner/name` on github.com**, produced by `Repos::normalize()`. Nothing else may be put into an API path.
4. **Nothing is sent without a click.** No request on the front end, in cron or on a screen other than the plugin's own. The Sent tab asks for the state of open issues only, at most 10 per view and each at most every 15 minutes.
5. **Every action checks the capability and the nonce** (`Settings::can_manage()`, default `activate_plugins`, filter `nhrrob_issue_reporter_capability`).
6. **No JavaScript, no CSS, no build step, no REST routes, no database tables.** The screen is server-rendered with WordPress's own admin styles. Keep it that way until a feature truly needs more.
7. **Identifiers**: namespace `NHRRob\IssueReporter\`, class `NHRRob_Issue_Reporter`, constants `NHRROB_ISSUE_REPORTER_*`, option and hook prefix `nhrrob_issue_reporter_`, HTML id prefix `nhrrob-issue-reporter-`, text domain `nhrrob-issue-reporter`.

## Layout

```
nhrrob-issue-reporter.php   main class, constants; loads Admin in the dashboard only
uninstall.php               deletes the three options, per site
includes/
  Admin.php        Tools → Report Issue: tabs, both forms, the "Report issue" plugin link
  Settings.php     the settings option, the token (constant first), the capability
  Repos.php        plugin → owner/name: settings first, then the plugin's header
  Environment.php  the versions that go into a report
  GitHub.php       similar(), create(), state(), body(), request()
  Reports.php      the list of sent reports and their state
tests/             PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/ci/smoke.php        prints every tab inside a real WordPress, fails on any PHP notice
```

## Stored data (three options, none autoloaded)

| Option | Holds | Cap |
|---|---|---|
| `nhrrob_issue_reporter_settings` | `repos`: plugin file → `owner/name`, only where it differs from the plugin's header | one row per plugin |
| `nhrrob_issue_reporter_token` | the GitHub token, when it is not set in `wp-config.php` | one string |
| `nhrrob_issue_reporter_reports` | sent reports, newest first: `plugin`, `repo`, `number`, `url`, `title`, `time`, `state`, `checked` | 100 rows |

The text of a report is not stored; it lives on GitHub.

## How a report is sent

Both forms post back to the screen and are handled in `Admin::handle()` on `load-{screen}`, before any output. A report goes: capability and nonce → plugin has a repository → title and description present → `GitHub::similar()` (skipped when the form was already shown with similar issues, field `confirmed`) → `GitHub::create()` → `Reports::add()` → redirect to the Sent tab. Anything that stops it re-prints the form with what was typed; nothing is kept between requests.

## Commands

```
composer install            # dev toolchain (phpcs + phpunit)
composer install --no-dev   # production autoloader (what is committed under vendor/)
composer run phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer run test:unit      # PHPUnit (WP_Mock, no DB, no network)
wp eval-file .github/ci/smoke.php   # inside a WordPress install with the plugin active
```

**PHP support: 7.4 and every later version.** `composer.json` pins `config.platform.php` to 7.4.33 so dev dependencies resolve to versions that run on every supported PHP.

`vendor/autoload.php` and `vendor/composer/*` are committed as the **production (`--no-dev`) autoloader**. A local `composer install` rewrites them, so run `composer install --no-dev` before committing anything under `vendor/`.

The codebase is PHPCS-clean, so any new violation is yours; fix it rather than widening `phpcs.xml`.

## Trying it locally

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. To try the forms without touching GitHub, answer its requests with a temporary `pre_http_request` filter in a mu-plugin and delete it afterwards. A real report is best sent to this plugin's own private repository.
