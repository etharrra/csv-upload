# Tech Stack

## Backend
- PHP 8.1+, Laravel 10
- MySQL (primary database)
- Redis (queue driver via `predis/predis`)
- Laravel Horizon (queue monitoring dashboard)
- Laravel Sanctum (API auth scaffolding, not actively used in current routes)
- `beyondcode/laravel-websockets` — self-hosted WebSocket server (Pusher-compatible, port 6001)
- `pusher/pusher-php-server` — broadcasting driver

## Frontend
- Blade templates
- Tailwind CSS 4 (via PostCSS)
- Vite (asset bundler)
- Laravel Echo + pusher-js (WebSocket client)
- Axios

## Testing
- PHPUnit 10 (`phpunit.xml` at project root)
- Mockery for mocking
- Laravel Pint for code style (PSR-12)

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Build frontend assets
npm run build       # production
npm run dev         # dev server (Vite HMR)

# Laravel
php artisan serve               # start dev HTTP server (port 8000)
php artisan migrate             # run database migrations
php artisan migrate:fresh       # drop all tables and re-run migrations
php artisan key:generate        # generate app key

# Queue & WebSockets (run in separate terminals)
php artisan queue:work          # process queued jobs
php artisan horizon             # start Horizon queue manager
php artisan websockets:serve    # start WebSocket server (port 6001)

# Testing & code style
php artisan test                # run PHPUnit test suite
./vendor/bin/pint               # fix code style with Laravel Pint
```

## Environment
Key `.env` variables:
- `DB_*` — MySQL connection
- `REDIS_*` — Redis connection
- `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` — used by both the WebSocket server and Echo client
- `BROADCAST_DRIVER=pusher`, `QUEUE_CONNECTION=redis`
