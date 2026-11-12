<p align="center">
  <a href="https://ifthenpay.com/" target="_blank">
    <img src="./.github/assets/ifthenpay_brand.svg" alt="ifthenpay" width="220" />
  </a>
</p>

<h1 align="center">⭐ ifthenpay – Moodle Payment Gateway ⭐</h1>

<p align="center">
  Developer guide for local development with <a href="https://code.visualstudio.com/docs/devcontainers/containers">VS Code Dev Containers</a> + <a href="https://www.docker.com/">Docker</a>.
</p>

<p align="center">
  <a href="https://code.visualstudio.com/docs/devcontainers/containers"><img alt="Dev Containers" src="https://img.shields.io/badge/VS%20Code-Dev%20Containers-007ACC?logo=visualstudiocode"></a>
  <a href="https://www.docker.com/"><img alt="Docker" src="https://img.shields.io/badge/Dockerized-🐳-2496ED?logo=docker"></a>
  <a href="https://www.php.net/releases/8.3/en.php"><img alt="PHP" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php"></a>
  <a href="https://moodle.org/"><img alt="Moodle" src="https://img.shields.io/badge/Moodle-Plugin-ff8f00?logo=moodle"></a>
  <a href="https://xdebug.org/"><img alt="Xdebug" src="https://img.shields.io/badge/Xdebug-3-2b9e4b"></a>
  <a href="https://getcomposer.org/"><img alt="Composer" src="https://img.shields.io/badge/Composer-Required-885630?logo=composer"></a>
  <a href="https://nodejs.org/"><img alt="Node" src="https://img.shields.io/badge/Node-22.x%20LTS-339933?logo=node.js"></a>
  <a href="https://gruntjs.com/"><img alt="Grunt" src="https://img.shields.io/badge/Build-Grunt-FAA918?logo=grunt"></a>
  <a href="https://dev.mysql.com/doc/refman/8.4/en/"><img alt="MySQL" src="https://img.shields.io/badge/MySQL-8.4%20LTS-4479A1?logo=mysql"></a>
</p>

---

## 🎯 Overview

Ifthenpay payment gateway plugin for <a href="https://moodle.org/">Moodle</a> with a batteries‑included local dev setup (🐳 Docker + <a href="https://code.visualstudio.com/docs/devcontainers/containers">Dev Containers</a>). It provides reproducible environments, coding standards, AMD build tasks, and ready‑to‑use <a href="https://xdebug.org/">Xdebug</a> debugging.

---

## 🧰 Tech Stack

- **Runtime:** <a href="https://moodle.org/">Moodle 5.3</a> (pinned + checksum-verified, configurable) · PHP 8.3 · <a href="https://xdebug.org/">Xdebug 3.4</a>
- **Database:** <a href="https://dev.mysql.com/doc/refman/8.4/en/">MySQL 8.4 LTS</a>
- **Dev Environment:** <a href="https://code.visualstudio.com/docs/devcontainers/containers">VS Code Dev Containers</a> + <a href="https://docs.docker.com/compose/">Docker Compose</a>
- **PHP Tooling:**
  - <a href="https://getcomposer.org/">Composer</a>
  - <a href="https://github.com/PHPCSStandards/PHP_CodeSniffer">PHPCS 3.13</a> + <a href="https://github.com/moodlehq/moodle-cs">Moodle CS 3.7</a>
  - <a href="https://phpstan.org/">PHPStan 2.2</a> + <a href="https://github.com/micaherne/phpstan-moodle">Moodle Extension 1.1</a>
  - <a href="https://phpmd.org/">PHPMD 2.15</a>
- **JS/AMD:** <a href="https://nodejs.org/">Node.js 22 LTS</a> + <a href="https://gruntjs.com/">Grunt 1.6</a> (uglify, watch, sourcemaps)
- **JS Tooling:**
  - <a href="https://eslint.org/">ESLint 9.39</a> (flat config) with JSDoc 50, Promise 7.3, Babel 7.29
  - <a href="https://stylelint.io/">Stylelint 16.26</a> + Stylistic 3.1 + Config Standard 36.0

---

## ⚡ Quickstart

1. **Prerequisites:** <a href="https://www.docker.com/">Docker</a> + <a href="https://code.visualstudio.com/">VS Code</a> with the <a href="https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers">Dev Containers extension</a>
2. **Clone & open** this repository in VS Code
3. **Reopen in container:** Command Palette (<code>F1</code>) → <em>"Dev Containers: Reopen in Container"</em>

That is the whole setup. The first build takes a few minutes and does everything else for you:

- pulls the pinned Moodle release (checksum-verified) and installs it
- starts MySQL 8.4 and creates the site
- **installs the plugin** (`admin/cli/upgrade.php`) and **enables the gateway**
- switches the site to developer mode (debug on, JS/template/lang caching off, mail caught by Mailpit)
- creates `.env` from `.env.example`, runs `composer install` and `npm install`

When the terminal shows the environment summary you are ready:

| | |
|---|---|
| Moodle | <code>http://localhost:8080</code> — `admin` / `adminpass` |
| phpMyAdmin | <code>http://localhost:8081</code> — `root` / `rootpass` |
| Mailpit (mail inbox) | <code>http://localhost:8025</code> — every email Moodle sends |
| Plugin source | `./src` on the host, `/workspace/plugin/src` in the editor |
| Helper CLI | `mdl help` (purge, upgrade, lint, phpunit, …) |

There are **no manual Moodle installation steps**: no "Site administration → Notifications", no
`docker exec`. Bump `version.php` and run `mdl upgrade` (or just restart the stack) and the plugin
upgrade is applied.

> 📦 **Migrating from the previous setup:** run `docker compose down -v` once before reopening the
> container. Moodle core now lives in a volume shared with the dev container, and the old volumes
> are laid out differently.

---

## ⚙️ Configuration

Everything is driven by a single `.env` file (created automatically from
[`.env.example`](.env.example) on first start). Nothing else needs to be edited.

| Variable | Default | What it does |
|---|---|---|
| `MOODLE_VERSION` / `MOODLE_BRANCH` | `5.3` / `stable503` | Pinned Moodle release. Change + rebuild to test another version; core files and DB upgrade themselves on the next start. |
| `MOODLE_PORT` / `PMA_PORT` / `MAILPIT_PORT` | `8080` / `8081` / `8025` | Published ports. |
| `MOODLE_WWWROOT` | `http://localhost:8080` | Public base URL. `config.php` is rewritten and caches purged on start when it changes. |
| `MOODLE_DEV_MODE` | `1` | DEVELOPER debug level, JS/template/lang caches off, mail routed to Mailpit, password policy off. |
| `MOODLE_DEV_SMTP` | `mailpit:1025` | Dev-mode SMTP catcher. Empty = send no mail at all (`noemailever`). |
| `MOODLE_AUTO_UPGRADE` | `1` | Runs pending core/plugin upgrades on every start. |
| `MOODLE_CRON` | `1` | Runs Moodle cron every minute in the app container, as the web user (sends queued mail, runs ad-hoc tasks). Output in `mdl logs cron`; `0` = only on demand with `mdl cron`. |
| `PLUGIN_AUTO_ENABLE` | `1` | Enables the gateway on every start, so the dev site is usable immediately (set `0` to manage it, and the Backoffice Key, yourself in Site administration). |
| `XDEBUG_MODE` / `XDEBUG_START` | `debug,develop` / `trigger` | Web debugging. `XDEBUG_START=yes` breaks on every request; `XDEBUG_MODE=off` is fastest. |
| `DEV_XDEBUG_MODE` | `off` | Xdebug for CLI tools in the dev container. |
| `APP_UID` / `APP_GID` | `1000` | **Linux only:** set to your `id -u` / `id -g` so the plugin files stay writable by you *and* by Apache. |
| `DB_*`, `MOODLE_ADMIN_*`, `MOODLE_SITE_*` | see `.env.example` | Database credentials, admin account, site name. |

Apply changes with `docker compose up -d` (rebuild only for `MOODLE_VERSION`, `APP_UID`, `APP_GID`).

---

## 🧱 Project Structure

```text
.
├─ .devcontainer/
│  ├─ devcontainer.json        # Compose integration, extensions, editor settings
│  ├─ Dockerfile.app           # PHP 8.3-Apache + Xdebug + pinned Moodle release
│  ├─ Dockerfile.dev           # Dev container: PHP CLI, Node 22, Moodle CLI tooling
│  ├─ app-entrypoint.sh        # Core sync → install → upgrade → dev settings → Apache
│  ├─ post-create.sh           # .env, permissions, composer install, npm install
│  ├─ post-start.sh            # Health checks + environment summary
│  ├─ bin/mdl                  # Helper CLI (see `mdl help`)
│  └─ lib/dev-setup.php        # Enables the gateway on the dev site
│
├─ .github/
│  ├─ workflows/ci.yml         # Checks for pull requests (and for releases), see "CI/CD"
│  ├─ workflows/release.yml    # Tag-triggered release: CI, GitHub Release, Moodle Marketplace
│  ├─ actions/                 # Shared steps: plugin PHP toolchain, cached Moodle core
│  └─ rulesets/main.json       # Branch protection for main, importable in the repo settings
│
├─ .vscode/
│  ├─ launch.json              # Xdebug launch configurations (web + CLI)
│  ├─ tasks.json               # Lint / analyse / build / purge / test tasks
│  └─ ifthenpay-moodle.code-workspace  # Optional multi-root view (repo + core)
│
├─ src/                        # Plugin source (PHP, templates, AMD JS, CSS)
│  ├─ amd/src/                 # Source AMD modules
│  ├─ amd/build/               # Built/minified AMD (committed)
│  ├─ classes/                 # PHP classes (PSR-4)
│  ├─ db/                      # Schema, upgrade steps, capability, message providers
│  ├─ lang/                    # Language strings (en, pt, es, fr)
│  ├─ pix/                     # Images & icons
│  ├─ templates/               # Mustache templates
│  ├─ tests/                   # PHPUnit tests
│  └─ *.php, *.css, version.php
│
├─ vendor/                     # Composer dependencies (dev-only)
├─ node_modules/               # NPM dependencies (dev-only)
│
├─ .env.example                # Every configurable knob (copied to .env on first start)
├─ composer.json               # PHP dependencies & scripts
├─ package.json                # JS dependencies & scripts
├─ Gruntfile.js                # AMD build configuration (uglify, watch)
├─ eslint.config.cjs           # ESLint flat config
├─ stylelint.config.cjs        # Stylelint configuration
├─ phpcs.xml                   # PHP CodeSniffer rules (Moodle CS)
├─ phpstan.neon                # PHPStan: level max + baseline (CI and `composer run analyse`)
├─ phpstan-compat.neon         # PHPStan: level 2, against the oldest supported Moodle (CI)
├─ phpstan-common.neon         # Settings shared by the two PHPStan configs
├─ docker-compose.yml          # Container orchestration
└─ LICENSE                     # Project license

```

> **🔗 Plugin Location** — one directory, three views of the same files:
>
> | Where | Path |
> |---|---|
> | Host | `./src` |
> | Editor / dev container | `/workspace/plugin/src` |
> | Served by Moodle | `/var/www/html/public/payment/gateway/ifthenpay` |
>
> Apache runs as your own user id (`APP_UID`), so the same files are writable from the editor and
> readable by the web server — no ownership dance.

---

## 🧩 Container Topology

| Container | Image/Base | Purpose | Ports |
|-----------|------------|---------|-------|
| **ifthenpay-app** | `php:8.3-apache` | Moodle (pinned release) + Xdebug 3.4 | `${MOODLE_PORT}:80` |
| **ifthenpay-db** | `mysql:8.4` | MySQL 8.4 LTS database | internal `3306` |
| **ifthenpay-dev** | `mcr.microsoft.com/vscode/devcontainers/php:8.3` | Editor tooling **and** Moodle CLI | – |
| **phpmyadmin** | `phpmyadmin:5.2` | Database UI | `${PMA_PORT}:80` |
| **mailpit** | `axllent/mailpit:v1.33.1` | Catches outgoing mail (SMTP `1025`, UI `8025`); nothing leaves the stack | `${MAILPIT_PORT}:8025` |

**Volumes**

| Volume | Mounted at (app **and** dev) | Contents |
|---|---|---|
| `moodle_core` | `/var/www/html` | Moodle core, synced from the image on version change |
| `moodle_data` | `/var/www/moodledata` | Uploads, caches, sessions |
| `phpunit_data` | `/var/www/phpunitdata` | PHPUnit data root |
| `app_logs` | `/var/log/apache2` (read-only in dev) | Apache/PHP error log — `mdl logs` |
| `mysql_data` | *(db only)* | Database files |

> **Why the shared paths matter:** Moodle core, moodledata and the plugin are mounted at *identical*
> paths in both containers. That is what makes `admin/cli/*.php` and PHPUnit runnable straight from
> the dev container's terminal, with no Docker socket and no `docker exec`.

The dev container starts only once the app container reports **healthy**, which happens after the
install/upgrade/configuration sequence has finished — so the site is always ready when your terminal
is.

---

## ✍️ Editor & Workspace Tips

**Workspace layout**
- The dev container opens `/workspace/plugin` (single root) — clean search results, one index.
- Moodle core is *not* a workspace folder, but Intelephense resolves core classes through
  `intelephense.environment.includePaths` (`/var/www/html/public`, `.../lib`).
- Want core in the explorer too? Open `.vscode/ifthenpay-moodle.code-workspace`
  (File → Open Workspace from File…). The plugin's path inside core is excluded from indexing so
  classes are never defined twice.

**Linting & formatting** (all wired to the same rules as CI)
- PHP: PHPCS/PHPCBF via **phpsab**, using `phpcs.xml` (Moodle CS) — format on save runs `phpcbf`.
- JavaScript: ESLint (flat config), auto-fix on save.
- CSS: Stylelint auto-fix on save. JSON/Markdown: Prettier.

**Tasks** (<kbd>F1</kbd> → *Run Task*)
- Quality: `QA: lint + analyse` (default test task) · `PHP: lint (phpcs)` · `PHP: fix (phpcbf)` ·
  `PHP: analyse (PHPStan)` · `JS/CSS: lint` · `JS/CSS: fix` · `Fix: phpcbf + eslint + stylelint`
- AMD: `AMD: build` (default build task) · `AMD: watch`
- Moodle: `Moodle: purge caches` · `Moodle: run upgrades` · `Moodle: environment info`
- Tests: `Tests: PHPUnit (plugin)` · `Tests: initialise PHPUnit`

**Pre-installed extensions:** Intelephense, PHP Debug, phpsab, PHP snippets, ESLint, Stylelint,
Prettier, EditorConfig.

---

## 🔄 Development Workflow

1. **Edit** the plugin in `./src` — it is live in Moodle immediately.
2. **PHP changes** are picked up on the next request. Only bump `src/version.php` when you change
   the database schema or want an upgrade step, then:
   ```bash
   mdl upgrade
   ```
3. **JavaScript / CSS**: JS caching is off in dev mode, so a rebuild is enough:
   ```bash
   mdl watch      # or: mdl build
   ```
4. **Language strings** apply instantly (`langstringcache` is off).
5. **Something looks stale?** `mdl purge`
6. **Waiting for an email or an ad-hoc task?** Cron runs every minute on its own
   (`MOODLE_CRON=1`); `mdl cron` runs it right away, `mdl adhoc` just the ad-hoc queue.
   The very first cron run after enabling it drains every ad-hoc task queued until then, so
   expect a longer run and a burst of mail in Mailpit.
7. **Before committing:**
   ```bash
   mdl qa         # phpcs + eslint + stylelint + PHPStan
   mdl build      # commit the built AMD files in src/amd/build/
   ```
8. **Releasing:** add a `## x.y.z` section to `src/CHANGES.md`, set `$plugin->release = 'x.y.z'`
   and bump `$plugin->version` in `src/version.php`, merge to `main`, then push the tag:
   ```bash
   git tag vx.y.z && git push origin vx.y.z
   ```
   `release.yml` refuses a tag that does not match `$plugin->release` or has no changelog section,
   runs every check, then publishes the zip as a GitHub Release and to the
   [Moodle Marketplace](https://marketplace.moodle.com/), both with that changelog section as the
   release notes. The Marketplace step needs the `MOODLE_MARKETPLACE_TOKEN` repository secret; a
   failed submission can be retried with *Re-run failed jobs*.

---

## 🚦 CI/CD

One verification workflow, `.github/workflows/ci.yml`, and one release workflow.

| When | What runs |
|---|---|
| Pull request to `main` | The checks its changes need (below), then **CI result** |
| Change to `src/version.php` in a pull request | Its checks + the oldest-supported-Moodle job |
| Change to `.github/**` in a pull request | Every check |
| Tag `v*` (`release.yml`) | Tag/version/changelog check → every check → GitHub Release → Moodle Marketplace |
| *Run workflow* on the Actions tab | Every check |

Nothing runs on push to `main`: the pull request was the gate, and the branch ruleset requires
it to be up to date with `main`, so what is merged is what was checked.

**Jobs** (in parallel after *What changed* decides the scope):

| Job | Runs when the change touches | Does |
|---|---|---|
| 🐘 PHPCS | `src/`, `phpcs.xml`, `phpstan*.neon`, `composer.*`, `.env.example` | `composer run lint` rules |
| 🛡️ PHPStan | same | `composer run analyse` (level max) against a cached Moodle core |
| 🧪 PHPUnit | same, if `src/tests/` has tests | the `paygw_ifthenpay_testsuite` on MySQL 8.4 |
| 🧬 Moodle 4.5 | `src/version.php` (release preparation, schema changes), releases, manual runs | Against the oldest supported release (`$plugin->requires`): PHPStan with `phpstan-compat.neon` (missing functions, classes, methods), then the PHPUnit suite |
| 🔍 ESLint + Stylelint | `src/amd/`, CSS, the JS lint configs, `package*.json` | `npm run lint`, and checks `src/amd/build/` matches a fresh build |
| ✅ **CI result** | always | fails if any job above failed; the one required check |

**Reports:** PHPCS and PHPStan problems and PHPUnit failures appear as annotations on the pull
request's *Files changed*; each job writes a short result (counts, failing tests) to the run's
*Summary*, and *CI result* a table of every job. A release run summarises the package (size,
files, SHA-256, release link) and the Marketplace submission.

**Moodle version:** CI reads `MOODLE_BRANCH` / `MOODLE_VERSION` from `.env.example`, the same pin
as the dev environment; bumping it there updates both (the cached core is keyed by it). The oldest
supported release is `MOODLE_MIN_BRANCH` / `MOODLE_MIN_VERSION` at the top of `ci.yml` — raise it
together with `$plugin->requires` in `src/version.php`.

**Branch protection:** `.github/rulesets/main.json` is the ruleset for `main` — Settings → Rules →
Rulesets → New ruleset → *Import a ruleset*. It requires a pull request (squash merge), the
**CI result** check and an up-to-date branch, blocks force pushes and deletion, and lets
repository admins bypass it (e.g. a direct push for a dev-environment chore).

---

## 🛠️ Commands

### PHP (Composer)

```bash
composer run lint      # PHPCS (Moodle CS)
composer run lint:fix  # PHPCBF auto-fix
composer run analyse   # PHPStan static analysis
composer run qa        # Run lint + analyse
```

### JS / AMD (Grunt)

```bash
npm run build         # Build AMD bundles (uglify + sourcemaps)
npm run watch         # Watch & auto-rebuild AMD on changes

npm run lint:js       # ESLint - check JavaScript
npm run lint:js:fix   # ESLint - auto-fix JavaScript
npm run lint:css      # Stylelint - check CSS
npm run lint:css:fix  # Stylelint - auto-fix CSS

npm run lint          # Run all linters (JS + CSS)
npm run lint:fix      # Auto-fix all fixable issues
```

### Moodle — the `mdl` helper (dev container terminal)

```bash
mdl                        # Status: URLs, Moodle/plugin versions, gateway state
mdl purge                  # Purge all caches
mdl upgrade                # Install/upgrade pending core + plugin versions
mdl cli <script> [args]    # Any admin/cli script (run `mdl cli` to list them)
mdl cfg --name=noreplyaddress --set=noreply@example.com
mdl sql                    # MySQL shell (or: mdl sql "SELECT ...")
mdl logs                   # Tail Apache/PHP errors
mdl logs cron              # Tail the background cron's output
mdl cron                   # Run cron once now (e.g. right after a test payment)
mdl adhoc                  # Run only the queued ad-hoc tasks now

mdl reinstall              # Uninstall + reinstall the plugin (drops its data)

mdl lint | fix | analyse | qa | build | watch
mdl phpunit-init           # Once per Moodle version
mdl phpunit [args]         # Run the plugin's PHPUnit tests
```

`mdl` runs Moodle CLI natively in the dev container — no `docker exec`, no Docker socket.

`mdl cfg --set` cannot change `debug`, `debugdisplay`, `cachejs`, `cachetemplates`,
`langstringcache`, `noemailever`, `smtphosts`, `noreplyaddress` or `passwordpolicy` — those are forced from `MOODLE_DEV_MODE` in
`config.php` on every start (see `app-entrypoint.sh`'s managed block) and win over anything written
to the database. Set `MOODLE_DEV_MODE=0` in `.env` first if you need to change one of them.

### Docker (host terminal)

```bash
docker compose up -d               # Start the stack
docker compose up -d --build       # Rebuild (after MOODLE_VERSION / APP_UID changes)
docker compose down                # Stop
docker compose down -v             # Stop + wipe volumes (fresh install on next up)
docker compose ps
docker compose logs -f ifthenpay-app
```

### Tests (PHPUnit)

```bash
mdl phpunit-init     # Installs Moodle's dev dependencies + test database (a few minutes)
mdl phpunit          # Runs the paygw_ifthenpay test suite
```

Put tests in `src/tests/` following Moodle's conventions. (Behat is not configured — it needs a
browser/Selenium service.) The suite covers the shared `lib.php` helpers (`lib_test.php`), staff
notifications (`notification_test.php`, `notification_recipients_test.php`,
`task/send_notification_test.php`), the account form (`config_form_test.php`) and the privacy
provider (`privacy/provider_test.php`).

To check staff notifications by hand, configure them on a payment account, complete a payment,
run `mdl adhoc` (or wait for cron) and open Mailpit at <code>http://localhost:8025</code>; the
popup copy shows in the recipient's Moodle notifications.

CI runs the same suite in the PHPUnit job of `.github/workflows/ci.yml`: a MySQL 8.4 service, the
pinned Moodle release (cached, shared with the PHPStan job), the plugin copied to
`public/payment/gateway/ifthenpay`, `admin/tool/phpunit/cli/init.php --disable-composer`, then
`vendor/bin/phpunit --testsuite paygw_ifthenpay_testsuite`. Installing the test site is the slow
part, so a snapshot of it (database dump + `phpunit_dataroot`) is cached per Moodle release and
plugin `version.php`/`db/`: a change there installs afresh, anything else reuses it. When
`src/tests/` has no `*_test.php` files the job is skipped.

---

## 🐞 Debugging (Xdebug)

Xdebug 3.4 is installed in both containers and configured through `.env`, so debugging costs nothing
when you are not using it.

**Web requests**
1. <strong>Run and Debug</strong> (F5) → <strong>"🪲 Xdebug: Moodle (web)"</strong> — this listens on port 9003 inside the dev container.
2. Trigger the request:
   - default (`XDEBUG_START=trigger`): use a browser extension, or append `?XDEBUG_TRIGGER=1`
   - or set `XDEBUG_START=yes` in `.env` + `docker compose up -d` to break on **every** request
3. Breakpoints in `src/**` and in Moodle core both resolve (path mappings are pre-configured).

**CLI**
1. Start <strong>"🪲 Xdebug: CLI (dev container)"</strong>
2. Run a single command with debugging enabled:
   ```bash
   XDEBUG_MODE=debug XDEBUG_TRIGGER=1 mdl cli purge_caches
   XDEBUG_MODE=debug XDEBUG_TRIGGER=1 mdl phpunit
   ```

**Settings** — app: `XDEBUG_MODE` / `XDEBUG_START`; dev CLI: `DEV_XDEBUG_MODE` (default `off`, which
also keeps `composer`/`phpcs`/`phpstan` fast and quiet). Client host `ifthenpay-dev`, port `9003`.

---

## ❓ Troubleshooting

**Plugin not detected / version not installed?**
```bash
mdl             # installed version, gateway enabled state
mdl upgrade     # install pending version bumps
mdl purge
```

Enable/disable the gateway and set its Backoffice Key from Site administration →
Plugins → Payment gateways → ifthenpay, same as any other plugin.

**"Permission denied" when saving a file in `src/`?**
Your host user id is not `1000`. Set `APP_UID`/`APP_GID` in `.env` to your `id -u`/`id -g`, rebuild
(`docker compose up -d --build`), and repair the existing files once:
```bash
sudo chown -R "$(id -u):$(id -g)" /workspace/plugin
```

**Xdebug breakpoints not hitting?**
- Is the listener running (Run and Debug → "🪲 Xdebug: Moodle (web)")?
- With the default `XDEBUG_START=trigger` the request must carry the trigger — add
  `?XDEBUG_TRIGGER=1` or set `XDEBUG_START=yes` in `.env`.
- Check `XDEBUG_MODE` is not `off` in `.env` (and `docker compose up -d` after changing it).

**Moodle not loading / white screen?**
```bash
mdl logs                              # Apache + PHP errors, from the dev container
docker compose logs -f ifthenpay-app  # boot sequence (host terminal)
```

**Database connection issues?**
```bash
mdl sql "SELECT 1;"                   # from the dev container
docker compose ps ifthenpay-db        # from the host
docker compose logs --tail 50 ifthenpay-db
```

**Testing another Moodle version?**
Set `MOODLE_VERSION` / `MOODLE_BRANCH` in `.env` (see <https://packaging.moodle.org/>), then
`docker compose up -d --build`. The new core is synced into the shared volume and the database is
upgraded automatically — your site and its data survive.

**Need a completely fresh start?**
```bash
docker compose down -v
docker compose up -d --build          # reinstalls Moodle and the plugin from scratch
```

**Container won't build?**
```bash
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

## 📁 Plugin Source (overview)

This plugin follows the official Moodle **plugin structure & coding guidelines** (see: [Moodle Plugins docs](https://moodledev.io/docs/guides), [Payment gateway API](https://moodledev.io/docs/apis/subsystems/payment)). Classes are PSR‑4 autoloaded under `paygw_ifthenpay\*`, UI is rendered with Mustache, and browser JS is shipped as **AMD modules** compiled via Grunt.

```text
src/
├─ amd/
│  ├─ src/
│  │  ├─ admin_gateway_form.js      # Enhancements for the admin gateway form
│  │  ├─ gateways_modal.js          # UI modal for method selection / UX helpers
│  │  └─ return.js                  # Return‑page UX (polling/spinner/retry)
│  └─ build/                        # Minified AMD bundles (committed for releases)
│
├─ classes/
│  ├─ adminsetting/
│  │  └─ backofficekey.php          # Custom admin setting + server-side validation
│  ├─ event/
│  │  ├─ notification_failed.php    # Logged when a staff notification cannot be sent
│  │  └─ payment_problem.php        # Logged when a payment notification is rejected
│  ├─ local/
│  │  ├─ api_client.php             # HTTP client for ifthenpay endpoints
│  │  ├─ config_form.php            # Payment account form (Payment + Staff notifications)
│  │  ├─ data_formatter.php         # Helpers for payloads/data types
│  │  ├─ hook_callbacks.php         # Moodle hook callbacks
│  │  └─ notification_recipients.php  # Resolves who receives a staff notification
│  ├─ privacy/
│  │  └─ provider.php               # GDPR/privacy API implementation
│  ├─ task/
│  │  └─ send_notification.php      # Ad-hoc task: sends one staff notification
│  └─ gateway.php                   # Gateway integration glue (Payment Account form/adapters)
│
├─ db/
│  ├─ access.php                    # Capability: paygw/ifthenpay:receivenotifications
│  ├─ caches.php                    # Cache definitions
│  ├─ hooks.php                     # Hook callback registrations
│  ├─ install.xml                   # DB schema
│  ├─ messages.php                  # Message providers (payment completed / problem)
│  ├─ uninstall.php                 # Cleanup on uninstall
│  └─ upgrade.php                   # Upgrade steps
│
├─ lang/                            # Language strings
├─ pix/                             # Icons/assets
├─ templates/
│  ├─ checkout_waiting.mustache            # "Complete the payment in the other tab" page
│  ├─ ifthenpay_button_placeholder.mustache  # Redirect placeholder in the payment modal
│  ├─ settings_status.mustache             # Connection status card on the settings page
│  ├─ status_page.mustache                 # Return / cancel / error pages
│  └─ steps.mustache                       # Onboarding steps on the settings page
├─ tests/                           # PHPUnit tests (see "Tests (PHPUnit)")
│
├─ cancel.php                       # Cancel/error landing
├─ lib.php                          # Business logic (helper functions)
├─ pay.php                          # Starts a payment attempt
├─ return.php                       # Handles return from provider (poll + update)
├─ settings.php                     # Admin settings (Plugin Core settings)
├─ styles.css                       # Small admin/UI styles
├─ version.php                      # Component metadata (version/reqs)
└─ webhook.php                      # Server‑to‑server notifications (callback endpoint)
```

> **Notes:** author AMD in `amd/src/*` and build to `amd/build/*` via `npm run build` (or `npm run watch`). Ensure built files are committed for distribution.

---

<h1 align="center">Happy Coding! 🚀</h1>
