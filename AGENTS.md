# Repository Guidelines

## Project Structure & Module Organization
Use `app/` for Laravel business logic: controllers, jobs, models, and services live there, while `app/Http` groups middleware and request classes. Routes belong to `routes/*.php` (mainly `routes/web.php`). Blade views appear under `resources/views`, UI assets and entry points in `resources/js` plus any Livewire/Alpine tied files. Static assets ship via `public/`, and compiled outputs land in `public/build` when `npm run build` runs. Migration, factory, and seeder sources stay in `database/`; tests sit under `tests/Feature` and `tests/Unit` mirroring the corresponding folders in `app/`. `storage/` holds logs, queue work, and filesystem disks; keep it writable but do not commit generated files. Composer-managed code lives in `vendor/` and should not be modified directly.

## Build, Test, and Development Commands
- `composer install` – installs PHP dependencies defined in `composer.json`.
- `npm install` – pulls frontend tooling from `package.json`/`package-lock.json`.
- `npm run build` – compiles and bundles the UI assets for production (`vite` under `resources/js`).
- `php artisan key:generate` and `php artisan migrate` – run once after copying `.env.example` to `.env` during setup.
- `php artisan serve` – boots the PHP dev server (default port 8000). Keep this running while developing the upload UI.
- `php artisan queue:work` – starts the worker for CSV processing jobs; restart after code changes affecting jobs.
- `php artisan websockets:serve` – runs the WebSocket server for realtime progress updates.

## Coding Style & Naming Conventions
Follow PSR-12: 4-space indentation, bracketed control structures, and descriptive names. All classes live under the `App` namespace (use `php artisan make:...`). Models are singular (`CsvRecord`), controllers suffix `Controller`, jobs end with `Job`, etc. Use `laravel/pint` (`./vendor/bin/pint`) before commits to autoformat PHP/Blade files; run `npm run lint` if you add new JS/CSS logic.

## Testing Guidelines
Tests live in `tests/Feature` for HTTP/behavior flows and `tests/Unit` for isolated classes. Name files like `CsvImportTest.php` and give each method a self-documenting name (e.g., `test_handles_missing_headers`). Run `./vendor/bin/phpunit` or `php artisan test`; pass `--filter` to narrow runs. Update or add tests that cover your CSV parsing, queue jobs, and broadcast events whenever you touch those areas.

## Commit & Pull Request Guidelines
Keep commit messages short, imperative, and scoped (e.g., `feat: add queue retry logging`). Reference the issue or ticket when available, note breaking changes, and mention relevant migrations or env changes. For PRs, include a summary, the testing steps you executed, links to related issues, and screenshots if UI changes occurred. Call out any manual setup (queues, websockets) reviewers must perform before testing.

## Security & Configuration Tips
Never commit `.env`. Rebuild config caches (`php artisan config:clear`) after editing env vars. Protect queue credentials by staging a dedicated `.env.testing` if needed, and ensure `DB_PASSWORD` is not logged. Restart workers/websockets after env or migration changes so new values take effect.
