# Impact Flow — Portfolio child theme

The WordPress child theme for Alex Marek's Impact Flow services and portfolio
site. It runs on the shared
[ImpactFlow parent theme](https://github.com/alexmarek/impact-flow-theme) and
currently pins parent release `v2.2.0-alpha.18` in
[`.impact-flow-parent-version`](.impact-flow-parent-version).

Maintained by **[Alex Marek — Infinity Seeker](https://github.com/alexmarek)**.

---

## Homepage architecture

The approved homepage design is retained in
[`assets/design/2026-09-20-impact-flow-homepage-v2-1.html`](assets/design/2026-09-20-impact-flow-homepage-v2-1.html).
[`tools/2026-09-23-build-homepage.php`](tools/2026-09-23-build-homepage.php)
converts its nine sections into core blocks and child variants of the parent
theme's patterns. The generated section patterns and complete homepage are in
[`patterns/`](patterns/); their shared editor and frontend presentation is in
[`assets/css/portfolio-home.css`](assets/css/portfolio-home.css).

WordPress stores inserted pattern content in the database. Deploying this
repository updates the pattern definitions, templates and presentation, but it
does not replace an existing page's saved blocks or Site Editor overrides.

---

## Stack

- **[Vite](https://vitejs.dev/)** for HMR + production build
- **SCSS** with Tailwind variables (inherited from parent) injected via Vite/PostCSS
- **GitHub Actions** for Hostinger deployment

---

## Requirements

- Node `^24.9.0`, npm `^11.6.1`
- The `impact-flow-theme` parent directory installed alongside this child

---

## Install

```bash
npm install
```

Create `.env.local` with your local WordPress URL:

```
IMPACTFLOW_LOCAL_URL=http://impact-flow.local
```

Activate **Impact Flow Portfolio** in **Appearance → Themes**. Its
`Template: impact-flow-theme` header must match the parent directory exactly.

---

## Scripts

| Command | Description |
|---|---|
| `npm run dev` | Vite dev server on `127.0.0.1:5174` with HMR |
| `npm run prod` | Production build into `dist/` |
| `npm run preview` | Serve the production build |

## Development workflow

The child has its own Vite dev server (port 5174) running alongside the parent's (port 5173). For HMR to work:

1. Start the parent: `cd ../impact-flow && npm run dev`
2. Start the child: `cd . && npm run dev` (in a second terminal)
3. Open the site with `?vite_dev=1` appended to the URL — both servers' `@vite/client` and entrypoints get enqueued

If `localhost:5173` shows the WordPress site instead of the Vite dev server, your `.env.local` is missing or the proxy isn't matching. See the parent theme's `readme.md` for the full troubleshooting table.

### Per-developer env vars (in `.env.local`)

| Var | Default | Purpose |
|---|---|---|
| `IMPACTFLOW_LOCAL_URL` | `https://testing.local` | WordPress upstream the dev proxy targets |
| `IMPACTFLOW_VITE_PORT` | `5174` | Port the Vite dev server binds |
| `IMPACTFLOW_VITE_HOST_BIND` | `127.0.0.1` | IPv4 loopback only — Local by Flywheel's proxy defaults to IPv4 |

`.env.local` is gitignored — different per developer, never committed.

## Asset loading

`npm run prod` writes the Vite build to `dist/` and emits
`dist/.vite/manifest.json`. The portfolio homepage stylesheet is generated
separately and loaded directly by `functions.php` on both the frontend and in
Gutenberg so both views use the same presentation.

`dist/` is build output and is git-ignored — never commit it.

---

## Structure

```
impact-flow-child/
├── assets/css/portfolio-home.css       Generated editor/frontend presentation
├── assets/design/                      Approved design sources
├── patterns/                           Homepage and nine section variants
├── parts/ and templates/               Header, footer and front-page shell
├── tools/2026-09-23-build-homepage.php Pattern and CSS generator
├── .github/workflows/                  Validation and production deployment
├── .impact-flow-parent-version         Immutable parent release pin
├── functions.php                       Portfolio asset registration
├── style.css                            Theme metadata
└── theme.json                           Child theme settings
```

---

## Working on the homepage

Edit the approved design source, then run the homepage builder. Use `--apply`
only in the Local WordPress installation when the generated block tree should
replace its configured static front page. Keep released parent pattern
structures stable; add portfolio-specific variants in this child.

---

## Deployment

Every push and pull request runs
[`deploy.yaml`](.github/workflows/deploy.yaml) to install dependencies, build
the Vite bundle, lint theme PHP and confirm that generated homepage files match
their source. It does not deploy automatically.

Running the workflow manually with `confirm_production` enabled deploys the
pinned parent and this child to the `production` GitHub environment. That
environment supplies the `SITE_PATH` and `SITE_URL` variables. The production
target is currently:

- `https://lightskyblue-skunk-164969.hostingersite.com/`
- `/home/u764292843/domains/lightskyblue-skunk-164969.hostingersite.com/public_html`

Required repository secrets:

- `SSH_PRIVATE_KEY` — the shared Hostinger deployment key used across Alex's sites
- `PARENT_REPO_PAT` — fine-grained token with read access to the private parent repository

The shared secrets are entered once in this repository. GitHub environment
protection rules provide an optional production approval gate.

The workflow deploys theme files only. It does not migrate the WordPress
database, media, navigation, plugins, WooCommerce settings, forms or Site
Editor records. A separate staging environment and workflow target can be added
later without changing the production credentials. See
[`docs/2026-09-23-hostinger-production-deployment.md`](docs/2026-09-23-hostinger-production-deployment.md)
for setup and the first production release sequence.

---

## License

Proprietary. © Alex Marek (Infinity Seeker).
