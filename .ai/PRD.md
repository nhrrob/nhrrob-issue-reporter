# NHR Issue Reporter — product notes

## The problem

I run my own plugins on my own sites. When one misbehaves I notice it in the dashboard, and the fix starts with an issue in that plugin's GitHub repository. Today that means opening GitHub, finding the repository, and copying versions by hand. Most of the time I do not do it, and the problem is forgotten.

## Who it is for

- **Now:** me, on my own sites, reporting on my own plugins. Private repository.
- **Next (decided 2026-11-23):** agencies and freelancers running UAT on client sites. The client marks the spot on the page, the developer gets a clean issue: the Marker.io and BugHerd job, self-hosted, straight to GitHub, without a monthly fee. The widget, annotation and an Inbox go into this free plugin (they are free elsewhere); see "Free and Pro" below.

## What 0.1 does

1. **A form under Tools → Report Issue**: plugin, title, what happened, steps to reproduce.
2. **A "Report issue" link under each plugin** in the Plugins list whose repository is known.
3. **Repository per plugin**: from the plugin's header (`GitHub Plugin URI`, or a `Plugin URI` / `Update URI` on github.com), otherwise typed in Settings.
4. **Environment attached**: plugin, WordPress and PHP versions, theme, active plugins. Shown in the form, editable, never the site address.
5. **Similar open issues** are listed beside the form as soon as a plugin and a title are there.
6. **Sent tab**: every issue opened from this site with its link and state.
7. **Token**: a fine-grained personal access token with "Issues: Read and write", from `wp-config.php` (preferred) or the Settings tab.
8. Only users who can activate plugins; nonce and capability on every action.

## Decisions

| Decision | Why |
|---|---|
| A React app with the same shell as my other plugins | First built server-rendered; rebuilt on 2026-10-11 because it should look and behave like its siblings: app bar, side nav, toasts, light and dark. It also made the similar-issue search live. |
| The repository is not guessed from `readme.txt` | A readme links to many GitHub repositories (libraries, credits). Guessing wrong would file my bug in a stranger's repository. |
| The Environment box holds exactly what is sent | The plugin's own line is put into the box when a plugin is chosen, so nothing is added out of sight on the server. |
| A personal access token, not "Sign in with GitHub" | One user, own repositories. OAuth needs a registered app and a callback, which only a public release justifies. |
| The report's text is not stored | It is on GitHub. Storing it again would put support conversations into the options table. |
| Abilities for AI agents and MCP, including ones that write | First left out because the only useful ability writes to GitHub. Added on 2026-10-16 so an agent can report for me through REST or MCP instead of running code on the site. Each ability runs the REST route the form uses, as the person logged in, so it checks and sends exactly what the form would. |

## Added in 0.3.0

Each was first left out; the decision log in `docs/DECISIONS.md` has why it came in.

- **Themes, must-use plugins and other repositories** as targets (#6).
- **Comments** on an existing issue: from a similar issue in the form, from the Sent tab, and as an ability (#12).
- **Screenshots**, uploaded to the media library and linked, after a tick that the link shows the site's address (#5). GitHub's API has no upload endpoint for issue images, and committing them would need far more token power.
- **A filter by plugin** on the Sent tab (#4).

## Added in 0.4.0

The "Aurora" redesign (#15), light and dark, from the mockups in `docs/design/` (`.ai/DESIGN.md`):

- A sidebar with the sections and the GitHub connection; numbered steps in the form.
- A searchable picker for the plugin, theme or repository; Markdown buttons; a drop, paste or browse zone for screenshots.
- Each environment line has its own switch, or the whole is edited as text; the preview and what is sent follow it.
- A live preview of the issue, and "Comment instead" on possible duplicates.
- The typed report is a draft in the browser until it is sent or cleared.
- Sent: totals, filters by state and plugin, search, sort, kept in the page address (#16).
- Settings: a GitHub tab with the setup steps, and a Repositories tab with search and filters (#16).

## Planned for the free plugin (UAT)

Drawn on the canvas (`docs/design/AuroraWidget.dc.html`, `AuroraInbox.dc.html`), not built:

- **A feedback widget on the site** for chosen roles: mark a spot (box, arrow, draw, text, blur), describe it, pick bug, change or question. Page, browser, screen size and console errors are added.
- **An Inbox in wp-admin** where that feedback waits; the admin sends it to GitHub, replies or closes it. Nothing reaches GitHub without the admin acting.
- **Type and severity** as GitHub labels.

These change three rules in `CLAUDE.md` (nothing on the front end; only admins; the person acting is the one sending), so each gets a row in `docs/DECISIONS.md` when it is built.

## Left out on purpose

- Assignees. (Labels for type and severity are planned above; templates move with them.)
- Trackers other than GitHub (a Pro candidate).
- Reporting on WordPress itself.
- A "Report issue" link on Appearance → Themes: that screen is drawn by JavaScript and has no filter for its links. Themes are reported from the form.
- A network-wide screen on multisite; each site has its own settings and list.

## Free and Pro

The market check and the Pro plan are in [nhrrob/nhrrob-issue-reporter-pro](https://github.com/nhrrob/nhrrob-issue-reporter-pro) (`docs/market.md`, `docs/PRD.md`), from #17 and #18. In short: the widget, annotation, console capture, Inbox, labels and templates, and "Sign in with GitHub" are free in other plugins or needed for WordPress.org, so they are free here. Pro candidates, for the owner to confirm: guest reviewers without an account, more trackers (Jira, Linear, GitLab, Trello) with status sync, and PHP errors turned into reports.

## Before this can be public (done 2026-11-23)

- "Sign in with GitHub", or very clear token instructions with screenshots. Done as instructions: the checklist on Settings → GitHub and the readme's setup steps.
- A decision on reporting to repositories the site owner does not control: today any plugin with a GitHub address in its header gets the link. Decided: allowed, see `docs/DECISIONS.md`.
- WordPress.org assets, Plugin Check on a production copy, the deploy workflows my other plugins have. Done: `.wordpress-org/`, `plugin-check.yml`, the deploy step in `release.yml`.
- Translations template. Not needed: WordPress.org builds the language packs from the code, the script's strings included.
