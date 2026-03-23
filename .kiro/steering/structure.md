# Project Structure

Standard Laravel 10 layout with the following conventions:

```
app/
  Console/          # Scheduled commands (Kernel.php)
  Enums/            # PHP 8.1 backed enums (e.g. FileStatus: int)
  Events/           # Broadcastable events (extend BaseCsvUploadEvent)
  Http/
    Controllers/    # Thin controllers — delegate to Services
    Middleware/     # Standard Laravel middleware
    Requests/       # Form Request classes for validation
  Jobs/             # Queueable jobs (implement ShouldQueue + Batchable)
  Listeners/        # Event subscribers (CSVUploadEventSubscriber)
  Models/           # Eloquent models
  Providers/        # Service providers (events registered in EventServiceProvider)
  Services/         # Business logic layer (FileService, JobBatchService)

database/
  migrations/       # Schema migrations (anonymous class style)
  factories/        # Model factories
  seeders/

resources/
  views/            # Blade templates
  js/               # app.js (Echo/Pusher setup), bootstrap.js
  css/              # app.css (Tailwind entry)

routes/
  web.php           # Only two routes: GET / and POST /upload
  api.php           # Unused currently
  channels.php      # Broadcast channel auth

config/             # Standard Laravel config files + websockets.php
docker/             # Sail-compatible Dockerfiles for PHP 8.0–8.3
```

## Conventions

- Controllers are thin — validation via Form Requests, logic in Services
- Services are injected via constructor DI (no facades in service classes)
- Events broadcast on public channels; use `broadcastAs()` to set event name and `broadcastWith()` to control payload
- Event listeners are registered as subscribers (`$subscribe` array in `EventServiceProvider`), not individual `$listen` mappings
- Jobs use `Batchable` trait; always check `$this->batch()?->cancelled()` at the start of `handle()`
- CSV rows are chunked into batches of 1000 before dispatching jobs
- Enums are used for model status fields with `$casts` in the model
- Migrations use anonymous classes (`return new class extends Migration`)
- PSR-4 autoloading: `App\` → `app/`, `Tests\` → `tests/`
