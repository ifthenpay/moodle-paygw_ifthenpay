<p align="center">
  <a href="https://ifthenpay.com/" target="_blank">
    <img src="./src/pix/ifthenpay_brand.svg" alt="ifthenpay" width="220" />
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

- **Runtime:** <a href="https://moodle.org/">Moodle 5.1.5</a> (pinned + checksum-verified, configurable) · PHP 8.3 · <a href="https://xdebug.org/">Xdebug 3.4</a>
- **Database:** <a href="https://dev.mysql.com/doc/refman/8.4/en/">MySQL 8.4 LTS</a>
- **Dev Environment:** <a href="https://code.visualstudio.com/docs/devcontainers/containers">VS Code Dev Containers</a> + <a href="https://docs.docker.com/compose/">Docker Compose</a>
- **PHP Tooling:**
  - <a href="https://getcomposer.org/">Composer</a>
  - <a href="https://github.com/squizlabs/PHP_CodeSniffer">PHPCS 3.13</a> + <a href="https://github.com/moodlehq/moodle-cs">Moodle CS 3.7</a>
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
- switches the site to developer mode (debug on, JS/template/lang caching off, mail disabled)
- creates `.env` from `.env.example`, runs `composer install` and `npm install`

When the terminal shows the environment summary you are ready:

| | |
|---|---|
| Moodle | <code>http://localhost:8080</code> — `admin` / `adminpass` |
| phpMyAdmin | <code>http://localhost:8081</code> — `root` / `rootpass` |
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
| `MOODLE_VERSION` / `MOODLE_BRANCH` | `5.1.5` / `stable501` | Pinned Moodle release. Change + rebuild to test another version; core files and DB upgrade themselves on the next start. |
| `MOODLE_PORT` / `PMA_PORT` | `8080` / `8081` | Published ports. |
| `MOODLE_WWWROOT` | `http://localhost:8080` | Public base URL. `config.php` is rewritten and caches purged on start when it changes. |
| `MOODLE_DEV_MODE` | `1` | DEVELOPER debug level, JS/template/lang caches off, mail disabled, password policy off. |
| `MOODLE_AUTO_UPGRADE` | `1` | Runs pending core/plugin upgrades on every start. |
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
│  └─ workflows/               # GitHub Actions CI/CD workflows
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
│  ├─ db/                      # Database schema
│  ├─ lang/                    # Language strings (en, pt)
│  ├─ pix/                     # Images & icons
│  ├─ templates/               # Mustache templates
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
├─ phpstan.neon                # PHPStan configuration
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

**Tasks** (<kbd>F1</kbd> → *Run Task*): lint, fix, PHPStan, AMD build/watch, purge caches, run
upgrades, PHPUnit.

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
6. **Before committing:**
   ```bash
   mdl qa         # phpcs + eslint + stylelint + PHPStan
   mdl build      # commit the built AMD files in src/amd/build/
   ```

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
mdl cfg --name=debug --set=32767
mdl sql                    # MySQL shell (or: mdl sql "SELECT ...")
mdl logs                   # Tail Apache/PHP errors

mdl reinstall              # Uninstall + reinstall the plugin (drops its data)

mdl lint | fix | analyse | qa | build | watch
mdl phpunit-init           # Once per Moodle version
mdl phpunit [args]         # Run the plugin's PHPUnit tests
```

`mdl` runs Moodle CLI natively in the dev container — no `docker exec`, no Docker socket.

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
browser/Selenium service.)

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
│  ├─ local/
│  │  ├─ api_client.php             # HTTP client for ifthenpay endpoints
│  │  └─ data_formatter.php         # Helpers for payloads/data types
│  ├─ privacy/
│  │  └─ provider.php               # GDPR/privacy API implementation
│  └─ gateway.php                   # Gateway integration glue (Payment Account form/adapters)
│
├─ db/
│  ├─ install.xml                   # DB schema
│  └─ uninstall.php                 # Cleanup on uninstall
│
├─ lang/                            # Language strings
├─ pix/                             # Icons/assets
├─ templates/
│  └─ ifthenpay_button_placeholder.mustache  # UI partials
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
