# WordPress + Ultimate Member, managed with an AI agent

Proof of concept for a client meeting. Static site, no build step.

| Path | Content |
| --- | --- |
| `/` | Start page with links to both parts |
| `/erklaerung/` | Part 1: short tour of WordPress, Ultimate Member, and how an AI agent manages them (MCP, REST API, WP-CLI) |
| `/demo/` | Part 2: live demo (work in progress) |

## Languages

German is the default. English is available with the switcher in the top bar
or with `?lang=en`. Texts live in one file per page (e.g.
`erklaerung/texte.js`). To add a language, add one more block there; the
switcher button appears by itself.

## Deploy

Cloudflare Pages, connected to this repository. No build command, output
directory `/`.
