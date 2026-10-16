# NHR Issue Reporter — project notes

Slug `nhrrob-issue-reporter` · display name "NHR Issue Reporter" · menu label **Report Issue** (Tools → Report Issue; never "NHR" in the UI).

Private repository, version 0.x, not on WordPress.org. `.ai/PRD.md` holds the scope, what was left out on purpose and the candidates for a paid version: read it before proposing a feature.

## Rules that are specific to this plugin

1. **A report can end up in a public repository.** The environment details are versions and names only: never the site's address, a path, an option value or anything about users. Everything that is sent is shown in the form first and can be edited.
2. **The token goes to `api.github.com` and nowhere else**, and never leaves the server: no REST answer and no page contains it, not even masked. The app only learns `hasToken` and `tokenInConfig`. `GitHub::request()` is the only place that makes an HTTP request.
3. **A repository is always `owner/name` on github.com**, produced by `Repos::normalize()`. Nothing else may be put into an API path.
4. **Nothing is sent to GitHub without the user acting.** No request on the front end or in cron. The user acts in the plugin's own screen, or through an ability run as them (an agent using their application password). The similar-issue search runs when the title field is left; opening the app (`GET /state`) makes no outside request. The Sent tab asks for the state of open issues only, at most 10 per view and each at most every 15 minutes.
5. **Every REST route and ability is behind `Settings::can_manage()`** (default `activate_plugins`, filter `nhrrob_issue_reporter_capability`); the routes also with the REST nonce.
6. **The screen is a React app in the style of my other plugins** (`.ai/DESIGN.md`): `@wordpress/scripts` only, no component or icon library, no database tables. Light and dark theme.
7. **Identifiers**: namespace `NHRRob\IssueReporter\`, class `NHRRob_Issue_Reporter`, constants `NHRROB_ISSUE_REPORTER_*`, option and hook prefix `nhrrob_issue_reporter_`, CSS and HTML id prefix `nhrrob-issue-reporter-`, CSS tokens `--ir-*`, REST namespace `nhrrob-issue-reporter/v1`, JS global `nhrrobIssueReporterApp`, text domain `nhrrob-issue-reporter`.

## Layout

```
nhrrob-issue-reporter.php   main class, constants; Rest always, Admin in the dashboard only
uninstall.php               deletes the three options, per site
includes/
  Admin.php        Tools → Report Issue: mount point, enqueue, boot data, the "Report issue" plugin link
  Rest.php         the five routes the app calls
  Abilities.php    get-state, find-similar-issues, report-issue: each runs a Rest method (WordPress 6.9+)
  Settings.php     the settings option, the token (constant first), the capability
  Repos.php        plugin → owner/name: settings first, then the plugin's header
  Environment.php  the versions that go into a report
  GitHub.php       similar(), create(), state(), body(), request()
  Reports.php      the list of sent reports and their state
admin/src/         React source: index.js, app.js, api.js, components/ (AppShell, ui, toasts), screens/ (Report, Sent, Settings), style.scss
admin/build/       compiled output (committed, ships)
tests/             PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/ci/smoke.php        calls every GET route inside a real WordPress, fails on any PHP notice
```

## Stored data (three options, none autoloaded)

| Option | Holds | Cap |
|---|---|---|
| `nhrrob_issue_reporter_settings` | `repos`: plugin file → `owner/name`, only where it differs from the plugin's header | one row per plugin |
| `nhrrob_issue_reporter_token` | the GitHub token, when it is not set in `wp-config.php` | one string |
| `nhrrob_issue_reporter_reports` | sent reports, newest first: `plugin`, `repo`, `number`, `url`, `title`, `time`, `state`, `checked` | 100 rows |

The text of a report is not stored; it lives on GitHub.

## REST routes (`nhrrob-issue-reporter/v1`, all behind `can_manage`)

- `GET /state`: plugins with their repository, `hasToken`, `tokenInConfig`, the environment text and the sent reports. No outside request.
- `POST /settings`: `repos` (plugin file → repository; the whole map is replaced; an address that is not a GitHub repository is refused with 400), `token`, `removeToken`. Answers with the new state.
- `POST /similar`: `plugin`, `title` → `issues`.
- `GET /reports`: asks GitHub about open issues (limits in rule 4), answers `reports`.
- `POST /reports`: `plugin`, `title`, `what`, `steps`, `environment` → `report`, `reports`.

## Abilities (WordPress 6.9+, category `nhrrob-issue-reporter`)

`get-state` (read-only, runs `Rest::state()`), `find-similar-issues` (read-only, `plugin` and `title`, runs `Rest::similar()`) and `report-issue` (writes, `plugin`, `title`, `what`, `steps`, `environment`, runs `Rest::send()`). All are `show_in_rest` and `mcp.public`, so they run through `/wp-json/wp-abilities/v1/abilities/<name>/run` (GET for read-only, POST with `{"input": …}` for report-issue) or the MCP Adapter. `tests/AbilitiesTest.php` pins the names, the gate, which one writes and the input fields.

A report goes: plugin has a repository → title and description present → `GitHub::create()` → `Reports::add()`. The Environment box holds exactly what is sent, the plugin's own line included; the server adds nothing to it. A GitHub failure comes back as a 400 with a message the app shows in a toast.

## Commands

```
npm run build               # admin/build
npm run lint                # ESLint (text domain enforced in .eslintrc.js)
composer install            # dev toolchain (phpcs + phpunit)
composer install --no-dev   # production autoloader (what is committed under vendor/)
composer run phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer run test:unit      # PHPUnit (WP_Mock, no DB, no network)
wp eval-file .github/ci/smoke.php   # inside a WordPress install with the plugin active
```

**PHP support: 7.4 and every later version.** `composer.json` pins `config.platform.php` to 7.4.33 so dev dependencies resolve to versions that run on every supported PHP.

`vendor/autoload.php` and `vendor/composer/*` are committed as the **production (`--no-dev`) autoloader**. A local `composer install` rewrites them, so run `composer install --no-dev` before committing anything under `vendor/`.

Always run `npm run lint` **and** `npm run build` after any change under `admin/src/`. The compiled `admin/build/` is committed and is what ships. `typescript` is pinned to `~6.0` in `package.json` only because the lint plugin does not load with 7.x.

`.husky/pre-commit` (active after `npm install`) blocks a commit on a failure of lint, PHPCS or the unit tests.

The codebase is PHPCS-clean, so any new violation is yours; fix it rather than widening `phpcs.xml`.

## Trying it locally

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. To try the app without touching GitHub, answer its requests with a temporary `pre_http_request` filter in a mu-plugin and delete it afterwards. A real report is best sent to this plugin's own private repository.
