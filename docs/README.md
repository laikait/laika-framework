# Laika Framework Documentation

Laika is a lightweight **MMC framework** (Model-Middleware-Controller) for PHP 8.1+. This documentation covers everything needed to build an application with it — from installing the framework to deploying a background job worker.

If you're new here, read the guides in order. If you already know what you're looking for, jump straight to the relevant section.

---

## Getting Started

| Guide | Description |
|---|---|
| [Installation](01_getting-started/01_installation.md) | Requirements, installing via Composer, running the dev server |
| [Project Structure](01_getting-started/02_project-structure.md) | What every directory in a Laika project is for |
| [Configuration](01_getting-started/03_configuration.md) | `lf-config/*.php` — app, database, mail, redis, queue, auth |
| [CLI Reference](01_getting-started/04_cli.md) | Every `php laika` command |

## Core Concepts

| Guide | Description |
|---|---|
| [Routing](02_routing/01_basic.md) | Routes, HTTP methods, parameters, groups, named routes |
| [Controllers](02_routing/02_controllers.md) | Writing controllers, method injection, returning views |
| [Pipelines](03_pipeline/01_basic.md) | Middleware that runs **before** the controller |
| [Filters](04_filter/01_basic.md) | Middleware that runs **after** the controller |
| [Models & Database](05_models/01_basic.md) | Query builder, schema builder, migrations |
| [Templates](06_templates/01_basic.md) | Twig-based view rendering |
| [Services & Relay](07_services-and-relay/01_basic.md) | The service container / static proxy pattern |
| [Hooks](08_hooks/01_basic.md) | Event-style hook system (`add_hook` / `apply_hook`) |

## Application Services

| Guide | Description |
|---|---|
| [Authentication](09_authentication/01_basic.md) | Session/cookie/token guards, OAuth |
| [Security (Shield)](10_security/01_basic.md) | Firewall middleware — rate limiting, IP/country blocking, SQLi/XSS detection |
| [Sessions](11_sessions/01_basic.md) | File/PDO/Redis/Memcached session backends |
| [Queue](12_queue/01_basic.md) | Background jobs and the `worker` process |

## Operations

| Guide | Description |
|---|---|
| [Deployment](13_deployment/01_basic.md) | Apache/nginx, entry points, running the queue worker in production |

---

## Package Reference

Laika is composed of independently versioned packages under `vendor/laikait/`. These docs explain how they fit together inside an application; each package's own `README.md` is the authoritative API reference:

- [laika-core](https://github.com/laikait/laika-core) — bootstrap, templating, hooks, exceptions
- [laika-route](https://github.com/laikait/laika-route) — router, dispatcher, pipelines & filters
- [laika-model](https://github.com/laikait/laika-model) — PDO query builder & schema builder
- [laika-relay](https://github.com/laikait/laika-relay) — service container & static proxy
- [laika-session](https://github.com/laikait/laika-session) — session backends
- [laika-auth](https://github.com/laikait/laika-auth) — authentication guards & OAuth
- [laika-shield](https://github.com/laikait/laika-shield) — firewall middleware
- [laika-queue](https://github.com/laikait/laika-queue) — background job queue
- [laika-cli](https://github.com/laikait/laika-cli) — the `laika` code generator

---

## Getting Help

- [Ask DeepWiki](https://deepwiki.com/laikait/laika-framework) — AI-assisted Q&A over the framework source
- [GitHub Issues](https://github.com/laikait/laika-framework/issues) — bug reports and feature requests
