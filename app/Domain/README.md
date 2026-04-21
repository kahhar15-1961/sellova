# Domain layer (DTOs, commands, exceptions)

This folder holds **immutable commands** and **domain exceptions** consumed by `app/Services/*`.

- **Commands:** `App\Domain\Commands\...`
- **Enums (schema-aligned backed enums):** `App\Domain\Enums\...`
- **Value objects:** `App\Domain\Value\...`
- **Exceptions:** `App\Domain\Exceptions\...`

See `docs/HTTP_DOMAIN_PIPELINE.md` for the full **Controller → FormRequest → Command → Service → Exception** map (controllers not scaffolded yet).
