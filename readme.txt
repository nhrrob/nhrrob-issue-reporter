=== NHR Issue Reporter ===
Contributors: nhrrob
Tags: github, issues, bug report, support, developer
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Report an issue with a plugin to its GitHub repository, without leaving the dashboard.

== Description ==
Something is wrong with a plugin on your site. NHR Issue Reporter opens an issue in that plugin's GitHub repository from a form in the dashboard, with the versions a developer asks for already filled in.

### How it works
- **Tools → Report Issue** has the form: plugin, title, what happened and steps to reproduce.
- Each plugin with a known repository also gets a **Report issue** link in the Plugins list.
- The versions of the plugin, WordPress, PHP, the theme and the active plugins are added. They are shown in the form first, and you can edit or remove them. The site's address is never added.
- While you type, open issues with a similar title are listed beside the form, so you can comment on an existing one instead.
- Light and dark theme, following your system or the switch in the app bar.
- The **Sent** tab lists the issues you opened, with a link and whether each is open or closed.

### Which repository
A plugin's repository is read from its header: `GitHub Plugin URI`, or a `Plugin URI` or `Update URI` on github.com. For any other plugin, type the repository under **Settings** as `owner/name`.

### Setting it up
1. On GitHub, create a fine-grained personal access token that covers only the repositories you report to, with one permission: "Issues: Read and write".
2. Add it to `wp-config.php`: `define( 'NHRROB_ISSUE_REPORTER_TOKEN', 'your token' );`. You can also paste it under Tools → Report Issue → Settings, which stores it in the database.
3. On the same Settings tab, fill in the repository of each plugin that does not name one itself.

Only users who can activate plugins can report an issue or change the settings.

== External services ==
This plugin connects to the GitHub API (api.github.com), a service of GitHub, Inc. It does so only when a user who can activate plugins:

- types a title in the report form: the title is sent to search for similar open issues in the chosen plugin's repository;
- sends the report form: the title, the text typed in the form and the environment details shown in the form are sent to open the issue;
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
Its three options: the settings, the stored token and the list of sent reports. Issues on GitHub are not touched.

== Changelog ==

= 0.1.0 =
* First version: the report form, repository detection and settings, the similar-issue check and the list of sent reports.
