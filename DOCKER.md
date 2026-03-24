# Docker Development Setup

This document explains how Docker is configured for this Laravel CSV Upload application.

## Quick Start

```bash
# Copy Docker environment file
cp .env.docker .env

# Start all services
docker compose up -d

# Install dependencies (first time only)
docker compose exec laravel composer install
docker compose exec laravel npm install

# Run migrations
docker compose exec laravel php artisan migrate

# Access the application
open http://localhost:8000
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Docker Network (sail)                       │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ csv-upload (Laravel Container)                              ││
│  │  ┌─────────────────┐  ┌─────────────┐  ┌──────────────────┐ ││
│  │  │ PHP Server :80  │  │ Queue Worker│  │ WebSocket :6001  │ ││
│  │  │ (artisan serve) │  │(queue:work) │  │(websockets:serve)│ ││
│  │  └─────────────────┘  └─────────────┘  └──────────────────┘ ││
│  │         │                    │                    │         ││
│  │  ┌─────────────────────────────────────────────────────────┐││
│  │  │              Supervisord (Process Manager)              │││
│  │  │   Manages all 3 processes, auto-restarts on failure     │││
│  │  └─────────────────────────────────────────────────────────┘││
│  └─────────────────────────────────────────────────────────────┘│
│           │                    │                    │           │
│           ▼                    ▼                    ▼           │
│  ┌─────────────┐      ┌─────────────┐                           │
│  │ csv-upload  │      │ csv-upload  │                           │
│  │   mysql     │      │   redis     │                           │
│  │  (MySQL 8)  │      │  (Redis 7)  │                           │
│  └─────────────┘      └─────────────┘                           │
│       Port 3307           Port 6380                             │
└─────────────────────────────────────────────────────────────────┘
```

## Services

| Service | Image | Ports | Purpose |
|---------|-------|-------|---------|
| `laravel` | Custom build (PHP 8.2) | 8000→80, 5173→5173, 6001→6001 | Main app, queue worker, WebSocket server |
| `mysql` | mysql:8.0 | 3307→3306 | Database |
| `redis` | redis:7-alpine | 6380→6379 | Queue driver + Cache |

## File Structure

```
docker/
├── 8.2/
│   ├── Dockerfile        # PHP 8.2 + Node.js + Composer
│   ├── start-container   # Entrypoint script
│   ├── supervisord.conf  # Manages multiple processes
│   └── php.ini           # PHP configuration
└── mysql/
    └── create-testing-database.sh  # Creates testing database

docker-compose.yml        # Service definitions
.env.docker               # Docker environment variables
.dockerignore             # Files excluded from build
```

## Container Processes

The Laravel container runs **three processes** managed by Supervisord:

| Process | Command | Purpose |
|---------|---------|---------|
| PHP Server | `php artisan serve --host=0.0.0.0 --port=80` | Serves HTTP requests |
| Queue Worker | `php artisan queue:work` | Processes background jobs |
| WebSocket Server | `php artisan websockets:serve --host=0.0.0.0` | Real-time progress updates |

## Data Flow

```
User Uploads CSV
       │
       ▼
┌───────────────────┐
│ Laravel Controller│
│ (FileController)  │
└────────┬──────────┘
         │ Saves file record to MySQL
         │ Dispatches batch job to Redis
         ▼
┌──────────────────┐
│   Redis Queue    │
│  (Jobs waiting)  │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Queue Worker    │
│  (in container)  │
└────────┬─────────┘
         │ Processes CSV rows
         │ Updates Products table
         │ Broadcasts progress via WebSocket
         ▼
┌──────────────────┐
│ WebSocket Server │
│  (in container)  │
└────────┬─────────┘
         │ Pushes real-time updates to browser
         ▼
┌──────────────────┐
│  User's Browser  │
│  (progress bar)  │
└──────────────────┘
```

## Port Mappings

| Host Port | Container Port | Service |
|-----------|----------------|---------|
| 8000 | 80 | Laravel application |
| 5173 | 5173 | Vite hot module reload |
| 6001 | 6001 | WebSocket server |
| 3307 | 3306 | MySQL database |
| 6380 | 6379 | Redis |

Note: Ports 3307/6380 are used instead of defaults (3306/6379) to avoid conflicts with local MySQL/Redis.

## Volumes

| Volume | Purpose |
|--------|---------|
| `.:/var/www/html` | Syncs project files to container |
| `sail-mysql:/var/lib/mysql` | Persists MySQL data |
| `sail-redis:/data` | Persists Redis data |

## Environment Configuration

Key environment variables for Docker:

```env
# Database - Use service names, not localhost!
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=csv_upload
DB_USERNAME=sail
DB_PASSWORD=password

# Redis - Use service name
REDIS_HOST=redis
REDIS_PORT=6379

# WebSocket - Runs in same container
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http

# Frontend WebSocket - Browser connects to localhost
VITE_PUSHER_HOST=localhost
```

## Common Commands

### Container Management

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart all services
docker compose restart

# View logs (all services)
docker compose logs -f

# View logs (specific service)
docker compose logs -f laravel
docker compose logs -f mysql
docker compose logs -f redis

# Check container status
docker compose ps
```

### Laravel Commands

```bash
# Run migrations
docker compose exec laravel php artisan migrate

# Fresh database
docker compose exec laravel php artisan migrate:fresh

# Clear cache
docker compose exec laravel php artisan config:clear
docker compose exec laravel php artisan cache:clear

# Run tests
docker compose exec laravel php artisan test

# Tinker (REPL)
docker compose exec laravel php artisan tinker
```

### Dependency Management

```bash
# Install PHP dependencies
docker compose exec laravel composer install

# Install frontend dependencies
docker compose exec laravel npm install

# Build frontend
docker compose exec laravel npm run build

# Run linter
docker compose exec laravel ./vendor/bin/pint
```

### Database Access

```bash
# MySQL CLI
docker compose exec mysql mysql -u sail -ppassword csv_upload

# Or from Laravel container
docker compose exec laravel php artisan db
```

### Redis Access

```bash
# Redis CLI
docker compose exec redis redis-cli
```

## Troubleshooting

### Port Already in Use

If you get "port is already in use" errors:

```bash
# Check what's using the port
lsof -i :3306  # MySQL
lsof -i :6379  # Redis
lsof -i :8000 # Laravel

# Stop conflicting service
brew services stop mysql
brew services stop redis

# Or change port in docker-compose.yml
ports:
  - "3307:3306"  # Use 3307 instead of 3306
```

### Queue Not Processing

```bash
# Check queue worker logs
docker compose logs laravel | grep queue

# Restart queue worker
docker compose restart laravel

# Check failed jobs
docker compose exec laravel php artisan queue:failed
```

### WebSocket Not Connecting

1. Check `PUSHER_HOST=127.0.0.1` in `.env` (backend)
2. Check `VITE_PUSHER_HOST=localhost` in `.env` (frontend)
3. Verify WebSocket server is running:
   ```bash
   docker compose exec laravel ps aux | grep websocket
   ```

### Changes Not Reflected

```bash
# Restart container to pick up env changes
docker compose restart laravel

# Clear config cache
docker compose exec laravel php artisan config:clear
```

### MySQL Connection Refused

MySQL takes time to initialize. The container has a health check that waits for MySQL to be ready before starting Laravel.

If issues persist:
```bash
# Check MySQL status
docker compose logs mysql

# Wait for healthy status
docker compose ps
```

### Rebuild Everything

```bash
# Stop and remove everything
docker compose down -v

# Rebuild and start
docker compose up -d --build

# Run setup
docker compose exec laravel composer install
docker compose exec laravel npm install
docker compose exec laravel php artisan migrate
```

## Development Workflow

### Starting Development

```bash
# 1. Start containers
docker compose up -d

# 2. Check status
docker compose ps

# 3. Access app
open http://localhost:8000
```

### Making Code Changes

Code changes sync automatically via volume mount. For some changes:

| Change Type | Action |
|-------------|--------|
| PHP code | Auto-reload (no action needed) |
| Frontend (Vite) | Auto-reload via HMR |
| `.env` changes | `docker compose restart laravel` |
| `composer.json` | `docker compose exec laravel composer install` |
| `package.json` | `docker compose exec laravel npm install` |
| Config changes | `docker compose exec laravel php artisan config:clear` |

### Running Tests

```bash
# Run all tests
docker compose exec laravel php artisan test

# Run specific test file
docker compose exec laravel php artisan test tests/Feature/FileUploadTest.php

# Run with filter
docker compose exec laravel php artisan test --filter test_file_upload
```

### Stopping Development

```bash
# Stop containers (keeps data)
docker compose stop

# Stop and remove containers (keeps volumes)
docker compose down

# Stop and remove everything including data
docker compose down -v
```

## CI/CD Integration

This Docker setup is designed for local development but can be adapted for CI/CD:

```yaml
# Example GitHub Actions workflow
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Start services
        run: docker compose up -d
      
      - name: Install dependencies
        run: |
          docker compose exec laravel composer install --no-interaction
          docker compose exec laravel npm install
      
      - name: Run tests
        run: docker compose exec laravel php artisan test
```

## Security Notes

- Default passwords are for development only
- Never commit `.env.docker` or `.env` to version control
- In production, use secrets management and stronger passwords
- Consider using Docker secrets for sensitive data in production