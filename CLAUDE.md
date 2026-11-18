# CLAUDE.md

Follow `AGENTS.md` as the canonical context for this repository.

## Project Summary

ParkPass es una Terminal web para recepción y validación de tickets, configurable por marca.


- Backend: PHP 8.5, Fat-Free Framework, MariaDB.
- Frontend: Vue 3, Pinia, PrimeVue, Tailwind CSS.
- Runtime: Docker Compose, WSL-oriented development.

## Operating Rules

- Use the root `Makefile` for commands.
- Do not use Laravel or Artisan commands.
- Preserve existing user changes.
- Keep changes small and aligned with existing modules.
- For backend work, keep controllers thin and delegate business logic to services.
- For frontend work, use module-local components, stores, services and mappers.
- Validate backend with `make test` or `make test-docker` when relevant.
- Validate frontend with `make frontend-build` when relevant.

## API conventions

- Keep `backend/src/Routes/api.php` focused on route registration and dependency composition.
- Reuse shared helpers for request payloads, authentication, validation and file uploads.
- Keep SQL and business rules in services or repositories, never in route closures.
- Protect private routes with the authenticated user and scope every query to that user.
- Use `JsonResponse::send()` for JSON responses.

