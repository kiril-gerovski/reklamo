# Deployment — SuperHosting.bg

How to put Reklamo.bg live on a SuperHosting shared account, and how to ship updates afterwards. The
same repo runs locally in Docker and on the host natively; only the `.env` and `wp-config.php` differ.

Principle unchanged from local dev: **WordPress core and WooCommerce are installed on the server, not
committed. Our code (theme + plugin) is a git checkout the site symlinks to. `scripts/seed.sh` is the
site configuration and runs on the server too.**

```
/home/<user>/
├── public_html/                      WordPress core (installed by WP-CLI), wp-config.php, uploads
│   └── wp-content/
│       ├── plugins/woocommerce/      installed by WP-CLI, pinned version
│       ├── plugins/reklamo-core  →   symlink to ../../../reklamo/wp-content/plugins/reklamo-core
│       └── themes/reklamo        →   symlink to ../../../reklamo/wp-content/themes/reklamo
├── reklamo/                          this git repository (git pull = deploy)
└── reklamo-private/                  customer logos & mockups — ABOVE the web root, never served
```

## 0. Prerequisites

| Need | Notes |
|---|---|
| **Plan with SSH** | SSH is included on **СуперПро** and **СуперХостинг** plans only, and is off by default. Without SSH use the fallback in §11. |
| Domain pointed at the account | DNS A record → the account's shared IP (cPanel → Основна информация). Allow propagation before SSL. |
| A mailbox on the domain | e.g. `office@reklamo.bg`, created in cPanel → Email Accounts. WooCommerce sends *from* it and SMTP authenticates *as* it. |
| Decisions not yet taken | VAT handling (`docs/PLAN.md` → Open questions) — decide **before** entering real products. |

## 1. Enable SSH and add your key

1. my.superhosting.bg → Хостинг акаунти → Настройки → **SSH достъп → Активиране**. Credentials arrive by email.
2. cPanel → **SSH Access → Manage SSH Keys** → import your public key (or generate one there) and **Authorize** it.
3. Connect — note the non-standard port:
   ```bash
   ssh -p 1022 <cpanel-user>@<domain-or-server>.superhosting.bg
   ```
   Handy in `~/.ssh/config` on your laptop: `Host reklamo-prod` / `HostName reklamo.bg` / `Port 1022` / `User <cpanel-user>`.

## 2. PHP version and limits

cPanel → **PHP Manager by SuperHosting**:

- **PHP version: 8.3** (what we develop and test on). Do not pick 8.4+ until tested locally.
- **Change PHP Directives** — set for the whole account:

  | Directive | Value | Why |
  |---|---|---|
  | `upload_max_filesize` | the largest offered (≥ 128M) | logo uploads; until chunked upload (hardening phase) this is the ceiling customers hit |
  | `post_max_size` | same or larger | must be ≥ `upload_max_filesize` |
  | `max_execution_time` | 120 | mockup uploads, WooCommerce admin |
  | `max_input_time` | 300 | a 100 MB upload on a slow uplink needs minutes to arrive |
  | `memory_limit` | 256M | WooCommerce |

  Write the values you got into `docs/PLAN.md` (Phase "uploads hardening" needs them); if the maximum offered is small, that phase's chunked upload becomes mandatory rather than nice-to-have.

## 3. Database

cPanel → **MySQL® Databases**: create database `<user>_reklamo`, a user with a long generated password, add the user to the database with **ALL PRIVILEGES**. Keep the three values for step 5.

## 4. WP-CLI on the server

```bash
wp --info || true          # SuperHosting normally ships wp-cli; if not:
mkdir -p ~/bin && curl -sSLo ~/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ~/bin/wp
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc && source ~/.bashrc
```

## 5. Install WordPress (once)

```bash
cd ~/public_html
# If the host pre-installed anything here (index.html, cgi-bin), move it aside first.
wp core download --locale=bg_BG --version=7.1
wp config create --dbname=<user>_reklamo --dbuser=<user>_dbuser --dbpass='<password>' --dbhost=localhost --dbprefix=wp_
wp core install --url=https://reklamo.bg --title="Reklamo.bg" --admin_user=<owner-login> --admin_password='<strong>' --admin_email=office@reklamo.bg --skip-email --locale=bg_BG
```

Then add the production constants to `wp-config.php` (above `/* That's all, stop editing! */`):

```php
define( 'WP_HOME',    'https://reklamo.bg' );
define( 'WP_SITEURL', 'https://reklamo.bg' );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_DEBUG', false );
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'FORCE_SSL_ADMIN', true );

// Customer logos live ABOVE the web root — never publicly addressable.
define( 'REKLAMO_PRIVATE_DIR', '/home/<user>/reklamo-private' );

// Outgoing mail through the account's own mailbox (see step 8).
define( 'REKLAMO_SMTP_HOST',   'mail.reklamo.bg' );      // or <servername>.superhosting.bg
define( 'REKLAMO_SMTP_PORT',   465 );
define( 'REKLAMO_SMTP_SECURE', 'ssl' );                  // 587 + 'tls' also works
define( 'REKLAMO_SMTP_USER',   'office@reklamo.bg' );
define( 'REKLAMO_SMTP_PASS',   '<mailbox password>' );

// Real cron instead of visitor-triggered WP-Cron (see step 9).
define( 'DISABLE_WP_CRON', true );
```

```bash
mkdir -p ~/reklamo-private && chmod 750 ~/reklamo-private
```

## 6. Clone the repo and link our code

```bash
cd ~ && git clone git@github.com:kiril-gerovski/reklamo.git     # add a deploy key in cPanel SSH Access first, or use HTTPS
cd ~/public_html/wp-content
rm -rf themes/twentytwenty* plugins/akismet plugins/hello.php   # stock noise
ln -s ~/reklamo/wp-content/themes/reklamo   themes/reklamo
ln -s ~/reklamo/wp-content/plugins/reklamo-core plugins/reklamo-core
```

Symlinks (not copies) are the point: a deploy is `git pull`, a rollback is `git checkout <tag>`.

## 7. WooCommerce, theme, plugin, configuration

```bash
cd ~/reklamo
cp .env.example .env
```
Edit `.env`: `WP_URL=https://reklamo.bg`, `WP_ADMIN_USER=<owner-login>`, `WP_PATH=/home/<user>/public_html`, `REKLAMO_ENV=production`. The DB_* / MAIL_PORT / WP_ADMIN_PASS lines are irrelevant on the server (Docker-only) — leave them.

```bash
set -a; . ./.env; set +a
wp --path="$WP_PATH" plugin install woocommerce --version="$WC_VERSION" --activate
wp --path="$WP_PATH" language core install bg_BG --activate
wp --path="$WP_PATH" language plugin install woocommerce bg_BG
wp --path="$WP_PATH" theme activate reklamo
wp --path="$WP_PATH" plugin activate reklamo-core
scripts/seed.sh                     # THE site configuration, same script as locally
```

`seed.sh` in production mode allows indexing (`blog_public 1`) and otherwise does exactly what it does locally: pages, menus, packages, gateway, shipping, request page, homepage from patterns, company settings. **Afterwards, edit the real values in the dashboard**: WooCommerce → Настройки → Reklamo (phone, email, address, social), product prices and photos, page texts. Re-running `seed.sh` later never overwrites a homepage the owner has edited.

Disable WooCommerce/WordPress auto-updates stay disabled (seeded). Updates are done deliberately, see §10.

## 8. Email — the step that decides whether approval links arrive

1. cPanel → **Email Accounts** → create `office@reklamo.bg` (used in step 5 constants).
2. cPanel → **Email Deliverability** → for reklamo.bg click **Manage** and install the suggested **SPF** and **DKIM** records (one click if DNS is at SuperHosting; otherwise copy them to your DNS). Add a **DMARC** TXT record: `v=DMARC1; p=quarantine; rua=mailto:office@reklamo.bg`.
3. SuperHosting's own guidance for WordPress SMTP is `<servername>.superhosting.bg`, port 25, authenticated. Prefer the encrypted variants above (465/ssl or 587/tls) — both are standard on cPanel mail; fall back to 25 only if they fail.
4. Test from the server: `wp --path=$WP_PATH eval 'var_dump( wp_mail("you@gmail.com","SMTP test","ok") );'` → expect `bool(true)`, then check the inbox **and** spam folder. Repeat to an abv.bg and a mail.bg address — those are the customers' providers.

No plugin is involved: the SMTP transport is `Reklamo_Mail` in the plugin, driven by the constants.

## 9. Cron

WP-Cron only fires on visits; on a low-traffic site scheduled jobs (WooCommerce Action Scheduler, future reminders) simply don't run. With `DISABLE_WP_CRON` set, add a real one — cPanel → **Cron Jobs**, every 5 minutes:

```
*/5 * * * * cd /home/<user>/public_html && /usr/local/bin/php wp-cron.php >/dev/null 2>&1
```

(or use SuperHosting's *Manager for WordPress → Cron Jobs (WP-Cron) → Move*, which does the same).

## 10. SSL and final checks

- cPanel → **SSL/TLS Status** → AutoSSL (Let's Encrypt) for reklamo.bg and www. Once issued, `WP_HOME`/`WP_SITEURL` already say https.
- Verify the private directory is not reachable: `curl -I https://reklamo.bg/reklamo-private/` must be 404 (it is outside `public_html`, so it should be).
- Walk the flow once with a real email address: homepage → Red Business Pack → request page → upload → order → email arrives → wp-admin → send mockup → approval link works. Then delete the test order (or keep it as the first "example").
- Set the WooCommerce store address, and turn on **Analytics** (WooCommerce → Analytics) — Legacy Reports ignore our statuses.

## 11. No SSH? cPanel Git fallback

If the plan lacks SSH, cPanel → **Git™ Version Control → Create** → clone `https://github.com/kiril-gerovski/reklamo.git` into `/home/<user>/reklamo`. Deployment then copies files instead of symlinking: commit a `.cpanel.yml` in the repo root —

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/<user>/public_html/wp-content
    - /bin/cp -R wp-content/themes/reklamo $DEPLOYPATH/themes/
    - /bin/cp -R wp-content/plugins/reklamo-core $DEPLOYPATH/plugins/
```

— and click **Update from Remote** then **Deploy HEAD Commit** after each release. Caveats from cPanel: deleted files are not removed from the target, and dot-files are not copied. WordPress itself is then installed with SuperHosting's *Manager for WordPress* and configured through the dashboard (no `seed.sh`).

## 12. Shipping an update

```bash
ssh reklamo-prod
cd ~/reklamo && git pull --ff-only
set -a; . ./.env; set +a
scripts/seed.sh                                  # only if scripts/seed.sh changed in the pull
wp --path="$WP_PATH" cache flush && wp --path="$WP_PATH" rewrite flush
```

Code is symlinked, so PHP/CSS changes are live the moment `git pull` finishes. If a release adds translatable strings, the compiled `.mo` files are committed, nothing to build. Rollback: `git checkout <previous-tag>` in `~/reklamo`.

Updating WordPress or WooCommerce: test the new version locally first (`WC_VERSION` in `.env.example`, `scripts/reset.sh`, `scripts/e2e.sh`), then on the server `wp core update` / `wp plugin update woocommerce`.

## 13. Backups and staging

- cPanel → **Backup** (or JetBackup if offered): make sure `~/reklamo-private` is inside the backup scope — it holds the customers' logo files, which exist nowhere else.
- Before the first real order, create `staging.reklamo.bg` as a cPanel **subdomain** with its own DB and a second checkout (`~/reklamo-staging`), and try upgrades there first. Cheap hosting has no undo button.
