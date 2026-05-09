# Docker Development Setup

This project runs a Laravel 10 CSV upload application with Docker Compose.

## Quick Start

```bash
# Copy Docker environment file
cp .env.docker .env

# Build and start all services
docker compose up -d --build

# Install dependencies, first run only
docker compose exec laravel composer install
docker compose exec laravel npm install

# Prepare the database
docker compose exec laravel php artisan migrate

# Open the app
open http://localhost:8000
```

## Services

| Service | Image | Ports | Purpose |
|---------|-------|-------|---------|
| `laravel` | Custom Ubuntu 24.04 / PHP 8.3 image | `8000:80`, `5173:5173`, `6001:6001` | App server, queue worker, S3 notification poller, WebSocket server |
| `mysql` | `mysql:8.0` | `3307:3306` | Database |
| `redis` | `redis:7-alpine` | `6380:6379` | Queue/cache backend |

Host ports `3307` and `6380` avoid conflicts with MySQL or Redis running directly on your machine.

## App Image

The Laravel image is built from [docker/8.3/Dockerfile](docker/8.3/Dockerfile). It uses `ubuntu:24.04` and installs PHP 8.3, Composer, Node.js, and common local development tools.

The build accepts these arguments:

| Argument | Default | Purpose |
|----------|---------|---------|
| `WWWGROUP` | from `${WWWGROUP:-1000}` | Group ID for the `sail` user |
| `NODE_VERSION` | from `${NODE_VERSION:-20}` in Compose | Node.js major version from NodeSource |

Installed tooling includes Composer, npm, MySQL client, Supervisor, and PHP extensions for MySQL, Redis, ZIP, XML, BCMath, Intl, MBString, Curl, Readline, PCNTL, and POSIX support.

The image intentionally does not install unused development packages such as PostgreSQL client/extensions, SQLite packages/extensions, GD, Imagick, IMAP, SOAP, LDAP, Memcached, Xdebug, pnpm, Bun, or Yarn.

## File Structure

```text
docker/
├── 8.3/
│   ├── Dockerfile
│   ├── start-container
│   ├── supervisord.conf
│   └── php.ini
└── mysql/
    └── create-testing-database.sh

docker-compose.yml
.env.docker
.dockerignore
```

## Runtime Processes

The `laravel` container runs multiple development processes through Supervisor:

| Process | Command | Purpose |
|---------|---------|---------|
| PHP server | `php artisan serve --host=0.0.0.0 --port=80` | Serves HTTP requests |
| Queue worker | `php artisan queue:work` | Processes background jobs |
| S3 notification poller | `php artisan s3:poll-upload-notifications` | Polls upload notifications |
| WebSocket server | `php artisan websockets:serve --host=0.0.0.0` | Sends real-time progress updates |

The `start-container` script waits for MySQL and Redis before starting Supervisor.

## Common Commands

```bash
# Start services
docker compose up -d

# Rebuild after Dockerfile changes
docker compose up -d --build

# Stop services
docker compose down

# View logs
docker compose logs -f
docker compose logs -f laravel

# Check status
docker compose ps
```

## Laravel Commands

```bash
docker compose exec laravel php artisan migrate
docker compose exec laravel php artisan migrate:fresh
docker compose exec laravel php artisan optimize:clear
docker compose exec laravel php artisan test
docker compose exec laravel php artisan tinker
```

## Dependencies And Assets

```bash
docker compose exec laravel composer install
docker compose exec laravel npm install
docker compose exec laravel npm run build
docker compose exec laravel ./vendor/bin/pint
```

## Database And Redis

```bash
# MySQL CLI from the MySQL container
docker compose exec mysql mysql -u sail -ppassword csv_upload

# Laravel database shell
docker compose exec laravel php artisan db

# Redis CLI
docker compose exec redis redis-cli
```

## Environment Notes

Inside containers, use Compose service names:

```env
DB_HOST=mysql
DB_PORT=3306
REDIS_HOST=redis
REDIS_PORT=6379
```

For browser WebSocket connections, use the host-facing address:

```env
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
VITE_PUSHER_HOST=localhost
```

## Troubleshooting

### Port Already In Use

```bash
lsof -i :8000
lsof -i :3307
lsof -i :6380
lsof -i :6001
```

Change the relevant port in `.env` or stop the conflicting local service.

### Queue Not Processing

```bash
docker compose logs -f laravel
docker compose restart laravel
docker compose exec laravel php artisan queue:failed
```

### WebSocket Not Connecting

```bash
docker compose logs -f laravel
docker compose exec laravel ps aux | grep websockets
```

Check `PUSHER_*` and `VITE_PUSHER_*` values in `.env`.

### MySQL Connection Refused

```bash
docker compose ps
docker compose logs -f mysql
```

The Laravel container depends on MySQL and Redis health checks, but first-time MySQL startup can still take a little time.

### Rebuild From Scratch

```bash
docker compose down -v
docker compose up -d --build
docker compose exec laravel composer install
docker compose exec laravel npm install
docker compose exec laravel php artisan migrate
```

`docker compose down -v` removes database and Redis volumes.
