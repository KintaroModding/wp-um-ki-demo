# WordPress + Ultimate Member, managed with an AI agent

Proof of concept for a client meeting. Static site, no build step.

| Path | Content |
| --- | --- |
| `/` | Start page with links to both parts |
| `/erklaerung/` | Part 1: short tour of WordPress, Ultimate Member, and how an AI agent manages them (MCP, REST API, WP-CLI) |
| `/demo/` | Part 2: live demo — real WordPress + Ultimate Member in the browser (WordPress Playground) |

## Live demo

`demo/index.html` loads `demo/blueprint.json`, inlines the files it points to,
and starts WordPress Playground in the page. Every start builds the site fresh:

| File | Purpose |
| --- | --- |
| `demo/wp/demo-daten.json` | Data: fields, registration and profile forms, roles, directory, sample members |
| `demo/wp/demo-setup.php` | Builds the Ultimate Member setup from the data file |
| `demo/wp/demo-anpassungen.php` | Must-use plugin: custom behavior (role by profile type, version badge) |
| `demo/aenderungen.json` | Change log shown on the demo page and in WordPress |

Most changes are a small edit in `demo-daten.json`. Add a new entry to
`aenderungen.json` with each change, so the new version is visible.

## Languages

German is the default. English is available with the switcher in the top bar
or with `?lang=en`. Texts live in one file per page (e.g.
`erklaerung/texte.js`). To add a language, add one more block there; the
switcher button appears by itself.

## Deploy

Cloudflare Pages, connected to this repository. No build command, output
directory `/`.
