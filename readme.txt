=== NHR Issue Reporter ===
Contributors: nhrrob
Tags: github, issues, bug report, support, developer
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Report an issue with a plugin to its GitHub repository, without leaving the dashboard.

== Description ==
Something is wrong with a plugin on your site. NHR Issue Reporter opens an issue in that plugin's GitHub repository from a form in the dashboard, with the versions a developer asks for already filled in.

### How it works
- **Tools → Report Issue** has the form: plugin or theme, title, what happened, steps to reproduce and screenshots.
- Each plugin with a known repository also gets a **Report issue** link in the Plugins list.
- The versions of the plugin, WordPress, PHP, the theme and the active plugins are added. They are shown in the form first, and you can edit or remove them. The site's address is never added.
- While you type, open issues with a similar title are listed beside the form. **Add as comment** sends what you typed to one of them instead of opening another.
- Screenshots can be picked or pasted into the form. GitHub cannot take an image through its API, so they are added to your Media Library and linked from the issue. That link shows your site's address, so you tick a box to agree before they are sent.
- Light and dark theme, following your system or the switch in the app bar.
- The **Sent** tab lists the issues you opened, with a link and whether each is open or closed, a filter by plugin with counts, and **Add comment** under each.

### Which repository
A plugin's repository is read from its header: `GitHub Plugin URI`, or a `Plugin URI` or `Update URI` on github.com. Must-use plugins are read the same way, and a theme from `GitHub Theme URI`, or a `Theme URI` or `Update URI` on github.com, in its `style.css`. For anything else, type the repository under **Settings** as `owner/name`. Settings also takes **other repositories** with a label, such as an app the site talks to. A repository you add is checked with GitHub when it is saved.

### Setting it up
1. On GitHub, create a fine-grained personal access token that covers only the repositories you report to, with one permission: "Issues: Read and write".
2. Add it to `wp-config.php`: `define( 'NHRROB_ISSUE_REPORTER_TOKEN', 'your token' );`. You can also paste it under Tools → Report Issue → Settings, which stores it in the database.
3. On the same Settings tab, fill in the repository of each plugin that does not name one itself.

Only users who can activate plugins can report an issue or change the settings.

### For AI agents and MCP clients
On WordPress 6.9 and later the plugin registers four abilities, so an AI agent can report an issue for you through the Abilities API's REST routes (`/wp-json/wp-abilities/v1/`) or through the MCP Adapter plugin:

- `nhrrob-issue-reporter/get-state`: the plugins, themes and other repositories with their repository, whether a token is set, the environment text and the sent reports (optionally only those to one `repo`). Makes no request to GitHub.
- `nhrrob-issue-reporter/find-similar-issues`: open issues with a similar title.
- `nhrrob-issue-reporter/report-issue`: opens the issue, optionally with screenshots from the media library.
- `nhrrob-issue-reporter/comment-on-issue`: adds a comment to an issue in the plugin's repository.

Each runs the same code as the form, behind the same capability, as the person who is logged in, for example with an application password. The token is never part of an answer.

== External services ==
This plugin connects to the GitHub API (api.github.com), a service of GitHub, Inc. It does so only when a user who can activate plugins, in the form or through an ability run as them:

- types a title in the report form, or runs `find-similar-issues`: the title is sent to search for similar open issues in the chosen plugin's repository;
- sends the report form, or runs `report-issue`: the title, the text typed in the form and the environment details shown in the form are sent to open the issue;
- adds a comment, from the form, the Sent tab or `comment-on-issue`: the text of the comment is sent to the issue;
- sends screenshots: links to the images in your Media Library are put in the issue or comment, so it shows your site's address. Only after you tick the box that allows it;
- saves a repository under Settings that was not listed before: its name is sent to check that it exists and the token can see it;
- opens the Sent tab: the repository and number of each open issue are sent to ask whether it is still open.

Every request carries the GitHub token you configured. Nothing is sent on the front end, in the background or for visitors.

- [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
- [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)

== Frequently Asked Questions ==

= Is the issue public? =
It is as public as the repository. In a public repository anyone can read the issue, so check the Environment box before sending.

= Where is the token stored? =
In `wp-config.php` if you define `NHRROB_ISSUE_REPORTER_TOKEN` there, which is the safer place. Otherwise in the database. It is never printed in a page.

= What is removed when I delete the plugin? =
Its three options: the settings, the stored token and the list of sent reports. Issues on GitHub, and screenshots in the Media Library, are not touched.

== Changelog ==

= 0.4.0 =
* New design, light and dark: a sidebar with the sections and the GitHub connection, numbered steps in the form, cards and filters.
* Report: a searchable picker for the plugin, theme or repository; Markdown buttons; drop, paste or browse screenshots; each environment line has its own switch, or edit it all as text; a live preview of the issue; Ctrl or Cmd + Enter sends.
* The typed report is kept as a draft in the browser until it is sent or cleared.
* Sent: totals, filters by state and plugin, search by title or issue number, sorting. The filters are kept in the page address.
* Settings: a GitHub tab with the setup steps, and a Repositories tab with search and filters (has a repository, not set, set here).

= 0.3.0 =
* Report to themes, must-use plugins and other repositories saved in Settings; a new repository is checked when it is saved.
* Comment on an existing issue: from a similar issue in the form, from the Sent tab, and with the comment-on-issue ability.
* Screenshots: pick or paste images into the form. They are added to the Media Library and linked, after you agree that the link shows the site's address.
* Sent tab: filter by plugin, with a count per plugin and open and closed totals. get-state takes an optional repo.
* Fix: text in angle brackets, such as code, was removed from a report.
* Fix: similar issues were only found when every word of the title matched.
= 0.2.0 =
* Abilities for AI agents and MCP clients: get-state, find-similar-issues and report-issue.

= 0.1.0 =
* First version: the report form, repository detection and settings, the similar-issue check and the list of sent reports.
