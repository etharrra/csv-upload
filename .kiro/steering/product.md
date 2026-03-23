# Product

CSV Upload is a Laravel web application for uploading, processing, and storing CSV product data. Users upload CSV files through a web interface; the app processes them in background queue jobs and streams real-time progress back to the browser via WebSockets.

## Core Capabilities

- CSV file upload with validation (csv, xlsx, xls)
- Background batch processing using Laravel job batches (chunks of 1000 rows)
- `updateOrCreate` upsert logic keyed on `unique_key` for the `products` table
- Real-time progress tracking via WebSocket broadcasts on the `csv-uploads` channel
- File status lifecycle: Pending → Processing → Completed / Failed
- Upload history listing with pagination

## Domain Models

- `File` — tracks each upload (name, size, status, linked batch ID)
- `Product` — the target data model populated/updated from CSV rows
