# ImpactFlow — Child Theme

Boilerplate for client-specific child themes built on top of the **[ImpactFlow parent theme](../impact-flow)**.

Maintained by **[Alex Marek — Infinity Seeker](https://github.com/alexmarek)**.

---

## What this is for

The child theme layers per-client logo overrides, brand colours, and any custom CSS/JS on top of the parent theme. The parent provides the entire block system, templates, and design tokens — you only need to override what actually differs per client.

This repo is a **template**. After cloning it for a client, replace `APP-NAME` in `.github/workflows/deploy.yaml` with the target Hostinger application name, and add the GitHub repository secrets listed below.

---

## Stack

- **[Vite](https://vitejs.dev/)** for HMR + production build
- **SCSS** with Tailwind variables (inherited from parent) injected via Vite/PostCSS
- **GitHub Actions** for Hostinger deployment

---

## Requirements

- Node `^24.9.0`, npm `^11.6.1`
- The `impact-flow` parent theme installed and active in the same WordPress

---

## Install

```bash
npm install
```

Set `localURL` in `vite.config.js` to your local WordPress URL (e.g. `http://impact-flow-child.local`).

Activate the child theme in **Appearance → Themes** — it declares `Template: impact-flow`, which makes WordPress automatically fall back to the parent for any file that isn't overridden here.

---

## Scripts

| Command | Description |
|---|---|
| `npm run dev` | Vite dev server with BrowserSync + HMR |
| `npm run prod` | Production build into `dist/` |
| `npm run preview` | Serve the production build |

---

## Asset loading

`npm run prod` writes the build to `dist/` and emits `dist/.vite/manifest.json`. `includes/vite-config-child-theme.php` reads that manifest on `wp_enqueue_scripts` and enqueues the child CSS/JS, declaring the parent handles `app-parent-style-build` and `app-parent-script-main` as dependencies — so the parent styles load first.

`dist/` is build output and is git-ignored — never commit it.

---

## Structure

```
impact-flow-child/
├── assets/
│   ├── js/          Brand-specific JS (entry: child-theme.js)
│   └── scss/        Brand-specific styles (entry: child-theme-styles.scss)
├── dist/             Build output (git-ignored)
├── includes/
│   └── vite-config-child-theme.php    Vite enqueue helper
├── .github/workflows/deploy.yaml       Hostinger deployment
├── child-theme.js    Vite JS entry
├── functions.php     Just includes vite-config-child-theme.php
├── style.css         Theme metadata header (Template: impact-flow)
├── package.json
├── postcss.config.cjs
└── vite.config.js
```

---

## Customising for a client

1. Replace the logo files referenced by the parent theme settings (configured in **Appearance → ImpactFlow Settings → Branding**).
2. Add per-client brand variables or extra CSS in `assets/scss/`.
3. Add any client-specific JS in `assets/js/` and import it from `child-theme.js`.
4. Configure the deployment secrets in GitHub.

---

## Deployment

`.github/workflows/deploy.yaml` has two jobs:

1. **deploy** — builds, then rsyncs over SSH to the configured Hostinger target.
2. **sync_staging** — merges `main` into `staging` after a successful production deploy.

Required repository secrets:

- `HOSTINGER_SSH_HOST`
- `HOSTINGER_SSH_USER`
- `HOSTINGER_SSH_KEY` (private key)
- `HOSTINGER_TARGET_PATH` (e.g. `/home/user/public_html/wp-content/themes/impact-flow-child/`)

---

## License

Proprietary. © Alex Marek (Infinity Seeker).
