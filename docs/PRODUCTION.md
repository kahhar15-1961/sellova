# Production operations checklist

## Web server

- Point the vhost document root to **`public/`** (not project root).
- Set **`APP_ENV=production`**, **`APP_DEBUG=false`**, **`APP_URL=https://…`**.
- Run **`php artisan config:cache`** and **`php artisan route:cache`** after deploy.

## HTTPS and sessions

- Set **`SESSION_SECURE_COOKIE=true`** when the site is only served over HTTPS.
- Set **`SESSION_SAME_SITE=lax`** (or **`strict`**) unless you need cross-site cookies.
- Enable **`TRUST_PROXIES=true`** and tune **`TRUSTED_PROXIES`** when TLS terminates at a load balancer or CDN so `URL::forceScheme('https')` and client IP detection stay correct.

## Queues and Horizon

- Set **`QUEUE_CONNECTION=redis`** (or your broker) and configure **`REDIS_*`**.
- Run a **queue worker**: `php artisan queue:work redis --sleep=3 --tries=3` (use systemd/supervisor in production).
- **Horizon** (`/horizon`): run `php artisan horizon` under supervisor. The dashboard requires a signed-in **admin** user who passes the `viewHorizon` gate (`HorizonServiceProvider`). Unlike stock Laravel Horizon, **local** does not auto-open the UI; set **`HORIZON_ALLOW_LOCAL_UNAUTHENTICATED=true`** in `.env` only on trusted dev machines if you want unauthenticated access in `APP_ENV=local`.

## Scheduler

- Cron: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- Register jobs in `routes/console.php`.

## Mail

- Set **`MAIL_*`** for your provider (`smtp`, `ses`, `postmark`, etc.). **`MAIL_MAILER=log`** is fine for staging.

## Sanctum vs mobile API

- **Mobile `/api/v1/*`** continues to use the **legacy Bearer token** pipeline (`ResolveActorUser` + `UserAuthToken`).
- **Sanctum** is installed for **first-party SPAs** or future PAT-based APIs; wire `routes/api.php` (Laravel) or middleware when you adopt it. See `config/sanctum.php`.
