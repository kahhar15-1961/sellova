# Database migrations

## Canonical command

Use **Laravel’s migrator** only:

```bash
php artisan migrate
```

Composer mirrors this:

```bash
composer migrate
```

(`composer.json` maps `migrate` to `php artisan migrate`.)

## Legacy script

`scripts/migrate.php` remains as a **thin wrapper** around `php artisan migrate` for old habits or docs links. Prefer calling Artisan directly.

## CI

```yaml
# Example (GitHub Actions)
- run: cp .env.example .env && php artisan key:generate --force
- run: php artisan migrate --force
```

Ensure `DB_*` (or `.env`) points at the CI database before `migrate`.

## Schema note

The large marketplace schema may still be applied from `CANONICAL_SCHEMA.sql` via the historical migration `2026_04_21_000000_apply_canonical_schema.php` on empty databases. Laravel migrations in `database/migrations/` layer RBAC (`2026_04_25_120000_admin_rbac_foundation.php`), Sanctum’s `personal_access_tokens`, etc.

## Ordering

Migrations run in filename order. Do not rename published Sanctum migrations without checking dependency order against your own migrations.
