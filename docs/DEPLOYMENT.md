# Deployment — SuperHosting.bg

How Reklamo.bg goes live on a SuperHosting shared account and how updates ship afterwards. The
same repo runs locally in Docker and on the host natively; only the `.env` differs.

**What SuperHosting is, in the terms that matter here.** Bulgarian shared hosting on cPanel with
CloudLinux, Apache and `.htaccess`. PHP is chosen per account in *PHP Manager by SuperHosting*
(5.2 through 8.5 installed; the web default on saturn is 8.5, which the local stack matches). Every plan has WP-CLI preinstalled as the command `wp-cli`. SSH exists on the
**СуперПро** and **СуперХостинг** plans only, is off until enabled in the client profile, and
listens on **port 1022** with the cPanel user. Git and the cPanel `uapi` command are available in
that shell. Outbound ports 22, 25, 26 and 465 to *external* hosts are blocked; GitHub is reached
on `ssh.github.com:443` and mail goes through the account's own mail server.

Principle unchanged from local dev: **WordPress core and WooCommerce are installed on the server,
never committed. Our code (theme + plugin) is a git checkout the site symlinks into.
`scripts/seed.sh` is the site configuration and runs on the server too.**

```
/home/<user>/
├── public_html/                      WordPress core (WP-CLI), wp-config.php, uploads, .user.ini
│   └── wp-content/
│       ├── plugins/woocommerce/      installed by WP-CLI, version from versions.env
│       ├── plugins/reklamo-core  →   symlink to ~/reklamo/wp-content/plugins/reklamo-core
│       └── themes/reklamo        →   symlink to ~/reklamo/wp-content/themes/reklamo
├── reklamo/                          this git repository; .env here is the server .env
├── reklamo-private/                  customer logos & mockups — ABOVE the web root, never served
├── reklamo-backups/                  database dumps taken before every WP/WC version change
└── .reklamo/wp-cli.phar              only when the host's wp-cli is unusable
```

## The scripts

| Where | Script | Does |
|---|---|---|
| workstation | `scripts/deploy.sh install` | copies `bootstrap.sh` over SSH and runs it |
| workstation | `scripts/deploy.sh update [tag\|branch]` | runs `update.sh` on the server |
| workstation | `scripts/deploy.sh check [--mail-test a@b]`, `wp …`, `ssh` | health report, remote WP-CLI, shell |
| server | `scripts/host/bootstrap.sh <repo> [dir] [ref]` | deploy key for GitHub over 443, clone, hand over to `install.sh` |
| server | `scripts/host/install.sh` | zero → live site; idempotent, converges an existing install with its `.env` |
| server | `scripts/host/update.sh [ref] [--no-seed]` | fetch, maintenance mode, checkout, WP/WC pins, `seed.sh`, flush, `check.sh` |
| server | `scripts/host/check.sh [--mail-test a@b]` | read-only report: versions, symlinks, config, cron, web PHP probe, mail |

`scripts/deploy.sh` takes `DEPLOY_USER`, `DEPLOY_HOST` and optionally `DEPLOY_PORT` (1022),
`DEPLOY_DIR` (reklamo), `DEPLOY_REF` (main), `DEPLOY_REPO` (the checkout's `origin`) from the
environment; `.env.deploy` (copy `.env.deploy.example`, gitignored) fills whatever is not set. The
server scripts read `~/reklamo/.env`, created from `.env.server.example` on the first run.
`scripts/lib.sh` runs every `wp` call through `PHP_BIN` so the CLI uses the same PHP as the site;
the `php` on PATH is the server default and may be older.

## 0. Before the first deploy (clicks in cPanel, once)

| Step | Where | Notes |
|---|---|---|
| Enable SSH | my.superhosting.bg → Хостинг акаунти → Настройки → SSH достъп → Активиране | Credentials arrive by email. |
| Authorise your key | cPanel → SSH Access → Manage SSH Keys → Import → Authorize | Then `ssh -p 1022 <cpanel-user>@<domain>` works. |
| PHP version | cPanel → PHP Manager by SuperHosting → PHP 8.4 | Same major.minor as `PHP_BIN` in the server `.env` and as the `wordpress:*-php8.5` images in `docker-compose.yml`. `check.sh` compares web and CLI. |
| PHP directives | PHP Manager → Change PHP directives | `upload_max_filesize` ≥ 128M, `post_max_size` ≥ same, `max_execution_time` 120, `max_input_time` 300, `memory_limit` 256M. `install.sh` also writes `.user.ini`; `check.sh` shows which values the web server really applies. |
| Mailbox | cPanel → Email Accounts → `office@reklamo.bg` | WooCommerce sends from it; SMTP authenticates as it. |
| DNS | nameservers of the account (my.superhosting.bg → Настройки → DNS сървъри; `ns299`/`ns300` for saturn) | Until it propagates the site answers only on the account's IP. `install.sh` records that IP from cPanel as `WP_HOST_IP`, so `check.sh` probes work before DNS. The account IP is not the server hostname's IP. |

The database is **not** a manual step: `install.sh` creates it through cPanel's `uapi`
(`<user>_reklamo`). If `uapi` turns out to be unavailable in the shell, create it in cPanel →
MySQL Databases and fill `DB_NAME`, `DB_USER`, `DB_PASSWORD` in the server `.env`.

## 1. First deploy

```bash
DEPLOY_USER=<cpanel-user> DEPLOY_HOST=reklamo.bg scripts/deploy.sh install
```

or put the same values in `.env.deploy` once and call `scripts/deploy.sh install`.

What happens on the server:

1. `bootstrap.sh` writes a `~/.ssh/config` stanza pointing `github.com` at `ssh.github.com:443`,
   generates `~/.ssh/reklamo_deploy` and prints the public key. Add it as a **read-only deploy
   key** on the GitHub repository, press Enter, and it clones `~/reklamo` at `DEPLOY_REF`.
2. `install.sh` creates `~/reklamo/.env` from `.env.server.example` with the cPanel user filled
   in. To skip every prompt, copy `.env.server.example` to `.env.server` on the workstation
   (gitignored) and fill it in: `deploy.sh install` uploads it, `install.sh` merges the filled
   values into the server `.env` and deletes the upload. Whatever is still empty is asked for:
   site URL, admin login and email, mailbox and its password. `SMTP_HOST` defaults to the
   server's own hostname, whose TLS certificate matches (465/ssl). Re-uploading with a changed
   value updates the server; an empty value never erases what is there, so the generated
   database credentials survive.
3. Checks PHP ≥ 8.1 and the required extensions, finds WP-CLI, creates the database, downloads
   WordPress (`WP_VERSION`), writes `wp-config.php` with the production constants from `.env`
   (`chmod 600`), installs, installs the Bulgarian language pack, symlinks theme and plugin,
   installs WooCommerce (`WC_VERSION`), activates everything, writes `.user.ini`, adds the cron
   line, runs `seed.sh` and finishes with `check.sh`.
4. Prints the admin password **once** when it had to generate one. It is stored nowhere.

Every step is idempotent: re-run `scripts/deploy.sh install` after a failure or after editing
the server `.env` (for example a new mailbox password) and it converges.

Constants written to `wp-config.php`: `WP_HOME`, `WP_SITEURL`, `WP_ENVIRONMENT_TYPE production`,
`WP_DEBUG false`, `DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN`, `WP_MEMORY_LIMIT 256M`,
`WP_AUTO_UPDATE_CORE minor` (security releases apply themselves; plugins never auto-update, that is
seeded), `DISABLE_WP_CRON`, `REKLAMO_PRIVATE_DIR`, `REKLAMO_SMTP_*`. `REKLAMO_DISABLE_RATE_LIMITS`
is removed if present: the per-IP limits on the request form, the uploader and the approval page are
part of the site's protection.

Cron: `*/5 * * * * cd ~/public_html && /opt/cpanel/ea-php85/root/usr/bin/php wp-cron.php`. If the
shell cannot write the crontab, the script prints the line to paste into cPanel → Cron Jobs.

## 2. After the first deploy (once)

1. cPanel → **SSL/TLS Status** → AutoSSL for the domain and `www`. `WP_HOME` already says https.
2. cPanel → **Email Deliverability** → install the suggested **SPF** and **DKIM** records, add a
   **DMARC** TXT record: `v=DMARC1; p=quarantine; rua=mailto:office@reklamo.bg`.
3. `scripts/deploy.sh check --mail-test you@gmail.com` → expect `sent`, then check inbox **and**
   spam. Repeat to an abv.bg and a mail.bg address, the customers' providers. If 465/ssl is
   refused, set `SMTP_PORT=587`, `SMTP_SECURE=tls` in the server `.env` and re-run `install`;
   port 25 without encryption is SuperHosting's documented last resort.
4. WooCommerce → **Reklamo diagnostics** on the live site: storage *outside the web root*, `DOM`
   and `finfo` available, cleanup scheduled. Click **Run probe**; the uploader sends 2 MB chunks,
   so once 2 MB passes, uploads of any size work. Record the largest accepted size in
   `docs/PLAN.md`.
5. Enter the real values in the dashboard: WooCommerce → Настройки → Reklamo (phone, email,
   address, social, bank details), product prices and photos, page texts. `seed.sh` seeds these
   once and never overwrites them afterwards.
6. Walk the flow with a real address: homepage → package → request → upload → email → wp-admin →
   send mockup → approval link. Delete the test order.
7. Turn on WooCommerce → **Analytics**; Legacy Reports ignore our statuses.
8. cPanel → **Backup** (or JetBackup): confirm `~/reklamo-private` and `~/reklamo-backups` are in
   scope. The logo files exist nowhere else.

## 3. Shipping an update

```bash
scripts/deploy.sh update            # fast-forward the deployed branch
scripts/deploy.sh update v1.4.0     # deploy a tag (detached checkout)
scripts/deploy.sh update main       # back onto a branch
```

`update.sh` refuses a dirty checkout, fetches, prints the incoming commits, enables maintenance
mode, checks out the target, raises WordPress and WooCommerce to the versions in `versions.env`
(database dump to `~/reklamo-backups/` first, then `wp core update` / `wp plugin install --force`,
`update-db`, `wp wc update`), runs `seed.sh`, flushes caches and rewrite rules, leaves maintenance
mode and ends with `check.sh`. **It never lowers a version.** A site that is ahead of the pin,
because a minor security release applied itself or someone updated from the dashboard, is left
alone and reported, and the right response is to bump `versions.env` after testing locally. Code is symlinked, so PHP and CSS are live the moment the checkout
moves. Compiled `.mo` files are committed, nothing to build.

**Rollback** is `scripts/deploy.sh update <previous tag>`. WooCommerce database migrations are
forward-only, so a rollback across a `WC_VERSION` bump also needs the dump from
`~/reklamo-backups/` (`scripts/deploy.sh wp db import …`).

**Updating WordPress or WooCommerce**: change `versions.env`, keep the `wordpress:` image tag in
`docker-compose.yml` on the same WordPress major.minor, run `scripts/reset.sh` and `scripts/e2e.sh`
locally, commit, then `scripts/deploy.sh update`. Point releases (`7.1.1`) need no image change:
`setup.sh` raises the bind-mounted core to the pin, exactly as `update.sh` does on the host.

**When the host is ahead**: `WP_AUTO_UPDATE_CORE minor` lets WordPress apply security point
releases itself, so `check.sh` will occasionally report "WordPress 7.1.x ahead of versions.env".
That is the cue to bump `WP_VERSION`, run `scripts/setup.sh` and `scripts/e2e.sh` locally, and
commit. Never the other way round.

## 4. Everyday remote commands

```bash
scripts/deploy.sh check                       # health report, exit 1 when something needs attention
scripts/deploy.sh wp plugin list              # any WP-CLI command on the server
scripts/deploy.sh wp db export - | gzip > backup.sql.gz
scripts/deploy.sh ssh
```

On the server itself: `cd ~/reklamo && scripts/wp …` runs the same WP-CLI through `PHP_BIN`.

## 5. No SSH? cPanel Git fallback

Plans without SSH cannot run the scripts. cPanel → **Git™ Version Control → Create** → clone the
repository into `/home/<user>/reklamo`, and commit a `.cpanel.yml` that copies the two directories
into `public_html/wp-content/` on **Deploy HEAD Commit**:

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/<user>/public_html/wp-content
    - /bin/cp -R wp-content/themes/reklamo $DEPLOYPATH/themes/
    - /bin/cp -R wp-content/plugins/reklamo-core $DEPLOYPATH/plugins/
```

cPanel copies without deleting and skips dot-files. WordPress is then installed with *Manager for
WordPress* and configured by hand from `seed.sh`, which is a real cost: the SSH plan is cheaper.

## 6. Facts about SuperHosting the scripts rely on

From help.superhosting.bg:

- SSH: plans СуперПро and СуперХостинг, activation in the client profile, port 1022, cPanel user
  and password or an authorised key. cPanel's browser Terminal is available once SSH is on.
- WP-CLI is installed on every shared Linux server as `/usr/local/bin/wp-cli`.
- PHP CLI binaries: `/opt/cpanel/ea-phpXX/root/usr/bin/php`; the bare `php` is the server default
  and its `php.ini` is the system one, so the web PHP version chosen in PHP Manager does not
  change what `php` on the shell is. On saturn the web serves PHP 8.5 while the shell's bare `php`
  is 8.4, which is why `check.sh` compares the two instead of assuming. ea-php52 through ea-php85
  are installed.
- `/usr/bin/curl` is root-only (`-r-x------`) for hosting accounts. `wget` works, and PHP's curl
  extension is loaded. The host scripts use PHP streams for their HTTP probes and `wget` for the
  WP-CLI download fallback.
- `/usr/local/bin/wp-cli` is a bash launcher that runs `/usr/local/bin/wp-cli.phar` on the
  **newest** PHP installed (8.5 on saturn) and ignores `WP_CLI_PHP`. `scripts/lib.sh` skips
  launchers and runs the phar itself through `PHP_BIN`, so WP-CLI and the site share one PHP.
- Cron commands should use the full `/opt/cpanel/ea-phpXX/root/usr/bin/php` path.
- Git works in the shell. GitHub over SSH needs `Hostname ssh.github.com` / `Port 443` in
  `~/.ssh/config` because outbound 22 is blocked.
- Sending mail from a script: host = the domain or `serverNN.superhosting.bg`, authenticated with
  a real mailbox; the ports page lists 465 SSL/TLS and 587 STARTTLS on the server hostname, the
  script page documents 25 unencrypted. Outbound 25/26/465 to *other* servers is blocked.
- Apache with `.htaccess` (the deny rules on the private directory and WordPress permalinks work).
  Nothing ships a `.htaccess` for `public_html`: without one every URL except the homepage is a
  404. WP-CLI writes it on `wp rewrite flush --hard` only when told mod_rewrite exists, which is
  what `wp-cli.yml` in the repo root does (`apache_modules: [mod_rewrite]`). The seed runs that
  flush, and `check.sh` verifies both the file and a live pretty URL.
- PHP directives and modules are set per account in PHP Manager; `.user.ini` is the per-directory
  route that `check.sh` verifies rather than assumes.

## 7. Confirm on the real account

Not verifiable without an account; `check.sh` reports each one:

- `uapi` present in the jailed shell (database creation). Fallback: cPanel → MySQL Databases.
- A WP-CLI phar exists (`/usr/local/bin/wp-cli.phar` on saturn). Fallback: `install.sh`
  downloads `~/.reklamo/wp-cli.phar` with `wget`.
- `wp core update --version=X` on the bg_BG site needs `--locale=en_US`: wordpress.org has no
  Bulgarian package for point releases and the update fails to download otherwise. The scripts
  pass it and refresh the language pack afterwards.
- `crontab` writable from the shell. Fallback: the printed line in cPanel → Cron Jobs.
- Apache follows the theme/plugin symlinks (`SymLinksIfOwnerMatch`, same owner). `check.sh` fetches
  the theme's `style.css` through the symlink.
- `.user.ini` honoured by the FastCGI PHP. `check.sh` prints the values the web server applies.
