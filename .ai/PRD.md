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
5. **Similar open issues** are shown before sending; the second click sends anyway.
6. **Sent tab**: every issue opened from this site with its link and state.
7. **Token**: a fine-grained personal access token with "Issues: Read and write", from `wp-config.php` (preferred) or the Settings tab.
8. Only users who can activate plugins; nonce and capability on every action.

## Decisions

| Decision | Why |
|---|---|
| Server-rendered screen, no JavaScript or build step | Three small forms. My other plugins use React for their screens because they are apps; this one is not. Revisit if screenshots or live duplicate search are added. |
| The repository is not guessed from `readme.txt` | A readme links to many GitHub repositories (libraries, credits). Guessing wrong would file my bug in a stranger's repository. |
| The plugin's name and version are added on the server at send time | The form has no script, so the environment box cannot change when another plugin is chosen. |
| A personal access token, not "Sign in with GitHub" | One user, own repositories. OAuth needs a registered app and a callback, which only a public release justifies. |
| The report's text is not stored | It is on GitHub. Storing it again would put support conversations into the options table. |
| No abilities (AI agents, MCP) yet | My other plugins expose read-only abilities. Here the only useful ability would write to GitHub, and that should stay a human's click for now. |

## Left out on purpose

- Screenshots and attachments (GitHub's API has no upload endpoint for issue images).
- Labels, assignees and issue templates.
- Comments on an existing issue from the dashboard.
- Trackers other than GitHub.
- Reporting on themes or on WordPress itself.
- A network-wide screen on multisite; each site has its own settings and list.

## Candidates for a paid version

- Screenshots, uploaded to the media library or a gist and linked.
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
