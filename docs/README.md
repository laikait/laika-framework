# Laika Framework Documentation

Laika is a lightweight **Moduler First MVC framework** for PHP 8.1+. Requests flow through **pipelines** (middleware before the controller), the **controller**, and **filters** (middleware after it), backed by a query builder, Twig templates, a service container and a code-generating CLI. Everything ships as small Composer packages under `vendor/laikait/`.

New to Laika? Read **Getting Started** in order. Looking for something specific? Try the [common tasks](#common-tasks) table.

---

## Getting Started

| Guide | Covers |
|---|---|
| [Installation](01_getting-started/01_installation.md) | Requirements, Composer install, the dev server |
| [Project Structure](01_getting-started/02_project-structure.md) | What every directory is for |
| [Configuration](01_getting-started/03_configuration.md) | `lf-config/*.php`, `config()`, `lf-inc/const.php` |
| [CLI Reference](01_getting-started/04_cli.md) | All 46 `php laika` commands, writing your own |
| [Request Lifecycle](01_getting-started/05_request-lifecycle.md) | What runs, in what order, from `index.php` to the response |
| [Upgrading](01_getting-started/06_upgrading.md) | Moving to laika-core 5.1 |

## The Basics

| Guide | Covers |
|---|---|
| [Routing](02_routing/01_basic.md) | Routes, parameters, named routes, groups, fallbacks |
| [Controllers](02_routing/02_controllers.md) | Return values, parameters, dependency injection |
| [Requests](02_routing/03_requests.md) | Input, files, headers, validation |
| [Responses](02_routing/04_responses.md) | JSON, status codes, redirects, flash messages, cookies, downloads |
| [Pipelines](03_pipeline/01_basic.md) | Middleware that runs **before** the controller |
| [Filters](04_filter/01_basic.md) | Middleware that runs **after** the controller |
| [Templates](06_templates/01_basic.md) | Twig views, template variables, filters |
| [Assets, Meta & Navigation](06_templates/02_assets-meta-nav.md) | Styles, scripts, meta tags, context, menus, icons |

## Database

| Guide | Covers |
|---|---|
| [Models & Database](05_models/01_basic.md) | Connections, models, schemas, migrations, soft deletes |
| [Query Builder](05_models/02_query-builder.md) | Every query, connection and schema-builder method |
| [Backup & Convert](05_models/03_backup-and-convert.md) | Database backups, moving between database engines |

## Architecture

| Guide | Covers |
|---|---|
| [Services & Relays](07_services-and-relay/01_basic.md) | The container, providers, relays, auto-wiring |
| [Relay List](07_services-and-relay/02_relay-list.md) | Every `Laika\Service\*` relay |
| [Hooks](08_hooks/01_basic.md) | `lf-hooks/`, `add_hook()` / `do_hook()` / `apply_hook()` |
| [Resources](14_resources/01_basic.md) | How classes are discovered; adding your own resource types |
| [Helper Functions](15_helpers/01_basic.md) | Every global helper function |

## Security

| Guide | Covers |
|---|---|
| [Authentication](09_authentication/01_basic.md) | Session, cookie and token guards |
| [Security (Shield)](10_security/01_basic.md) | Firewall — rate limiting, IP/country blocking, SQLi/XSS detection |
| [CSRF & CORS](10_security/02_csrf-and-cors.md) | CSRF tokens, CORS and security headers |
| [Encryption & Tokens](10_security/03_encryption-and-tokens.md) | App key, encryption, password hashing, JWTs, IDs |

## Beyond the Request

| Guide | Covers |
|---|---|
| [Sessions](11_sessions/01_basic.md) | File, database, Redis and Memcached sessions |
| [Session Drivers & Operations](11_sessions/02_drivers-and-operations.md) | Locking, garbage collection, failure modes, several servers |
| [Queue](12_queue/01_basic.md) | Background jobs and the `worker` |
| [Queue Drivers & Operations](12_queue/02_drivers-and-operations.md) | Driver internals, stuck jobs, worker hooks, custom drivers |
| [Caching](20_cache/01_basic.md) | File, Redis and Memcached caches; query, response and template caching |
| [Mail](17_mail/01_basic.md) | Sending and reading email |
| [Files & Storage](16_files-and-storage/01_basic.md) | Uploads, images, files, local and S3 storage |
| [Errors & Logging](18_errors-and-logging/01_basic.md) | The error handler, HTTP exceptions, logs, activity log, options |
| [Utilities](19_utilities/01_basic.md) | Dates, precise math, cron, shell commands, IP maths |

## Operations

| Guide | Covers |
|---|---|
| [Deployment](13_deployment/01_basic.md) | Apache, nginx, PHP-FPM, production checklist, running the worker |

---

## Common Tasks

| I want to... | See |
|---|---|
| Add a page | [Routing](02_routing/01_basic.md) → [Controllers](02_routing/02_controllers.md) → [Templates](06_templates/01_basic.md) |
| Validate a form and show errors | [Requests → Validation](02_routing/03_requests.md#validation) |
| Return JSON from an API | [Responses → JSON](02_routing/04_responses.md#json) |
| Redirect with a flash message | [Responses → Redirects](02_routing/04_responses.md#redirects) |
| Protect routes with a login | [Authentication → Protecting Routes](09_authentication/01_basic.md#protecting-routes) |
| Issue API tokens | [Authentication → Token Guard](09_authentication/01_basic.md#token-guard) |
| Check CSRF tokens | [CSRF & CORS → CSRF](10_security/02_csrf-and-cors.md#csrf) |
| Allow another origin to call my API | [CSRF & CORS → CORS](10_security/02_csrf-and-cors.md#cors) |
| Rate-limit or firewall the app | [Security (Shield)](10_security/01_basic.md) |
| Create a table | [Models → Schemas](05_models/01_basic.md#schemas) |
| Query the database | [Query Builder](05_models/02_query-builder.md) |
| Use a second database | [Models → Multiple Connections](05_models/01_basic.md#multiple-connections) |
| Store something in the session | [Sessions](11_sessions/01_basic.md) |
| Upload a file | [Files & Storage → Upload](16_files-and-storage/01_basic.md#upload) |
| Send an email | [Mail](17_mail/01_basic.md) |
| Run work in the background | [Queue](12_queue/01_basic.md) |
| Fix a queue that stopped processing | [Queue Drivers & Operations → A Job That Can't Be Restored](12_queue/02_drivers-and-operations.md#a-job-that-cant-be-restored) |
| Cache a slow value, query or page | [Caching](20_cache/01_basic.md) |
| Run code on every request | [Hooks → Hook Files](08_hooks/01_basic.md#hook-files) |
| Make a service available everywhere | [Services & Relays](07_services-and-relay/01_basic.md) |
| Add my own `php laika` command | [CLI → Writing Your Own Commands](01_getting-started/04_cli.md#writing-your-own-commands) |
| Change the timezone | [Utilities → Date](19_utilities/01_basic.md#date) |
| Go to production | [Deployment](13_deployment/01_basic.md#pre-deploy-checklist) |

---

## Package Reference

These docs explain how the packages fit together inside an application. Each package's own README goes deeper into its internals:

| Package | Provides |
|---|---|
| [laika-core](https://github.com/laikait/laika-core) | Bootstrap, request/response, validation, templates, relays for every core service, helpers, storage, security, errors |
| [laika-route](https://github.com/laikait/laika-route) | Router, dispatcher, pipelines and filters |
| [laika-model](https://github.com/laikait/laika-model) | PDO query builder, schema builder, backup, SQL converter |
| [laika-relay](https://github.com/laikait/laika-relay) | Service container and relay base class |
| [laika-session](https://github.com/laikait/laika-session) | File, database, Redis and Memcached session handlers |
| [laika-auth](https://github.com/laikait/laika-auth) | Session, cookie and token guards |
| [laika-shield](https://github.com/laikait/laika-shield) | Firewall pipeline |
| [laika-queue](https://github.com/laikait/laika-queue) | Background job queue and worker |
| [laika-cache](https://github.com/laikait/laika-cache) | Cache drivers: file, array, Redis, Memcached |
| [laika-mailman](https://github.com/laikait/laika-mailman) | Mail sending (PHPMailer) and IMAP/POP3 reading |
| [laika-cli](https://github.com/laikait/laika-cli) | The `laika` command-line tool |

Where a package README and these docs disagree about behaviour inside a Laika app, these docs describe the current code.

## Getting Help

- [Ask DeepWiki](https://deepwiki.com/laikait/laika-framework) — AI-assisted Q&A over the framework source
- [GitHub Issues](https://github.com/laikait/laika-framework/issues) — bug reports and feature requests
