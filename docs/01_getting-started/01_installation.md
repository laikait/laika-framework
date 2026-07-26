# Laika Framework — Installation
 
## Requirements
- PHP >= 8.1
- Composer
- PDO extension (MySQL/SQLite/etc.)
- ext-json, ext-mbstring
## Install via Composer
 
```bash
composer create-project laikait/laika-framework myproject
cd myproject
```
 
## Run
 
```bash
php laika app:start
```
 
## Verify
 
Visit `http://127.0.0.1:8000` — you should see the default Laika landing route.