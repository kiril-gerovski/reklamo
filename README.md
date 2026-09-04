# Reklamo.bg

Online shop for promotional packages branded with the customer's logo — **Variant A** from
`docs/Reklamo-4-varianta-za-izgrazhdane.docx`: WordPress + WooCommerce, bank transfer only,
custom-built theme. WooCommerce is the only third-party plugin; everything else is our own code
plus configuration from the dashboard.

Full plan: [`docs/PLAN.md`](docs/PLAN.md). Design mockups: [`design/`](design/).

Convention: code, comments and docs are in English. Site content (pages, products, menus,
emails) is Bulgarian — that is data, seeded by `scripts/seed.sh`.

## What is in the repo

Only our code. WordPress core and WooCommerce are installed by `scripts/setup.sh` and are
gitignored, so the owner can still update them from the dashboard.

```
wp-content/themes/reklamo/        theme — presentation only
wp-content/plugins/reklamo-core/  plugin — all business logic (statuses, uploads, emails)
scripts/seed.sh                   THE site configuration, as WP-CLI commands
scripts/worktree/                 per-branch isolated stacks (wt-new / wt-build / wt-push / wt-merge / wt-drop)
docker-compose.yml                local environment (Apache, MariaDB, Mailpit)
```

## Local environment

Requirements: Docker + Compose v2. No PHP on the host.

```bash
cp .env.example .env      # optional — setup.sh does it for you
scripts/setup.sh          # zero → working Bulgarian store
```

The stack listens on `127.0.0.1` only. From your own machine, open a tunnel:

```bash
ssh -p 22022 -L 8080:127.0.0.1:8080 -L 8025:127.0.0.1:8025 dev@178.104.78.114
```

| What | Where |
|---|---|
| Storefront | http://localhost:8080 |
| Admin | http://localhost:8080/wp-admin — credentials in `.env` |
| Mailpit (every outgoing email) | http://localhost:8025 |

## Everyday commands

```bash
scripts/wp <command>          # WP-CLI, e.g. scripts/wp plugin list
scripts/seed.sh               # re-apply configuration (idempotent)
scripts/reset.sh              # destroy everything and rebuild from zero — THE MASTER TEST
scripts/lint.sh               # PHPCS + WordPress Coding Standards
scripts/make-fixtures.sh 150  # test files with real magic bytes (AI/PSD/CDR/EPS/SVG)
docker compose logs -f wp
```

## Working on a branch in an isolated stack

```bash
scripts/worktree/wt-new.sh feature/x      # worktree + second stack on :8081 (Mailpit :9081)
scripts/worktree/wt-build.sh feature/x    # restart + cache flush; --seed / --lint / --reset
scripts/worktree/wt-push.sh feature/x -m "msg"
scripts/worktree/wt-merge.sh feature/x    # leaves the merge STAGED — you commit it
scripts/worktree/wt-drop.sh feature/x --yes
```

The same commands exist as `/wt-new`, `/wt-build`, `/wt-push`, `/wt-merge`, `/wt-drop` in
Claude Code. Details: [`scripts/worktree/README.md`](scripts/worktree/README.md).

## The configuration rule

The database is not in git — **`scripts/seed.sh` is**. Every setting you click in the dashboard
must become a line in `seed.sh`, otherwise it does not exist. `scripts/reset.sh` proves that
nothing lives only in the local database.

The PHP limits in `config/php/uploads.ini` are deliberately low (64M) to mimic shared hosting.
Do not raise them to "make a test pass".

## Translations

Source strings in the theme and plugin are English, wrapped in `__( '…', 'reklamo' )`. Bulgarian
lives in `wp-content/{themes,plugins}/*/languages/bg_BG.po`. After editing a `.po`:

```bash
scripts/wp i18n make-mo wp-content/themes/reklamo/languages
```
