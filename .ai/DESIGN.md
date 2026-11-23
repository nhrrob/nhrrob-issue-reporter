# DESIGN — Report Issue admin app ("Aurora")

> Dev-only document. Implemented in `admin/src/style.scss` (tokens and layout) and `admin/src/components/` (primitives). The chosen mockups are in `docs/design/` (Aurora, chosen on 2026-11-23 over the earlier sibling-shell options and two other premium looks). Where the mockups and this file differ, the code follows this file.

## 1. Direction

A premium product look, not the shared gradient-bar shell of the older plugins: a full-height white sidebar, a quiet top bar, cards with soft shadows, and one accent, **indigo to violet**, used for the primary button, the logo, the active menu item, numbered steps and focus rings. Dark mode is the same design on near-black with a periwinkle accent (the "Carbon" mockup), never a separate look. wp-admin's font stack with Inter first if the computer has it; no web font is loaded (WordPress.org plugins may not call Google Fonts). No framework, no icon font: CSS custom properties and inline SVG only.

The name shown in the UI is **Report Issue**. "NHR" never appears.

## 2. Tokens (`.nhrrob-issue-reporter-app`, prefix `--ir-`)

| Token | Light | Dark | Use |
|---|---|---|---|
| `--ir-accent` / `--ir-accent-text` | `#5b52f0` / `#4338ca` | `#8b95ff` / `#aab2ff` | focus, switches, active tab line / links and accent text (passes 4.5:1) |
| `--ir-accent-soft` | `#eef0ff` | `#1d2036` | active menu item, step numbers, tiles |
| `--ir-cta` / `--ir-logo` | indigo → violet gradients | same hues, lighter | primary button / logo tile |
| `--ir-bg` / `--ir-surface` / `--ir-field` | `#f5f6fb` / `#fff` / `#f8f9fd` | `#0b0c0f` / `#131419` / `#0f1014` | page / card / inputs |
| `--ir-side*` | white sidebar, `#eef0ff` active | `#08090b` sidebar, `#1a1c23` active | sidebar |
| `--ir-track` | `#eef0f6` | `#1e2027` | segmented-control track, empty bars |
| `--ir-border` / `-strong` | `#e8eaf2` / `#d7dae7` | `#22242c` / `#2e313b` | |
| `--ir-text` / `--ir-muted` | `#14162b` / `#5f6480` | `#eceef3` / `#9095a3` | muted passes 4.5:1 on the surface |
| `--ir-ok` / `--ir-closed` / `--ir-warn` / `--ir-bad` (+ `-bg`) | green / violet / amber / red | brighter | open / closed (GitHub's colours) / attention / errors |
| `--ir-r` / `--ir-rs` | 16px / 12px | same | card / control radius |

**Dark theme:** tokens redefined under `@media (prefers-color-scheme: dark)` guarded by `:not([data-theme="light"])`, and again under `[data-theme="dark"]`, so the top-bar switch wins both ways. `html:has(...)` paints wp-admin's canvas. The choice is kept in `localStorage` (`nhrrob-issue-reporter-theme`).

**Type:** screen title 26px/700; card and step titles 14–15px/600; stat numbers 28px/700 tabular; labels and help 12–13px muted; repositories in the mono stack.

## 3. Components

`components/ui.js`: Icon, ScreenHeader (title, lede, actions), Panel (card with optional header and foot), Step (numbered form section), Pill (`ok | closed | warn | info | off`, always with a dot), Button (`primary` gradient, `secondary`, `ghost`, `link`, `danger`, `small`), Segmented (a track of buttons with counts), Switch, SearchBox, Stat (icon tile, label, number), Field, Note, Loading, External.
`components/TargetPicker.js` (searchable, grouped, arrow keys and Enter), `Shots.js` (thumbnails, drop/paste/browse zone), `EnvCards.js` (one card and switch per environment line), `AppShell.js`, toasts.

## 4. Screens

- **Shell:** sidebar with the brand (the site's name under it), groups "Reports" (New report, Sent with the open count) and "Setup" (Settings), and the GitHub connection card at the bottom; top bar with a breadcrumb and the theme switch.
- **New report:** four numbered steps (Where it goes, What happened, Screenshots, Environment) and a footer saying where it goes, with Clear and Send (⌘/Ctrl+Enter). Right column: Live preview, Possible duplicates with "Comment instead". The typed text is a draft in `localStorage` (`nhrrob-issue-reporter-draft`) until sent or cleared.
- **Sent:** four stats, a toolbar (state, plugin, search, sort; kept in the address as `ir_state`, `ir_repo`, `ir_q`, `ir_sort`), rows with a state tile and pill, Comment opening a box under its row.
- **Settings:** tabs. GitHub: connection card, three-step checklist, token form (or a note when it is in `wp-config.php`). Repositories: filters (has a repository, not set, set here, all), search, groups, a field per row saved on blur; other repositories below.
- **Inbox** (sidebar group "Client feedback"): New, Sent and Done with counts in the header; a list with search on the left (text, who, page, age, kind and error tags); the chosen feedback on the right: screenshot ("Open full size"), facts (from, page, browser, screen, sent), console box, kind and severity as segmented controls, then "Send to GitHub" (repository picker, title from the first sentence, the text, the tick for page, screenshot and errors, "What will be sent"), and a footer with Delete (asks once, in place), Mark done or Move back to New, and Send to GitHub.
- **Settings → GitHub**: "Connect GitHub" first (a code in large mono type, Copy, Open GitHub, Cancel), then "Or use a token" with three steps and the pre-filled "Create a token on GitHub" link.
- **Settings → Client widget**: the switch, roles as checkboxes, the floating-button switch, where feedback goes, the captured chips, Save.

Below 1100px the Report columns and the Inbox stack; below 782px the sidebar becomes a row above the content.

## 5. Copy

Plain words from the site owner's side. Say what will be sent and where. Errors say what went wrong and what to do next.

## 6. The feedback widget

Inside a shadow root, with its own copy of the tokens (`admin/src/widget/style.scss`), light and dark, following the system or the switch in its panel (same `localStorage` key as the app).

- A floating "Feedback" pill bottom right on the site (CTA gradient), and the admin-bar item "Report an issue" everywhere.
- Open: a dimmed overlay; the screenshot on the left with a floating toolbar (box, arrow, draw, text, blur, Undo); a 380px panel on the right: "Send feedback", the marks count, "What's wrong?", Kind (Bug, Change, Question), "Added for the developer" chips that switch off, the reviewer's initial and name, Send. Marks are drawn in orange `#ea580c`, text with a white halo, blur as pixels.
- Below 782px the panel goes under the screenshot. Escape closes. Ctrl or Cmd + Enter sends.

## 7. Planned (drawn on the canvas, not built)

Replying to the reviewer from the Inbox; steps as a reorderable list; Templates; ⌘K search.
