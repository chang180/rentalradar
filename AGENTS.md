# Repository Guidelines

## Project Structure & Module Organization

Application code lives in app/ with domain logic arranged by feature. HTTP routes sit in routes/, while Inertia React pages and UI primitives live under resources/js, notably Pages/ and Components/. Shared styles belong to resources/css, and compiled assets publish to public/. Database migrations, factories, and seeders reside in database/, and environment bootstrapping happens through bootstrap/.

## Build, Test, and Development Commands

Install dependencies with composer install and npm install. Use composer run dev to start Herd compatible PHP, queue, and Vite processes together, or npm run dev for a frontend only loop. Ship production assets with npm run build. Execute back end tests using php artisan test, scoping with paths such as php artisan test tests/Feature/Rentals/ImportTest.php. Format PHP with vendor/bin/pint --dirty and align JS or TS via npm run format and npm run lint.

## Coding Style & Naming Conventions

PHP follows PSR-12 with constructor property promotion, strict return types, and descriptive class names such as SyncRentalListingsJob. React files are PascalCase, default exports match filenames, and hooks use camelCase verbs. Keep Tailwind utilities ordered from layout to color and avoid duplicates. Prefer Laravel facades or helpers over direct globals, and never reach for env outside configuration files.

## Testing Guidelines

All new behavior needs Pest coverage. Mirror existing dataset usage for validation rules, rely on model factories for setup, and assert responses with helpers such as assertUnauthorized or assertRedirect. Fake queues, notifications, and events when side effects appear. Run the narrowest possible test filter locally before opening a pull request.

## Commit & Pull Request Guidelines

Commits use conventional prefixes like feat: or fix: and often reference Linear issues; keep the subject scoped to one logical change. Pull requests should summarize intent, list testing evidence, and attach UI screenshots or recordings for Inertia updates. Flag migrations, queue jobs, or configuration changes so reviewers can prepare rollout steps.

## Environment & Tooling Tips

Serve the app through Laravel Herd at https://rentalradar.test, or rely on composer run dev for a full stack preview. Manage secrets in .env and document required keys in the example file when they change. Use Artisan generators such as php artisan make:controller --no-interaction to scaffold controllers, form requests, and jobs so they match project conventions.
