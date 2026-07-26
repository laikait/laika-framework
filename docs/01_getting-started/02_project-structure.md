# Laika Framework — Project Structure

```
myproject/
├── .github/            # GitHub workflows/templates
├── assets/             # public static assets
├── docs/               # documentation
├── lf-app/             # application code (controllers, models, etc.)
├── lf-boot/            # boot/bootstrap files
├── lf-cache/           # cache storage
├── lf-config/          # config files (app, database, etc.)
├── lf-hooks/           # hook definitions
├── lf-inc/             # includes
├── lf-lang/            # language/translation files
├── lf-logs/            # log files
├── lf-routes/          # route definition files
├── lf-storage/         # storage (uploads, generated files, etc.)
├── template/           # view/template files
├── uploads/            # uploaded files
├── vendor/             # composer dependencies
├── .gitignore
├── .htaccess
├── composer.json
├── composer.lock
├── index.php           # entry point
├── laika               # CLI executable
├── LICENSE
├── nginx.conf
└── README.md
```

## Key Directories

- **lf-app/** — application code
- **lf-boot/** — bootstrap/init files run on startup
- **lf-cache/** — framework/app cache
- **lf-config/** — config files, service provider registration
- **lf-hooks/** — hook definitions (Hook helper: add/do/apply)
- **lf-inc/** — shared includes
- **lf-lang/** — language files
- **lf-logs/** — log output
- **lf-routes/** — route files, auto-loaded
- **lf-storage/** — app-generated/storage files
- **template/** — view templates
- **uploads/** — user-uploaded files
- **assets/** — public static assets (css/js/images)
- **docs/** — project documentation

## Entry Points

- `index.php` — web entry point
- `laika` — CLI executable (laika-cli commands)
- `nginx.conf` — sample nginx server config
- `.htaccess` — Apache rewrite rules