# Repository Guidelines

## Project Structure & Module Organization
- `app/` contains feature-focused domain logic; keep controllers slim and move business rules into dedicated classes.
- HTTP routes live in `routes/`; align route names with related Inertia pages in `resources/js/Pages` for traceability.
- React components and UI primitives sit under `resources/js/Components`; shared styles belong in `resources/css` and compile to `public/` via Vite.
- Database migrations, factories, and seeders stay in `database/`; environment bootstrapping resides in `bootstrap/`.

## Build, Test, and Development Commands
- `composer install` and `npm install` hydrate PHP and Node dependencies before any local run.
- `composer run dev` starts Herd-compatible PHP, queue worker, and Vite builds; use `npm run dev` for frontend-only loops.
- Ship production assets with `npm run build`; static files end up in `public/`.
- Generate framework scaffolding with Artisan, e.g. `php artisan make:controller --no-interaction`.

## Coding Style & Naming Conventions
- Follow PSR-12 for PHP with constructor property promotion and strict return types; name classes descriptively (e.g., `SyncRentalListingsJob`).
- React files use PascalCase, default exports mirror filenames, and hooks use camelCase verbs.
- Order Tailwind utilities from layout → spacing → color; avoid duplicates.
- Format PHP via `vendor/bin/pint --dirty` and align JS/TS with `npm run lint` and `npm run format` before committing.

## Testing Guidelines
- Write all new tests with Pest; mirror existing dataset fixtures and rely on factories for setup.
- Scope runs with `php artisan test` or narrow paths such as `php artisan test tests/Feature/Rentals/ImportTest.php`.
- Fake queues, notifications, and events whenever side effects appear; assert responses with helpers like `assertUnauthorized` and `assertRedirect`.

## Commit & Pull Request Guidelines
- Use conventional commit prefixes (`feat:`, `fix:`) and keep each commit focused; reference Linear issues when available.
- Pull requests should summarize intent, document manual or automated test evidence, and attach Inertia UI screenshots or recordings.
- Flag migrations, queue jobs, or config changes in the PR body so reviewers can plan rollouts.

## Environment & Tooling Tips
- Serve the app through Laravel Herd at `https://rentalradar.test` or rely on `composer run dev` for a full-stack preview.
- Manage secrets in `.env` and document updates in `.env.example`.
- Prefer Laravel facades/helpers over raw globals; never call `env()` outside configuration files.
