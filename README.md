# Laika Framework

A lightweight, fast, and flexible **MMC framework** (Model-Middleware-Controller) for PHP 8.1+, built with simplicity in mind. Laika gives you routing, models, templating, a service container, and a CLI generator — without the overhead of large frameworks like Laravel or Symfony.

[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/laikait/laika-framework)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## ✨ Features

- 🚀 **Lightweight core** — no bloat, no magic you can't trace back to a file
- 🛠️ **Expressive router** — route groups, named routes, typed parameters, pipelines & filters
- 🗂️ **PDO models & schema builder** — MySQL, PostgreSQL, SQLite, SQL Server, Oracle, Firebird
- 🧩 **Service container** — a `Relay` static-proxy pattern for clean, testable app services
- 🔐 **Security by default** — direct file access denied, CSRF/CORS-ready, optional firewall middleware
- 🔑 **Multi-guard auth** — session, cookie, and token guards with Google/Facebook OAuth
- 🗃️ **Sessions & queues** — pluggable backends (file/PDO/Redis/Memcached, database/Redis/JSON)
- ⚙️ **Built-in CLI** (`php laika`) — scaffold controllers, models, pipelines, filters, templates, and more
- 📦 **Composer-native** — PSR-4 autoloading, each subsystem is its own versioned package

---

## 📦 Installation

```bash
composer create-project laikait/laika-framework myproject
cd myproject
php laika app:start
```

Visit `http://127.0.0.1:8000` — you should see the default Laika landing page.

See [docs/01_getting-started/01_installation.md](docs/01_getting-started/01_installation.md) for requirements and manual setup.

---

## 📚 Documentation

Full documentation lives in [`docs/`](docs/README.md):

| Section | What's covered |
|---|---|
| [Getting Started](docs/01_getting-started/01_installation.md) | Installation, project structure, configuration, CLI |
| [Routing](docs/02_routing/01_basic.md) | Routes, groups, named routes, controllers |
| [Pipelines](docs/03_pipeline/01_basic.md) | Pre-controller middleware |
| [Filters](docs/04_filter/01_basic.md) | Post-controller middleware |
| [Models & Database](docs/05_models/01_basic.md) | Query builder, schema builder, migrations |
| [Templates](docs/06_templates/01_basic.md) | Twig-based view rendering |
| [Services & Relay](docs/07_services-and-relay/01_basic.md) | The service container / static proxy pattern |
| [Hooks](docs/08_hooks/01_basic.md) | Event-style hook system |
| [Authentication](docs/09_authentication/01_basic.md) | Guards, tokens, OAuth |
| [Security (Shield)](docs/10_security/01_basic.md) | Firewall middleware |
| [Sessions](docs/11_sessions/01_basic.md) | Session backends and API |
| [Queue](docs/12_queue/01_basic.md) | Background jobs and workers |
| [Deployment](docs/13_deployment/01_basic.md) | Apache/nginx, production checklist |

---

## 🧩 The Laika Ecosystem

`laikait/laika-framework` is the application skeleton; the actual functionality ships as independently versioned Composer packages under `vendor/laikait/`:

| Package | Purpose |
|---|---|
| [laika-core](https://github.com/laikait/laika-core) | Bootstrap, templating, hooks, exceptions, framework glue |
| [laika-route](https://github.com/laikait/laika-route) | Router, dispatcher, pipelines & filters |
| [laika-model](https://github.com/laikait/laika-model) | PDO query builder & schema builder |
| [laika-relay](https://github.com/laikait/laika-relay) | Service container & static proxy (`Relay`) |
| [laika-session](https://github.com/laikait/laika-session) | File/PDO/Redis/Memcached sessions |
| [laika-auth](https://github.com/laikait/laika-auth) | Session/cookie/token guards, OAuth |
| [laika-shield](https://github.com/laikait/laika-shield) | Firewall middleware (rate limiting, IP/country blocking, SQLi/XSS detection) |
| [laika-queue](https://github.com/laikait/laika-queue) | Background job queue & worker |
| [laika-cli](https://github.com/laikait/laika-cli) | The `laika` code generator CLI |

Each package has its own README with a full API reference — the docs here focus on how they fit together inside an application.

---

## ⌨️ CLI Quick Reference

```bash
php laika make:controller UserController
php laika make:model User --table=users
php laika make:pipeline Auth
php laika make:filter Log
php laika make:template admin/dashboard

php laika list:route
php laika app:migrate
php laika app:start
```

Full command reference: [docs/01_getting-started/04_cli.md](docs/01_getting-started/04_cli.md).

---

## 🤝 Contributing

Issues and pull requests are welcome on the relevant package repository. For framework-wide changes, open an issue on [laikait/laika-framework](https://github.com/laikait/laika-framework).

## 📄 License

MIT — see [LICENSE](LICENSE).
