# NHR Issue Reporter — project notes

Slug `nhrrob-issue-reporter` · display name "NHR Issue Reporter" · menu label **Report Issue** (Tools → Report Issue; never "NHR" in the UI).

Public repository, version 0.x, prepared for WordPress.org on 2026-11-23 (the zip is built as described under "Releases"). `.ai/PRD.md` holds the scope, what is planned, what was left out on purpose, and what stays free: read it before proposing a feature. `.ai/DESIGN.md` and `docs/design/` hold the "Aurora" design: a design change updates both in the same pull request. `docs/DECISIONS.md` says why the tools, checks, dependencies and rules are the way they are, and what undoing each would take: read it before changing one. `docs/notes/` has a dated note per working session.

**This repository is public.** Pricing, competitor research, positioning and the paid add-on's plan live in the private nhrrob/nhrrob-issue-reporter-pro (`docs/`); read them there and never copy them into this repository, its issues or its pull requests.

**Decision log rule:** a pull request that adds a tool, script, check, dependency or rule adds a row to `docs/DECISIONS.md` in the same pull request. One that reverses a decision strikes its row and adds a new one; it never deletes a row.

## Rules that are specific to this plugin

1. **A report can end up in a public repository.** The environment details are versions and names only: never the site's address, a path, an option value or anything about users. Everything that is sent is shown in the form first and can be edited. The one exception is a screenshot link, which shows the site's address: it is only sent with `allowSiteAddress: true`, the tick under the screenshots, and the server refuses screenshots without it.
2. **The token goes to `api.github.com` and nowhere else**, and never leaves the server: no REST answer and no page contains it, not even masked. The app only learns `hasToken` and `tokenInConfig`. `GitHub::request()` is the only place that sends it. `Connect::post()` calls github.com/login for "Connect GitHub" (device flow) with the public client ID and no token, and receives the token there; the device code stays in a transient on the server. Those two are the only places that make an HTTP request.
3. **A repository is always `owner/name` on github.com**, produced by `Repos::normalize()`. Nothing else may be put into an API path.
4. **Nothing is sent to GitHub without the user acting.** No request to GitHub on the front end or in cron; the feedback widget only posts to this site (`POST /feedback`), and its feedback waits in the Inbox until someone who may report sends it. The user acts in the plugin's own screen, or through an ability run as them (an agent using their application password). The similar-issue search runs when the title field is left; saving a new repository in Settings checks it with GitHub; opening the app (`GET /state`) makes no outside request. The Sent tab asks for the state of open issues only, at most 10 per view and each at most every 15 minutes.
5. **Every REST route and ability is behind `Settings::can_manage()`** (default `activate_plugins`, filter `nhrrob_issue_reporter_capability`); the routes also with the REST nonce. The one exception is `POST /feedback`, behind `Feedback\Widget::can_send()`: the widget is switched on, the person is logged in and has one of the chosen roles.
6. **The screen is a React app in the "Aurora" design** (`.ai/DESIGN.md`, `docs/design/`): a premium look, not the older plugins' shared shell. `@wordpress/scripts` only, no component or icon library, no web font, no database tables. Light and dark theme, both checked in a browser.
7. **The widget loads for the people it is on for only.** Nobody else gets a script, a style or an admin-bar item, and visitors never do. It draws in a shadow root, so it takes no style from the theme or wp-admin and gives none; the screenshot library (`html2canvas-pro`) is a separate chunk loaded on the first click. What it captures (screenshot, page path with screen-naming query values only, browser, window size, console errors) is shown as chips the person can switch off before sending.
8. **No database tables.** Feedback is a private post type (`nhrrob_ir_feedback`, no UI, not public), its screenshot an attachment with a random file name, kept out of the Media Library and deleted with its feedback.
9. **Identifiers**: namespace `NHRRob\IssueReporter\`, class `NHRRob_Issue_Reporter`, constants `NHRROB_ISSUE_REPORTER_*`, option and hook prefix `nhrrob_issue_reporter_`, CSS and HTML id prefix `nhrrob-issue-reporter-`, CSS tokens `--ir-*`, REST namespace `nhrrob-issue-reporter/v1`, JS global `nhrrobIssueReporterApp`, text domain `nhrrob-issue-reporter`.

## Layout

```
nhrrob-issue-reporter.php   main class, constants; Rest always, Admin in the dashboard only
uninstall.php               deletes the three options, the feedback and its screenshots, per site
includes/
  Admin.php        Tools → Report Issue: mount point, enqueue, boot data, the "Report issue" plugin link
  Rest.php         the routes the app calls; text() and line() keep what was typed
  Abilities.php    get-state, find-similar-issues, report-issue, comment-on-issue: each runs a Rest method (WordPress 6.9+)
  Settings.php     the settings option (repositories, other repositories), the token (constant first), the capability
  Repos.php        every target → owner/name: settings first, then the plugin's or theme's header
  Environment.php  the versions that go into a report
  GitHub.php       similar(), words(), create(), comment(), check(), state(), body(), request()
  Reports.php      the list of sent reports, their state and comment counts
  Screenshots.php  upload to the media library, Markdown links, whether GitHub can reach the site
  Connect.php      "Connect GitHub": device flow start and poll, run from the server; the client ID
  Feedback/Store.php   the feedback post type: create, list, update, delete, mark sent; screenshots out of the Media Library
  Feedback/Routes.php  GET and POST /feedback, POST and DELETE /feedback/<id>
  Feedback/Widget.php  who may send (can_send), the admin-bar item, the widget's scripts
assets/errors.js   keeps the page's last 20 console errors for the widget; loaded in the head for permitted people only, not built
admin/src/         React source: index.js, app.js, api.js, components/ (AppShell, ui, TargetPicker, Shots, EnvCards, ConnectGitHub, toasts), screens/ (Report, Sent, Inbox, InboxItem, Settings, WidgetSettings), style.scss
                   widget.js and widget/ (Widget, Markup, capture, style.scss): the feedback widget, a second entry
admin/build/       compiled output (committed, ships)
tests/             PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/workflows/deploy-on-pushing-a-new-tag.yml  a new tag (v<version>) sends that version, with .wordpress-org/, to WordPress.org (10up deploy action); as in Post Insights
.github/workflows/deploy-readme-asset-on-pushing-on-trunk.yml  a push to the `trunk` branch updates only readme.txt and .wordpress-org/ on WordPress.org; as in Post Insights
.github/workflows/plugin-check.yml  WordPress.org's Plugin Check on the zip's files, on every pull request
docs/wporg/        icon.html and banner.html, the sources of the WordPress.org icon and banners; render.mjs (Playwright) writes them to .wordpress-org/
.wordpress-org/    WordPress.org listing: icon, banners, screenshot-1 to 6 (captioned in readme.txt's == Screenshots ==); not shipped
.github/ci/smoke.php        calls every GET route inside a real WordPress, fails on any PHP notice
```

## Stored data (three options, none autoloaded, and the feedback posts)

| Option | Holds | Cap |
|---|---|---|
| `nhrrob_issue_reporter_settings` | `repos`: target key → `owner/name`, only where it differs from the header; `extra`: other repositories, rows of `label` and `repo`; `widget`: `enabled` (off by default), `roles` (administrator and editor by default), `button` | one row per target |
| `nhrrob_issue_reporter_token` | the GitHub token, when it is not set in `wp-config.php` | one string |
| `nhrrob_issue_reporter_reports` | sent reports, newest first: `plugin`, `repo`, `number`, `url`, `title`, `time`, `state`, `checked`, and `comments` once one was added | 100 rows |

The text of a report is not stored; it lives on GitHub. Screenshots of New report are ordinary attachments in the media library.

Feedback from the widget is a post of the private type `nhrrob_ir_feedback`: the text as content, the reporter as author, and the post meta `_nhrrob_issue_reporter` with `status` (new, sent, done), `kind` (bug, change, question), `severity` ('', minor, major, critical), `page`, `browser`, `screen`, `errors` (at most 20), `shot` (attachment id) and, once sent, `issue` (`repo`, `number`, `url`). Its screenshot carries `_nhrrob_issue_reporter_feedback` (the feedback's id). "Connect GitHub" keeps the device code in the transient `nhrrob_issue_reporter_device_<user id>` for at most 15 minutes.

A target is a plugin (keyed by its file), a must-use plugin (`mu:` and its file), a theme (`theme:` and its folder) or another repository (`repo:owner/name`). The app and the abilities still call the list `plugins` and the key `plugin`, and each row has a `type`.

## REST routes (`nhrrob-issue-reporter/v1`, all behind `can_manage`)

- `GET /state`: targets with their repository and `type`, `publicSite`, `hasToken`, `tokenInConfig`, the environment text, the sent reports, `widget`, `roles` and `inbox` (feedback waiting). No outside request. Filter `nhrrob_issue_reporter_state`.
- `POST /settings`: `repos` (target key → repository; the whole map is replaced), `extra` (rows of `label` and `repo`; the whole list is replaced), `widget`, `token`, `removeToken`. An address that is not a GitHub repository, or a new one the token cannot see, is refused with 400. Answers with the new state.
- `POST /similar`: `plugin`, `title` → `issues`.
- `GET /reports`: asks GitHub about open issues (limits in rule 4), answers `reports`.
- `POST /reports`: `plugin`, `title`, `what`, `steps`, `environment`, optional `labels`, `screenshots` (attachment ids) with `allowSiteAddress`, and `feedback` (the Inbox marks it sent on `nhrrob_issue_reporter_sent`) → `report`, `reports`. `nhrrob_issue_reporter_pre_send` may send it elsewhere.
- `POST /connect/start` (`private` for the `repo` scope) → `code`, `url`, `expiresIn`, `interval`; `POST /connect/poll` → `status` (pending, done with `state`, expired, denied). The app polls every `interval` seconds; the server reaches GitHub no more often than that.
- `GET /feedback` → `feedback`; `POST /feedback` (the widget, multipart: `text`, `kind`, `page`, `browser`, `screen`, `errors` as JSON, file `file`) → `id`; `POST /feedback/<id>` (`status`, `kind`, `severity`) → `item`; `DELETE /feedback/<id>`.
- `similar`, `reports` and `comments` take an optional `repo` for a target without one: it is checked with GitHub and saved (`Repos::resolve()`).
- `POST /comments`: `plugin`, `number`, and `body` or `what`/`steps`/`environment`, optional `screenshots` with `allowSiteAddress` → `url`, `reports`.
- `POST /screenshots`: one image as the file `file` (PNG, JPEG, WebP or GIF, at most 5 MB) → `id`, `url`. The app uploads when the report or comment is sent.

## Abilities (WordPress 6.9+, category `nhrrob-issue-reporter`)

`get-state` (read-only, optional `repo`, `state` and `search` to narrow the reports, runs `Rest::state()`), `find-similar-issues` (read-only, `plugin` and `title`, runs `Rest::similar()`), `report-issue` (writes, `plugin`, `title`, `what`, `steps`, `environment`, `labels`, `screenshots`, `allowSiteAddress`, `repo`, runs `Rest::send()`) and `comment-on-issue` (writes, `plugin`, `number`, `body`, `screenshots`, `allowSiteAddress`, `repo`, runs `Rest::comment()`). All are `show_in_rest` and `mcp.public`, so they run through `/wp-json/wp-abilities/v1/abilities/<name>/run` (GET for read-only, POST with `{"input": …}` for the two that write) or the MCP Adapter. `tests/AbilitiesTest.php` pins the names, the gate, which one writes and the input fields.

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

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. To try the app without touching GitHub, answer its requests with a temporary `pre_http_request` filter in a mu-plugin and delete it afterwards. A real report is best sent to this plugin's own repository.

## Releases

Releases are made by hand, as in Post Insights; nothing is tagged on a version change.

1. On a branch: raise `Version` in the header (and the class constant), `Stable tag` and `package.json`'s `version`, and add a `= x.y.z =` section under `== Changelog ==` in `readme.txt`. Merge it into `main`.
2. On GitHub, create a release from `main` with a new tag `v<version>` and that version's changelog as its notes. The tag push runs `deploy-on-pushing-a-new-tag.yml`, which sends the version to WordPress.org with the `SVN_USERNAME` and `SVN_PASSWORD` secrets. Until WordPress.org approves the plugin and the secrets are set, that run fails; the tag and release still work for the blog.
3. To change only the readme or the listing's images, push to `trunk`. Bring `trunk` up to `main` after each release.

The blog (`nhrrob/nazmulrobin_blog`) requires the plugin by these tags with Composer, so a release reaches it in Dependabot's next pull request.

The zip holds what `.distignore` leaves in, with the production autoloader: `composer install --no-dev`, then `rsync -a --exclude-from=.distignore ./ <dir>/nhrrob-issue-reporter/` and zip that folder. It ships `admin/src` and `package.json` so the source of `admin/build` is in it. Check it with `wp plugin check <dir>/nhrrob-issue-reporter --slug=nhrrob-issue-reporter`. Nothing about Pro goes into a shipped file.

The screenshots are taken on the dev site with sample data from a temporary mu-plugin (see "Trying it locally"), at 1440px wide, light, with the dark theme as the last one. The first is the widget on a sample page, sent by an editor; scroll to the top before a full-page capture, or wp-admin's fixed bars land mid-page.
