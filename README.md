# Devithor LMS — Backend + Admin

Zero-dependency PHP 8.2 backend for the Devithor LMS Android app, with a
custom admin dashboard. Designed to deploy on Hostinger Cloud Hosting via
Git push.

## What this gives you

- **REST API** consumed by the Android app (`/api/v1/...`)
- **Admin dashboard** for managing courses, lessons, users (`/admin`)
- **MySQL schema** matching every domain model in the Android app
- **Bearer-token auth** for the mobile API (HMAC-signed, no JWT lib needed)
- **GitHub Actions** workflow that auto-deploys on push to `main`

## Stack

- PHP 8.2 (no Composer required for the core)
- MySQL 8.0 / MariaDB 10.6+
- Apache (with `mod_rewrite`)
- Vanilla HTML + small Bootstrap-like CSS for admin UI

## Quick start (local)

```bash
cp .env.example .env
# Edit .env: set DB_*, APP_KEY (openssl rand -base64 48), ADMIN_*

php migrations/migrate.php   # Creates all tables
php seeds/seed.php           # Adds 6 sample courses + 1 admin user

php -S localhost:8000 -t public  # Dev server
# Admin:  http://localhost:8000/admin/login
# API:    http://localhost:8000/api/v1/courses
```

## Deploy to Hostinger

See [`deploy/DEPLOY.md`](deploy/DEPLOY.md) for the full step-by-step guide
including domain setup, DB creation, SSH key registration, and triggering the
first GitHub Actions deploy.

## Project layout

```
.
├── public/           # Web root — point Hostinger domain here
│   ├── index.php     # Front controller
│   ├── .htaccess     # URL rewrite to index.php
│   └── assets/       # Admin CSS + JS
├── src/
│   ├── bootstrap.php # Env loader + autoload + DB init
│   ├── Database.php  # PDO singleton
│   ├── Router.php    # Pattern-based router with named params
│   ├── Auth.php      # Token + admin session auth
│   ├── Controllers/  # API + Admin controllers
│   └── Views/        # Admin HTML templates
├── migrations/       # Numbered SQL files + migrate.php runner
├── seeds/            # seed.php for sample data + admin bootstrap
├── routes/           # api.php + admin.php route definitions
├── deploy/           # DEPLOY.md + helper scripts
└── .github/workflows/deploy.yml  # CI deploy to Hostinger
```

## Adding a new API endpoint

1. Add the route in [`routes/api.php`](routes/api.php).
2. Add the controller method (or new controller in `src/Controllers/Api/`).
3. If new fields are needed, add a migration in `migrations/NNN_*.sql` and run `php migrations/migrate.php`.
4. Push to `main` → GitHub Actions deploys → Android picks it up on next sync.
