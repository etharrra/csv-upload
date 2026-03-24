# Repository Guidelines

## Project Overview
This is a Laravel 10 application for CSV file upload and processing. It uses Laravel Horizon for queue management, Laravel WebSockets for real-time progress updates, and Redis for queuing and caching.

## Project Structure & Module Organization

```
app/
├── Console/          # Console commands and kernel
├── Enums/            # PHP enums (e.g., FileStatus)
├── Events/           # Broadcast events for WebSocket updates
├── Exceptions/       # Custom exception handlers
├── Http/
│   ├── Controllers/  # HTTP controllers
│   ├── Middleware/   # Request middleware
│   ├── Requests/     # Form request validation classes
│   └── Kernel.php    # HTTP kernel configuration
├── Jobs/             # Queue jobs (implement ShouldQueue)
├── Listeners/        # Event listeners
├── Models/           # Eloquent models
├── Providers/        # Service providers
└── Services/         # Business logic services

database/
├── factories/        # Model factories for testing
├── migrations/       # Database migrations
└── seeders/          # Database seeders

resources/
├── js/               # Frontend JavaScript (Vite entry points)
└── views/            # Blade templates

routes/
├── web.php           # Web routes
├── api.php           # API routes
├── channels.php      # Broadcasting channels
└── console.php       # Console commands

tests/
├── Feature/          # Feature tests (HTTP, integration)
└── Unit/             # Unit tests (isolated class tests)
```

## Build, Test, and Development Commands

### Initial Setup
```bash
# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Copy environment file and generate key
cp .env.example .env && php artisan key:generate

# Run database migrations
php artisan migrate
```

### Docker Development (Recommended)
```bash
# Start all services (Laravel, MySQL, Redis, Queue, WebSocket)
docker compose up -d --build

# View container logs
docker compose logs -f

# Stop all containers
docker compose down

# Run artisan commands in container
docker compose exec laravel php artisan migrate
```

### Development Servers (Non-Docker)
```bash
# PHP development server
php artisan serve

# Frontend hot reload (Vite)
npm run dev

# Queue worker (restart after job code changes)
php artisan queue:work

# WebSocket server for real-time updates
php artisan websockets:serve

# Horizon dashboard (optional, for queue monitoring)
php artisan horizon
```

### Build Commands
```bash
# Build frontend assets for production
npm run build
```

### Linting & Code Style
```bash
# Format PHP code (must run before commits)
./vendor/bin/pint

# Check code style without modifying
./vendor/bin/pint --test
```

### Testing
```bash
# Run all tests
php artisan test
# OR
./vendor/bin/phpunit

# Run only feature tests
php artisan test --testsuite=Feature

# Run only unit tests
php artisan test --testsuite=Unit

# Run a specific test file
php artisan test tests/Feature/FileUploadTest.php

# Run a specific test method
php artisan test --filter test_file_upload_success

# Run tests with parallel execution
php artisan test --parallel

# Run tests with coverage report
php artisan test --coverage
```

### Database &Cache Commands
```bash
# Run pending migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Clear application cache
php artisan config:clear
php artisan cache:clear

# Clear all caches (config, route, view)
php artisan optimize:clear
```

## Coding Style & Conventions

### PHP Style (PSR-12)
- **Indentation**: 4spaces (no tabs)
- **Opening brace**: Same line for classes/methods
- **Namespace**: One blank line after `<?php`
- **Use statements**: One per line, alphabetically sorted

```php
<?php

namespace App\Services;

use App\Enums\FileStatus;
use App\Models\File;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FileService
{
    // ...
}
```

### Imports
- Group imports by type: framework first, then project classes
- Always import classes at the top of the file
- Use fully qualified names for facades only when necessary

```php
use App\Enums\FileStatus;        // Project classes first
use App\Models\File;
use Illuminate\Bus\Batch;         // Framework classes second
use Illuminate\Support\Facades\Log;
```

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `FileController` |
| Methods | camelCase | `processFile()` |
| Properties | camelCase | `$fileName` |
| Constants | UPPER_SNAKE | `MAX_ATTEMPTS` |
| Variables | camelCase | `$uploadedFile` |
| Database tables | snake_case | `files`, `products` |
| Migrations | snake_case with timestamp | `2024_01_15_create_files_table` |
| Jobs | Descriptive noun + verb | `ProcessUpdate` |
| Events | Past tense or noun | `CsvFileUploaded`, `CsvUploadProgress` |
| Enums | PascalCase case names | `FileStatus::Processing` |

### Type Declarations
- Always use return type declarations
- Use parameter types in method signatures
- Use union types when needed (`int|false`)
- Use `readonly` for constructor property promotion in PHP 8.1+

```php
public function __construct(
    private readonly array $rows,
    private readonly array $header
) {}

public function headerIndex(string $columnName): int|false
{
    return array_search($columnName, $this->header);
}
```

### Error Handling
- Use try-catch in controllers to catch exceptions and log errors
- Log errors with context using `Log::error()`
- Return user-friendly error messages, never expose stack traces

```php
public function store(FileUploadRequest $request): RedirectResponse
{
    try {
        $file = $this->fileService->processAndStoreFile($request->file('file'));
        return redirect()->route('files.index')->with('success-msg', 'Success!');
    } catch (\Throwable $e) {
        Log::error('File upload failed: ' . $e->getMessage(), ['exception' => $e]);
        return redirect()->route('files.index')->with('error-msg', 'Upload failed.');
    }
}
```

### Service Classes Pattern
- Services handle business logic and should be injected via constructor
- Use constructor property promotion for dependency injection
- Return typed values from service methods

```php
class FileService
{
    public function __construct(
        protected JobBatchService $jobBatchService
    ) {}

    public function getFiles(int $perPage = 15): LengthAwarePaginator
    {
        return File::latest()->paginate($perPage);
    }
}
```

### Form Request Validation
- Use Form Request classes for validation, not inline validation
- Separate `authorize()` and `rules()` methods

```php
class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,xlsx,xls',
        ];
    }
}
```

### Jobs Pattern
- Implement `ShouldQueue` for async processing
- Use `Batchable` trait for job batches
- Check if batch is cancelled before processing

```php
class ProcessUpdate implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        // Process job...
    }
}
```

### Events & Broadcasting
- Use events for decoupled communication
- Events broadcasting progress should implement `ShouldBroadcastNow`
- Use base event classes for shared functionality

```php
abstract class BaseCsvUploadEvent implements ShouldBroadcastNow
{
    public function broadcastWith(): array
    {
        return ['batch' => $this->batch_arr];
    }
}
```

## Security Best Practices
- Never commit `.env` or `.env.*` files
- Use Form Request classes for validation and authorization
- Sanitize file uploads (check mime types, size limits)
- Never expose internal errors to users
- Always validate file uploads with `mimes` and size rules
- Use CSRF protection (built into Laravel web routes)

## Queue & WebSocket Notes
- Restart queue workers after changing job code: `php artisan queue:restart`
- WebSocket server must be running for real-time progress: `php artisan websockets:serve`
- Use Redis for queue driver in production (`QUEUE_CONNECTION=redis`)
- Redis must be running before starting queue workers

## Git Commit Guidelines
- Use conventional commits format: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`
- Keep commits atomic and focused
- Reference issue numbers when applicable
- Run `./vendor/bin/pint` before committing

## Environment Variables
Key environment variables for this project:
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - Database connection
- `REDIS_HOST` - Redis connection for queues
- `QUEUE_CONNECTION=redis` - Queue driver
- `BROADCAST_DRIVER=pusher` - Broadcasting driver
- `PUSHER_*` - WebSocket/Pusher configuration