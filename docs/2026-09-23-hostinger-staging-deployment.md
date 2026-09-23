# Hostinger production deployment

## Target

- URL: `https://lightskyblue-skunk-164969.hostingersite.com/`
- SSH host: `195.35.49.54`
- SSH user: `u764292843`
- SSH port: `65002`
- WordPress document root:
  `/home/u764292843/domains/lightskyblue-skunk-164969.hostingersite.com/public_html`
- Parent destination:
  `<document-root>/wp-content/themes/impact-flow-theme/`
- Child destination:
  `<document-root>/wp-content/themes/impact-flow-child/`

Hostinger File Manager displays this installation beneath
`ad583bda6872eb6b/files/domains/lightskyblue-skunk-164969.hostingersite.com/public_html`.
The SSH workflow uses the `/home/u764292843/domains/...` mapping already proven
by the Randa Galal child-theme deployment on the same Hostinger account.

## GitHub setup

Add these repository secrets under **Settings → Secrets and variables →
Actions** in `alexmarek/impact-flow-child`:

- `SSH_PRIVATE_KEY`: the existing generic Hostinger deployment private key
  used by the working Randa workflow. GitHub cannot share a personal-account
  repository secret with another repository, so enter the same value here
  once. Do not commit it.
- `PARENT_REPO_PAT`: a fine-grained GitHub token restricted to
  `alexmarek/impact-flow-theme` with **Contents: read** permission.

Create a GitHub environment named `production` and add these non-secret
environment variables:

- `SITE_PATH`:
  `/home/u764292843/domains/lightskyblue-skunk-164969.hostingersite.com/public_html`
- `SITE_URL`: `https://lightskyblue-skunk-164969.hostingersite.com/`

The `hostingersite.com` installation is the production site. When its custom
domain is connected, update `SITE_URL`; update `SITE_PATH` only if Hostinger
also changes the document root. Configure a required reviewer where the GitHub
plan supports it.

## Workflow behaviour

Every push and pull request runs the `validate` job in
`.github/workflows/deploy.yaml`. It:

1. installs locked npm dependencies on Node 24;
2. creates the production Vite build;
3. lints deployable PHP files;
4. regenerates the homepage patterns and stylesheet, then fails if they differ
   from the committed files;
5. stores the production `dist/` directory as a seven-day build artifact.

No push deploys a website. To deploy, open **Actions → Validate and deploy to
Hostinger → Run workflow**, enable `confirm_production`, and run it from
`main`.

The production job verifies the pinned parent tag and commit, mirrors the parent
and child theme directories with `rsync --delete`, verifies the three required
theme files over SSH, and requests the production URL. Source designs, archives,
builder tools, documentation and Node development files stay out of the hosted
child theme.

## First production release

1. Confirm WordPress is installed at the production URL and that
   `wp-content/themes` exists. The workflow refuses to deploy if it does not.
2. Add the two GitHub environment secrets.
3. Run the manual production deployment.
4. In WordPress, activate **Impact Flow Portfolio**. Confirm that the parent is
   shown as `ImpactFlow` from the `impact-flow-theme` directory.
5. Move the local WordPress content and settings separately. GitHub Actions
   does not transfer the database, uploads, navigation, WooCommerce data,
   Fluent Forms records, plugin state or Site Editor customisations.
6. Re-save permalinks, verify the static front page, and check the homepage in
   Gutenberg as well as on the frontend.
7. Test desktop and mobile layouts, dark mode when implemented, navigation,
   enquiry delivery, WooCommerce checkout, privacy pages and transactional
   email before configuring production.

The complete homepage pattern is deployable source, but an existing page is a
database record. Updating `patterns/homepage.php` will not rewrite blocks that
have already been inserted into a staging or production page.

## Later staging environment

Add staging when a separate testing installation exists. Give it its own
GitHub environment, `SITE_PATH` and `SITE_URL`, then add a staging target to the
manual workflow. It can reuse the same repository secrets because both sites
belong to the same Hostinger SSH account.
