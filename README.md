# Laika Framework

A secure, fast, and flexible **Moduler First MVC framework** for PHP 8.1+, built with simplicity in mind. Laika gives you routing, models, templating, a service container, and a CLI generator — without the overhead of large frameworks like Laravel or Symfony.

[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/laikait/laika-framework)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## ✨ Features

- 🚀 **Lightweight core** — no bloat, no magic you can't trace back to a file
- 🛠️ **Expressive router** — route groups, named routes, regex-constrained and UTF-8 parameters, pipelines & filters
- 💉 **Dependency injection** — controllers, pipelines and filters are auto-wired from the service container
- 🗂️ **PDO models & schema builder** — MySQL, PostgreSQL, SQLite, SQL Server, Oracle, Firebird, plus backups and a SQL converter
- 🧩 **Service container** — `Relay` classes give every service a clean static API
- 🔐 **Security by default** — only whitelisted static files served, CSRF tokens injected into forms, CORS and security headers, encryption helpers, optional firewall (rate limiting, SQLi/XSS detection)
- 🔑 **Multi-guard auth** — session, cookie and database-backed bearer-token guards
- 🗃️ **Sessions & queues** — pluggable backends (file/database/Redis/Memcached sessions; JSON/database/Redis queues)
- ✉️ **Mail** — SMTP/sendmail sending and IMAP/POP3 reading
- ⚙️ **Built-in CLI** (`php laika`) — scaffold controllers, models, pipelines, filters, jobs, commands, templates, and more
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
| [Getting Started](docs/01_getting-started/01_installation.md) | Installation, project structure, configuration, CLI, request lifecycle, upgrading |
| [Routing & HTTP](docs/02_routing/01_basic.md) | Routes, controllers, requests & validation, responses |
| [Pipelines](docs/03_pipeline/01_basic.md) | Pre-controller middleware |
| [Filters](docs/04_filter/01_basic.md) | Post-controller middleware |
| [Models & Database](docs/05_models/01_basic.md) | Models, query builder, schemas, migrations, backups |
| [Templates](docs/06_templates/01_basic.md) | Twig views, assets, meta tags, navigation |
| [Services & Relays](docs/07_services-and-relay/01_basic.md) | The service container and the relay list |
| [Hooks](docs/08_hooks/01_basic.md) | Hook files and the hook system |
| [Authentication](docs/09_authentication/01_basic.md) | Session, cookie and token guards |
| [Security](docs/10_security/01_basic.md) | Firewall, CSRF & CORS, encryption & tokens |
| [Sessions](docs/11_sessions/01_basic.md) | Session backends and API |
| [Queue](docs/12_queue/01_basic.md) | Background jobs and workers |
| [Deployment](docs/13_deployment/01_basic.md) | Apache/nginx/PHP-FPM, production checklist |
| [Resources](docs/14_resources/01_basic.md) | Class discovery and custom resource types |
| [Helpers](docs/15_helpers/01_basic.md) | Global helper functions |
| [Files & Storage](docs/16_files-and-storage/01_basic.md) | Uploads, images, local and S3 storage |
| [Mail](docs/17_mail/01_basic.md) | Sending and reading email |
| [Errors & Logging](docs/18_errors-and-logging/01_basic.md) | Error handling, logs, activity log, options |
| [Utilities](docs/19_utilities/01_basic.md) | Dates, math, cron, shell commands |

The [docs index](docs/README.md) also has a "common tasks" table — the fastest way to find how to do something.

---

## 🧩 The Laika Ecosystem

`laikait/laika-framework` is the application skeleton; the actual functionality ships in one Composer package, [`laikait/laika-engine`](https://github.com/laikait/laika-engine), installed at `vendor/laikait/laika-engine`:

| Module | Namespace | Provides |
|---|---|---|
| Core | `Laika\Engine\*` (App, Http, Helper, Support, …) | Bootstrap, request/response, validation, templates, helpers, storage, security, errors |
| Route | `Laika\Engine\Route` | Router, dispatcher, pipelines and filters |
| Model | `Laika\Engine\Model` | PDO query builder, schema builder, backup, SQL converter |
| Relay | `Laika\Engine\Relay`, `Laika\Engine\Services` | Service container, relay base class and the core service relays |
| Session | `Laika\Engine\Session` | File, database, Redis and Memcached session handlers |
| Auth | `Laika\Engine\Auth` | Session, cookie and token guards |
| Shield | `Laika\Engine\Shield` | Firewall pipeline (rate limiting, IP/country blocking, SQLi/XSS detection) |
| Queue | `Laika\Engine\Queue` | Background job queue and worker |
| Cache | `Laika\Engine\Cache` | Cache drivers: file, array, Redis, Memcached |
| Mailman | `Laika\Engine\Mailman` | Mail sending (PHPMailer) and IMAP/POP3 reading |
| Cli | `Laika\Engine\Cli` | The `laika` command-line tool |

The docs here explain how the modules fit together inside an application; the engine's `docs/<module>/` folders go deeper into their internals.

---

## ⌨️ CLI Quick Reference

```bash
php laika controller:make UserController
php laika model:make UsersModel --table=users
php laika pipeline:make Auth
php laika filter:make Log
php laika job:make SendWelcomeEmail
php laika template:make dashboard --path=admin

php laika route:list
php laika app:migrate
php laika app:start
php laika help
```

Full command reference: [docs/01_getting-started/04_cli.md](docs/01_getting-started/04_cli.md).

---

## 🤝 Contributing

Issues and pull requests are welcome on the relevant package repository. For framework-wide changes, open an issue on [laikait/laika-framework](https://github.com/laikait/laika-framework).

## 📄 License

MIT — see [LICENSE](LICENSE).
