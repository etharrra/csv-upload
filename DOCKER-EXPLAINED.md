# Docker Folder Explanation

This document explains the Docker setup for this Laravel CSV Upload application.

## Folder Structure

```
docker/
├── 8.2/                         # PHP 8.2 version (used)
│   ├── Dockerfile               # The "recipe" - what to install
│   ├── start-container          # Startup script (runs when container starts)
│   ├── supervisord.conf         # Process manager config (runs multiple processes)
│   └── php.ini                  # PHP settings
└── mysql/
    └── create-testing-database.sh  # Creates testing DB on first run
```

## File Explanations

### 1. Dockerfile - The "Recipe"

Think of this as a blueprint for creating a server from scratch:

```dockerfile
# Start with a base (like installing OS)
FROM ubuntu:22.04

# Set where files will go
WORKDIR /var/www/html

# Install PHP + Extensions + Node.js + Composer
# This is like running "apt-get install php", "npm install" etc.
RUN apt-get update \
    && apt-get install -y php8.2-cli php8.2-mysql ...nodejs ...

# Create a user called "sail"
RUN useradd -ms /bin/bash sail

# Copy config files into the container
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.2/cli/conf.d/99-sail.ini

# Open ports
EXPOSE 8000 6001

# Start the container (runs start-container script)
ENTRYPOINT ["start-container"]
```

**In simple terms:** This creates a Linux server with PHP 8.2, Node.js, Composer, and all necessary extensions pre-installed.

---

### 2. start-container - What Happens When Container Starts

```bash
#!/usr/bin/env bash

# If WWWUSER env is set, change the user ID
if [ ! -z "$WWWUSER" ]; then
    usermod -u $WWWUSER sail
fi

# If no arguments, run supervisord (manages processes)
if [ $# -gt 0 ]; then
    exec gosu $WWWUSER "$@"
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
```

**In simple terms:**
- When container starts with no arguments → runs `supervisord` (starts all your services)
- When you run `docker compose exec laravel php artisan migrate` → runs that command instead

---

### 3. supervisord.conf - Running Multiple Processes

Docker containers normally run **one** command. But Laravel needs **three** things running:

```ini
[supervisord]
nodaemon=true          # Keep running (don't exit)
user=root

# Process 1: PHP Development Server
[program:php]
command=/usr/bin/php artisan serve --host=0.0.0.0 --port=80
user=sail

# Process 2: Queue Worker (processes background jobs)
[program:queue]
command=/usr/bin/php artisan queue:work
user=sail
autostart=true
autorestart=true       # Restart if it crashes

# Process 3: WebSocket Server (real-time updates)
[program:websocket]
command=/usr/bin/php artisan websockets:serve --host=0.0.0.0
user=sail
autostart=true
autorestart=true
```

**In simple terms:** Supervisord is like a mini-systemd inside the container. It keeps PHP server, queue worker, and WebSocket server running. If any crashes, it auto-restarts them.

---

### 4. php.ini - PHP Settings

```ini
[PHP]
post_max_size = 100M        # Allow larger uploads
upload_max_filesize = 100M  # Allow larger CSV files
variables_order = EGPCS      # Environment variables order

[opcache]
opcache.enable_cli=1         # Speed up PHP in CLI mode
```

---

## docker-compose.yml - Detailed Explanation

This file defines **what containers to run** and **how they connect**.

### Service 1: Laravel (Your App)

```yaml
laravel:
  build:
    context: ./docker/8.2      # Where to find Dockerfile
    dockerfile: Dockerfile      # Which Dockerfile to use
    args:                       # Build arguments
      WWWGROUP: ${WWWGROUP:-1000}         # User group ID (default: 1000)
      NODE_VERSION: ${NODE_VERSION:-20}    # Node.js version
  image: csv-upload/laravel    # Name the built image
  container_name: csv-upload   # Name the running container
```

**What this does:**
1. Takes `docker/8.2/Dockerfile`
2. Builds a Docker image
3. Passes `WWWGROUP` and `NODE_VERSION` as variables during build

```yaml
  extra_hosts:
    - "host.docker.internal:host-gateway"
```

**What this does:** Allows the container to talk to your Mac (host machine). Useful for Xdebug or accessing services on your Mac.

```yaml
  ports:
    - "${APP_PORT:-8000}:80"      # Mac port 8000 → Container port 80
    - "${VITE_PORT:-5173}:5173"    # Vite hot reload
    - "6001:6001"                   # WebSocket
```

**Format:** `"HOST_PORT:CONTAINER_PORT"`

- Your browser → `localhost:8000` → Container's port 80
- WebSocket → `localhost:6001` → Container's port 6001

```yaml
  environment:
    WWWUSER: ${WWWGROUP:-1000}
    LARAVEL_SAIL: 1
    PUSHER_HOST: ${PUSHER_HOST:-127.0.0.1}
    # ... more env vars
```

**What this does:** Sets environment variables inside the container. `${VAR:-default}` means: use value from `.env` file, or use `default` if not set.

```yaml
  volumes:
    - .:/var/www/html
```

**What this does:**
- `.` (your project folder on Mac)
- `:` (maps to)
- `/var/www/html` (inside container)

Changes you make on Mac appear instantly in the container!

```yaml
  networks:
    - sail
```

**What this does:** Connects this container to the `sail` network. All containers on same network can talk to each other by service name.

```yaml
  depends_on:
    - mysql
    - redis
```

**What this does:** Ensures MySQL and Redis start before Laravel.

```yaml
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost"]
    retries: 3
    timeout: 5s
```

**What this does:** Every 5 seconds, Docker checks if Laravel is responding. `docker compose ps` shows "healthy" or "unhealthy".

---

### Service 2: MySQL (Database)

```yaml
mysql:
  image: mysql:8.0              # Use official MySQL image
  container_name: csv-upload-mysql
  ports:
    - "${FORWARD_DB_PORT:-3307}:3306"
```

**Why port 3307?** Your Mac already has MySQL on port 3306. Using 3307 avoids conflict.

```yaml
  environment:
    MYSQL_ROOT_PASSWORD: root            # Root password
    MYSQL_ROOT_HOST: "%"                 # Allow connections from any host
    MYSQL_DATABASE: csv_upload            # Create this database
    MYSQL_USER: sail                     # Create this user
    MYSQL_PASSWORD: password             # User's password
```

**What this does:** On first start, MySQL automatically:
1. Creates database `csv_upload`
2. Creates user `sail` with password `password`
3. Gives `sail` full access to `csv_upload`

```yaml
  volumes:
    - sail-mysql:/var/lib/mysql
```

**What this does:** Stores MySQL data in a named volume called `sail-mysql`. Data survives container restarts.

```yaml
    - ./docker/mysql/create-testing-database.sh:/docker-entrypoint-initdb.d/create-testing-database.sh
```

**What this does:** Runs a script on first start to create a `testing` database for PHPUnit tests.

---

### Service 3: Redis (Queue & Cache)

```yaml
redis:
  image: redis:7-alpine         # Lightweight Redis image
  container_name: csv-upload-redis
  ports:
    - "${FORWARD_REDIS_PORT:-6380}:6379"
  volumes:
    - sail-redis:/data          # Persist Redis data
  networks:
    - sail
```

**What this does:**
- Runs Redis 7 (alpine = minimal size)
- Port 6380 on Mac → 6379 in container
- Stores queue jobs and cache

---

### Networks Section

```yaml
networks:
  sail:
    driver: bridge
```

**What this does:**
- Creates a private network called `sail`
- All containers connect to it
- They can talk to each other by service name:
  - Laravel connects to MySQL via `mysql:3306` (not `localhost:3306`)
  - Laravel connects to Redis via `redis:6379`

---

### Volumes Section

```yaml
volumes:
  sail-mysql:
    driver: local
  sail-redis:
    driver: local
```

**What this does:**
- Creates persistent storage volumes
- Data survives `docker compose down`
- Only deleted with `docker compose down -v`

---

## How Everything Connects Together

```
┌─────────────────────────────────────────────────────────────────────┐
│                         YOUR MAC (HOST)                             │
│                                                                     │
│  Browser → localhost:8000 ──┐                                       │
│                              │                                       │
│  MySQL CLI → localhost:3307 ─┼──┐                                    │
│                              │  │                                    │
│  Redis CLI → localhost:6380 ─┼──┼──┐                                │
│                              │  │  │                                 │
└──────────────────────────────┼──┼──┼────────────────────────────────┘
                               │  │  │
                    ┌──────────┼──┼──┼──────────┐
                    │          │  │  │          │
                    │   ┌──────▼──▼──▼──────┐    │
                    │   │  Docker Network   │    │
                    │   │     (sail)        │    │
                    │   └──────────────────┘    │
                    │                            │
                    │  ┌─────────────────────┐    │
                    │  │ laravel container   │    │
                    │  │  ├─ PHP server      │    │
                    │  │  ├─ Queue worker    │    │
                    │  │  └─ WebSocket       │    │
                    │  └─────────────────────┘    │
                    │            │                 │
                    │            ▼                 │
                    │  ┌─────────────────────┐    │
                    │  │  mysql container    │    │
                    │  │  (database)         │    │
                    │  └─────────────────────┘    │
                    │            │                 │
                    │            ▼                 │
                    │  ┌─────────────────────┐    │
                    │  │  redis container    │    │
                    │  │  (queue/cache)      │    │
                    │  └─────────────────────┘    │
                    │                            │
                    └────────────────────────────┘
```

---

## Quick Reference

| File/Folder | Purpose |
|-------------|---------|
| `docker/8.2/Dockerfile` | Blueprint for building PHP container |
| `docker/8.2/start-container` | Runs when container starts |
| `docker/8.2/supervisord.conf` | Manages multiple processes in one container |
| `docker/8.2/php.ini` | PHP configuration settings |
| `docker-compose.yml` | Defines all services and how they connect |
| `.env.docker` | Environment variables for Docker |

---

## Common Questions

### Why Supervisord?

Docker containers run **one process**. But Laravel needs three:
1. PHP server (web requests)
2. Queue worker (background jobs)
3. WebSocket server (real-time updates)

Supervisord manages all three in one container.

### Why Port 3307/6380 Instead of 3306/6379?

You have local MySQL/Redis running on default ports. Using different ports avoids conflicts.

### How Do Containers Talk to Each Other?

Via service names on the Docker network:
- `mysql:3306` - connect to MySQL
- `redis:6379` - connect to Redis

**Never use `localhost` inside containers!** `localhost` inside a container refers to the container itself, not your Mac.

### Why Does Code Sync Instantly?

The volume mount `.:/var/www/html` maps your project folder directly into the container. Any file change you make is immediately visible inside the container.

### Does Data Survive Container Restarts?

Yes! Named volumes (`sail-mysql`, `sail-redis`) persist data even when containers stop.

To wipe everything:
```bash
docker compose down -v# -v flag removes volumes
```