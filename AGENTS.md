# reklamo

Online shop for promotional packages branded with the customer's logo — WordPress + WooCommerce,
bank transfer only, custom theme and plugin. WooCommerce is the **only** third-party plugin;
everything else is our own code plus dashboard configuration.

The repo holds only our code: `wp-content/themes/reklamo/` is presentation, and
`wp-content/plugins/reklamo-core/` holds all business logic — statuses, uploads, emails.
WordPress core and WooCommerce are installed by `scripts/setup.sh` and gitignored so the owner
can update them from the dashboard.

Read [`README.md`](README.md) for the environment, the everyday commands and how the site is put
together. [`docs/PLAN.md`](docs/PLAN.md) is the full plan and carries the settled decisions and
the verified environment facts. Going live: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Ground rules

- **After every change, update the docs.** `README.md` when behaviour or commands move,
  `docs/PLAN.md` for decisions and verified facts, `docs/DEPLOYMENT.md` for anything about going
  live, and this file when a ground rule changes. No exceptions.
- **No new plugins.** WooCommerce only. No Elementor, no ACF, no premium add-ons. Reach for the
  custom theme, the custom plugin, or dashboard configuration instead. Query Monitor is
  acceptable locally.
- **`scripts/seed.sh` is the configuration.** The database is not in git. Every setting clicked
  in the dashboard has to become a line in `seed.sh` or it does not exist. `scripts/reset.sh`
  destroys everything and rebuilds from zero, which is what proves nothing lives only in the
  local database — run it as the master test.
- **The low PHP limits are deliberate.** `config/php/uploads.ini` caps uploads at 64M to mimic
  shared hosting. Never raise them to make a test pass.
- **English source, Bulgarian content.** Identifiers, comments, docs and `__()` source strings
  are English. Bulgarian appears as site *content* seeded by `scripts/seed.sh`, and as
  translations in the `.po` files. The theme and the plugin use **different text domains**
  (`reklamo` / `reklamo-core`) — `WP_Textdomain_Registry` holds one path per domain, so sharing
  one silently drops the plugin's `.mo`.
- **Branch work goes in an isolated stack.** `scripts/worktree/wt-new.sh <branch>` brings up a
  second stack on `:8081`; `wt-build.sh` restarts and flushes. Do not test a branch against the
  main stack.

## Environment

Docker and Compose v2, no PHP on the host. The stack binds `127.0.0.1` only, so reaching it from
a workstation needs an SSH tunnel — the command is in `README.md`. MariaDB runs with
`innodb_buffer_pool_size=128M` from `config/mariadb/low-mem.cnf` because the VM is memory-tight;
stop other stacks before blaming performance on the code.

Browser automation runs on the VM through the `playwright-cli` skill, so e2e and screenshots can
reach `http://localhost:8080` directly with no tunnel. `unzip` and Python PIL are absent — use
`python3 -m zipfile` and `file` to inspect archives and images.

## Gotchas worth knowing before you touch these areas

`docs/PLAN.md` § "Verified environment facts" carries the full list. The ones that cost the most
time:

- The official `wordpress:*-apache` image does not run PHP as uid 1000 just because
  `APACHE_RUN_USER=#1000` is set — with no passwd entry Apache logs AH02155 and children stay
  root. Compose remaps `www-data` to uid 1000 in `command:` instead.
- Never `rm -rf` a bind-mounted directory while its container runs; the container keeps the old
  inode.
- Hook and API names that are easy to get wrong: the filter is `wc_order_is_editable`; block
  gateway JS reads `wc.wcSettings.getPaymentMethodData(name)`; the Store API fires
  `woocommerce_store_api_checkout_order_processed` after setting `pending` and preserves custom
  statuses set there.
- WC-CLI has no write command for `shipping_zone_location`, WooCommerce refuses a direct
  `update_option` on some admin task-list options, and the WP/WC sample pages are drafts — select
  them with `--post_name__in`, not `--name`.

## Conventions

Comments follow the global rule: default to none, and at most one short line where the logic is
genuinely not obvious. Rationale belongs in the response or the commit message, not inline.
