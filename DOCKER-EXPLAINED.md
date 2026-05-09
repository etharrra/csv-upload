# Docker Folder Explanation

This document explains the Docker setup currently used by this Laravel CSV Upload project.

## Folder Structure

```text
docker/
├── 8.3/
│   ├── Dockerfile
│   ├── start-container
│   ├── supervisord.conf
│   └── php.ini
└── mysql/
    └── create-testing-database.sh
```

## Dockerfile

[docker/8.3/Dockerfile](docker/8.3/Dockerfile) builds the Laravel application image.

The image starts from Ubuntu:

```dockerfile
FROM ubuntu:24.04
```

It then installs PHP 8.3, Composer, Node.js/npm, the MySQL client, Supervisor, and PHP extensions used by this app during local development.

Important build arguments:

```dockerfile
ARG WWWGROUP
ARG NODE_VERSION=18
```

In [docker-compose.yml](docker-compose.yml), `NODE_VERSION` is set from the environment with a default of `20`:

```yaml
args:
  WWWGROUP: ${WWWGROUP:-1000}
  NODE_VERSION: ${NODE_VERSION:-20}
```

The Dockerfile installs a focused development toolset:

- PHP 8.3 CLI
- Composer
- Node.js and npm
- MySQL client
- Supervisor
- PHP extensions for MySQL, Redis, ZIP, XML, Curl, MBString, BCMath, Intl, Readline, PCNTL, and POSIX

The app code lives at:

```text
/var/www/html
```

Docker Compose mounts the project directory there.

## start-container

[docker/8.3/start-container](docker/8.3/start-container) is the container entrypoint.

It:

1. Updates the `sail` user ID when `WWWUSER` is set.
2. Creates a writable Composer directory at `/.composer`.
3. Runs one-off commands through `gosu`.
4. Waits for MySQL and Redis before starting Supervisor.

For normal container startup, it runs:

```bash
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

## supervisord.conf

[docker/8.3/supervisord.conf](docker/8.3/supervisord.conf) runs the long-lived local development processes.

| Program | Command | Purpose |
|---------|---------|---------|
| `php` | `php artisan serve --host=0.0.0.0 --port=80` | HTTP server |
| `queue` | `php artisan queue:work` | Background jobs |
| `s3-upload-notifications` | `php artisan s3:poll-upload-notifications` | S3 upload notification polling |
| `websocket` | `php artisan websockets:serve --host=0.0.0.0` | WebSocket progress updates |

Supervisor restarts the queue, S3 poller, and WebSocket server if they exit.

## php.ini

[docker/8.3/php.ini](docker/8.3/php.ini) configures PHP for this app:

```ini
post_max_size = 100M
upload_max_filesize = 100M
variables_order = EGPCS
opcache.enable_cli=1
```

The larger upload limits support CSV uploads, and `variables_order = EGPCS` ensures environment variables are available to Laravel.

## docker-compose.yml

[docker-compose.yml](docker-compose.yml) defines three services.

### laravel

The Laravel service builds the image from `docker/8.3`:

```yaml
laravel:
  build:
    context: ./docker/8.3
    dockerfile: Dockerfile
    args:
      WWWGROUP: ${WWWGROUP:-1000}
      NODE_VERSION: ${NODE_VERSION:-20}
```

It exposes:

| Host | Container | Purpose |
|------|-----------|---------|
| `8000` | `80` | Laravel app |
| `5173` | `5173` | Vite dev server/HMR |
| `6001` | `6001` | WebSocket server |

It mounts the project into the container:

```yaml
volumes:
  - .:/var/www/html
```

Code changes on your machine are immediately visible inside the container.

### mysql

The MySQL service uses `mysql:8.0`.

On first startup it creates:

- database: `csv_upload`, or `DB_DATABASE` from `.env`
- user: `sail`, or `DB_USERNAME` from `.env`
- password: `password`, or `DB_PASSWORD` from `.env`

It also mounts `docker/mysql/create-testing-database.sh` into MySQL's initialization directory to create a testing database.

MySQL data persists in the `sail-mysql` volume.

### redis

The Redis service uses `redis:7-alpine`.

Laravel uses Redis for queues and cache. Redis data persists in the `sail-redis` volume.

## Networking

All services join the `sail` network:

```yaml
networks:
  sail:
    driver: bridge
```

Containers communicate by service name:

- Laravel connects to MySQL at `mysql:3306`
- Laravel connects to Redis at `redis:6379`

Inside a container, `localhost` means that container itself. Use service names for container-to-container traffic.

## Volumes

| Volume | Purpose |
|--------|---------|
| `.:/var/www/html` | Mounts the project into the Laravel container |
| `sail-mysql:/var/lib/mysql` | Persists MySQL data |
| `sail-redis:/data` | Persists Redis data |

To remove persisted MySQL and Redis data:

```bash
docker compose down -v
```

## Request Flow

```text
Browser
  |
  | http://localhost:8000
  v
Laravel container
  |
  | DB_HOST=mysql
  v
MySQL container

Laravel container
  |
  | REDIS_HOST=redis
  v
Redis container

Laravel container
  |
  | websocket :6001
  v
Browser progress updates
```

## Quick Reference

| File | Purpose |
|------|---------|
| `docker/8.3/Dockerfile` | Builds the Ubuntu/PHP 8.3 app image |
| `docker/8.3/start-container` | Entrypoint and dependency wait script |
| `docker/8.3/supervisord.conf` | Runs app server, queue, S3 poller, and WebSocket server |
| `docker/8.3/php.ini` | PHP upload and CLI settings |
| `docker-compose.yml` | Defines Laravel, MySQL, Redis, ports, volumes, and network |
| `.env.docker` | Docker-oriented environment defaults |

## Common Questions

### Why Supervisor?

For local development, one container runs several Laravel processes. Supervisor keeps them running and restarts the worker processes if they exit.

### Why Ports 3307 And 6380?

Host ports `3307` and `6380` avoid conflicts with MySQL or Redis running directly on your machine.

### Does Data Survive Restarts?

Yes. MySQL and Redis use named volumes. Data is removed only when you run:

```bash
docker compose down -v
```
