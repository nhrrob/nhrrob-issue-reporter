# NHR Issue Reporter — product notes

## The problem

I run my own plugins on my own sites. When one misbehaves I notice it in the dashboard, and the fix starts with an issue in that plugin's GitHub repository. Today that means opening GitHub, finding the repository, and copying versions by hand. Most of the time I do not do it, and the problem is forgotten.

## Who it is for

- **Now:** me, on my own sites, reporting on my own plugins. Private repository.
- **Later, maybe:** plugin authors who want their users to report well-formed issues. That is a different product (see "Candidates for a paid version") and is not built.

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

## Left out on purpose

- Labels, assignees and issue templates.
- Trackers other than GitHub.
- Reporting on WordPress itself.
- A "Report issue" link on Appearance → Themes: that screen is drawn by JavaScript and has no filter for its links. Themes are reported from the form.
- A network-wide screen on multisite; each site has its own settings and list.

## Candidates for a paid version

- Labels and issue templates per repository.
- A report form for a client's users: they describe the problem, an admin reviews and sends it.
- "Sign in with GitHub" in place of a pasted token.
- GitLab, Linear, Jira.
- Collecting PHP errors raised from the plugin's own files and offering them in the form.

## Before this can be public

- "Sign in with GitHub", or very clear token instructions with screenshots.
- A decision on reporting to repositories the site owner does not control: today any plugin with a GitHub address in its header gets the link.
- WordPress.org assets, Plugin Check on a production copy, the deploy workflows my other plugins have.
- Translations template.
