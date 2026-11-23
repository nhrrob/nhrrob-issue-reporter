=== NHR Issue Reporter – Client Feedback & Bug Reports ===
Contributors: nhrrob
Tags: feedback, bug report, uat, client feedback, github
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client feedback and bug reports with a marked-up screenshot, kept in WordPress and sent to GitHub when you choose.

== Description ==
Your client says "the button doesn't work". Which button, on which page, in which browser? NHR Issue Reporter puts a **Report an issue** button on every page of the site and the dashboard. The reviewer points at the problem on a screenshot, says what is wrong and sends it. You get the screenshot, the page, the browser, the screen size and the console errors in an Inbox in WordPress, and send it on to GitHub as a clean issue, with the WordPress, theme and plugin versions filled in.

It is self-hosted: feedback and screenshots stay on your site, and nothing goes to a third party until you send an issue to GitHub yourself.

### Client feedback and UAT
- **A Report button for the people you choose:** a "Report an issue" item in the admin bar, on the site's pages and on every dashboard screen, and a floating Feedback button on the site. Choose the roles under Settings → Client widget. Visitors never see it.
- **Mark the screenshot:** box, arrow, freehand, text, and blur for anything private. The screenshot is taken in the browser.
- **Captured with each report:** the page, the browser and system, the window size and the console errors. The reviewer sees each one and can switch any of them off before sending.
- **Bug, change or question:** the reviewer picks the kind.
- **An Inbox in WordPress:** feedback waits here, with the screenshot, the details and the console errors. Set a severity, send it to GitHub with labels, mark it done, or delete it. GitHub is optional: the Inbox works without it.

### Report to GitHub
- **Connect GitHub** with one button and a short code on github.com, or use a token limited to the repositories you choose. A link opens GitHub's token form already filled in.
- **New report** opens an issue in a plugin's, theme's or any repository from a form in four steps: where it goes, what happened, screenshots, environment. The versions of WordPress, PHP, the theme and the active plugins are added; each line has its own switch, and the site's address is never added.
- While you type the title, open issues with a similar title are listed. **Comment instead** adds to one of them rather than opening a duplicate.
- A live preview shows the issue exactly as it will be sent. What you type is kept as a draft in your browser.
- The **Sent** screen lists every issue opened from this site, open or closed, with filters, search and a Comment button.

### Which repository
A plugin's repository is read from its header: `GitHub Plugin URI`, or a `Plugin URI` or `Update URI` on github.com. A theme's from `GitHub Theme URI`, or a `Theme URI` or `Update URI` on github.com. For anything else, set it under **Settings → Repositories** as `owner/name` or a github.com address; it is checked with GitHub when saved.

### For AI agents and MCP clients
On WordPress 6.9 and later the plugin registers four abilities, for the Abilities API's REST routes (`/wp-json/wp-abilities/v1/`) or the MCP Adapter plugin:

- `nhrrob-issue-reporter/get-state`: the plugins, themes and repositories, whether GitHub is connected, the environment text and the sent reports, optionally narrowed by `repo`, `state` and `search`. Makes no request to GitHub.
- `nhrrob-issue-reporter/find-similar-issues`: open issues with a similar title.
- `nhrrob-issue-reporter/report-issue`: opens the issue, optionally with labels and screenshots.
- `nhrrob-issue-reporter/comment-on-issue`: adds a comment to an issue.

Each runs the same code as the form, as the person who is logged in. For a plugin without a repository, pass `repo`: it is checked with GitHub and saved.

### For developers
- `nhrrob_issue_reporter_capability`: who may report and manage.
- `nhrrob_issue_reporter_pre_send`: send a report somewhere other than GitHub.
- `nhrrob_issue_reporter_sent` and `nhrrob_issue_reporter_feedback_created`: after a report is opened, and after feedback arrives.
- `nhrrob_issue_reporter_state` and `nhrrob_issue_reporter_enqueue`, with the `nhrrobIssueReporter.app` JavaScript filter: add screens to the app.
- `nhrrob_issue_reporter_client_id`: use your own GitHub OAuth App for Connect GitHub.
- `nhrrob_issue_reporter_can_send`: let more people use the feedback widget.
- `nhrrob_issue_reporter_feedback_row`: change a piece of feedback as the Inbox shows it.

== Installation ==
1. Install the plugin from Plugins → Add New, or upload the zip, and activate it.
2. Under **Tools → Report Issue → Settings → GitHub**, click **Connect GitHub** and enter the code on github.com. Or create a token with **Create a token on GitHub** (one permission: "Issues: Read and write") and paste it, or add it to `wp-config.php`: `define( 'NHRROB_ISSUE_REPORTER_TOKEN', 'your token' );`.
3. Under **Settings → Repositories**, fill in the repository of each plugin or theme that does not name one itself.
4. For client feedback, switch on the widget under **Settings → Client widget** and choose who sees it.

Only users who can activate plugins can send reports to GitHub, read the Inbox or change the settings. Developers can change that with the `nhrrob_issue_reporter_capability` filter.

== External services ==
This plugin connects to GitHub, a service of GitHub, Inc., only when a user who can activate plugins acts in its screen or runs one of its abilities. Feedback from the widget is stored on your site and sent nowhere until such a user sends it to GitHub.

GitHub sign-in (github.com/login), when you click **Connect GitHub**:

- the plugin's public client ID and the access asked for are sent to get a short code, which you enter on github.com;
- while the code is shown, the plugin asks GitHub every few seconds whether you approved it, and receives the token when you have.

The GitHub API (api.github.com), with the token you connected or configured:

- leaving the title field of the report form, or `find-similar-issues`: the title, to search for similar open issues;
- sending a report, from the form, the Inbox or `report-issue`: the title, the text, the environment details shown before sending and the labels;
- adding a comment: the text of the comment;
- screenshots: links to the images on your site are put in the issue, so it shows your site's address. Only after you tick the box that allows it;
- saving a repository that was not listed before, or passing `repo` to an ability: its name, to check that it exists;
- opening the Sent tab: the repository and number of each open issue, to ask whether it is still open.

Nothing is sent to GitHub in the background or for visitors.

- [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
- [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)

== Frequently Asked Questions ==

= Who can send feedback with the widget? =
Logged-in people with the roles you choose under Settings → Client widget; administrators and editors by default. The widget is off until you switch it on, and visitors never see it.

= Where are feedback and screenshots kept? =
On your site: feedback as private entries that only the Inbox shows, and each screenshot as an image with a random name that is kept out of the Media Library. Deleting feedback deletes its screenshot.

= Is the issue public? =
It is as public as the repository. In a public repository anyone can read it, so read the preview before sending. The page, the screenshot and the console errors can show your site's address, so they are only sent when you tick the box for them.

= Do I need GitHub? =
Not for the widget and the Inbox. GitHub is where you send the feedback you want a developer to fix.

= Can I report to a plugin I did not write? =
Yes, when its repository is on GitHub and your connection may open issues there. The issue is opened by your GitHub account, the same as opening it on github.com.

= What does "Connect GitHub" have access to? =
Your public repositories, or also your private ones if you tick that box. A token you create yourself can be limited to chosen repositories and the one permission the plugin needs.

GitHub's approval page names the app "NHR Issue Reporter" by nhrrob. Approve it only right after clicking Connect GitHub on your own site: anyone can show that page, so a link to it from somewhere else is not this plugin.

= Where is the token stored? =
In `wp-config.php` if you define `NHRROB_ISSUE_REPORTER_TOKEN` there, which is the safer place. Otherwise in the database, in an option that is not autoloaded. It is never printed in a page or sent anywhere but api.github.com.

= Does it slow down my site? =
No. Visitors get nothing from it. For the people the widget is on for, a small script loads, and the screenshot code only when they click the button. In the dashboard the app loads only on its own screen.

= What is removed when I delete the plugin? =
Its options (the settings, the stored token and the list of sent reports), all feedback and its screenshots, on every site of a multisite network. Issues on GitHub are not touched.

= Where is the source of the scripts? =
In the plugin's `admin/src` folder. `admin/build` is made from it with `@wordpress/scripts`: run `npm install` and `npm run build` (see `package.json`). `assets/errors.js` is not built.

== Screenshots ==

1. The feedback widget: mark the screenshot with a box, an arrow, freehand, text or blur, and say what is wrong.
2. The Inbox: the screenshot, the page, browser, window size and console errors, and sending it to GitHub with labels.
3. New report: where it goes, what happened, screenshots and environment, with a live preview and possible duplicates.
4. Sent: every issue opened from this site, with filters, search and whether each is open or closed.
5. Settings → GitHub: Connect GitHub with a code, or a token.
6. Settings → Client widget: who sees the widget.
7. The dark theme.

== Changelog ==

= 0.5.1 =
* Better: the Connect GitHub screen and the FAQ say what GitHub's approval page shows, so it can be told apart from a copy.
* Developers: the nhrrob_issue_reporter_can_send and nhrrob_issue_reporter_feedback_row filters.

= 0.5.0 =
* New: the feedback widget. A "Report an issue" item in the admin bar on the site and in the dashboard, and a floating Feedback button on the site, for the roles you choose.
* New: screenshot markup with box, arrow, freehand, text and blur; the page, browser, window size and console errors captured, each of which the reviewer can switch off.
* New: the Inbox. Feedback waits in WordPress; set the kind and severity, send it to GitHub with labels, mark it done or delete it.
* New: Connect GitHub with a code on github.com, and a link that opens GitHub's token form filled in.
* New: get-state takes state and search; report-issue takes labels; a plugin without a repository can be given one with repo, which is checked and saved.
* Better: when a plugin has no repository, the error says how to set one.
* New hooks for developers: nhrrob_issue_reporter_pre_send, nhrrob_issue_reporter_sent, nhrrob_issue_reporter_feedback_created, nhrrob_issue_reporter_state, nhrrob_issue_reporter_enqueue, nhrrob_issue_reporter_client_id, and the nhrrobIssueReporter.app JavaScript filter.

= 0.4.1 =
* The site's name is shown under Report Issue in the sidebar.

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
