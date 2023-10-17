# CSV Upload

The **CSV Upload** project is a Laravel-based web application designed to streamline the process of uploading CSV files, processing their data, and storing it in a database. The application leverages Laravel's queue system for efficient background job processing and utilizes WebSockets to provide users with real-time updates on the progress of their data upload.

## Table of Contents

-   [Prerequisites](#prerequisites)
-   [Getting Started](#getting-started)
-   [Configuration](#configuration)
-   [Running Queue Workers](#running-queue-workers)
-   [Running Websockets Server](#running-websockets-server)
-   [Usage](#usage)
-   [Features](#features)
-   [Contributing](#contributing)
-   [License](#license)

## Prerequisites

Before you begin, ensure you have the following prerequisites installed on your system:

-   [PHP](https://www.php.net/) (v8.1 or higher)
-   [Composer](https://getcomposer.org/) (v2 or higher)
-   [Node.js](https://nodejs.org/) (v18 or higher)
-   [npm](https://www.npmjs.com/) (v9 or higher)
-   [MySQL Database](https://www.mysql.com/)

You can verify the installed versions by running the following commands:

```bash
php --version
composer --version
node --version
npm --version
```

## Getting Started

To get started with the project, follow these steps:

1. Install PHP dependencies:

    ```bash
    composer install
    ```

2. Install JavaScript dependencies:

    ```bash
    npm install
    ```

3. Building the Application:

    ```bash
    npm run build
    ```

4. Copy the `.env.example` file to `.env` and configure your environment variables:

    ```bash
    cp .env.example .env
    ```

5. Generate an application key:

    ```bash
    php artisan key:generate
    ```

6. Run database migrations:

    ```bash
    php artisan migrate
    ```

7. Start the development server:

    ```bash
    php artisan serve
    ```

8. Access your application at `http://localhost:8000`.

## Running Queue Workers

To process queued jobs and tasks, you can use the following command:

```bash
php artisan queue:work
```

This command will start the queue worker, which is essential for background processing and handling tasks asynchronously.

## Running Websockets Server

To run the WebSockets server for real-time features, you can use the following command:

```bash
php artisan websockets:serve
```

## Configuration

-   Update the `.env` file with your database credentials.
    -   `DB_CONNECTION`: Set this to your database connection type (e.g., `mysql`).
    -   `DB_HOST`: Specify the database host (e.g., `127.0.0.1`).
    -   `DB_PORT`: Set the database port (e.g., `3306` for MySQL).
    -   `DB_DATABASE`: Enter the name of your database (e.g., `laravel`).
    -   `DB_USERNAME`: Provide your database username (e.g., `root`).
    -   `DB_PASSWORD`: Enter the corresponding password for the database user. If your database has no password, leave this field empty.

## Usage Instructions

1. **Download CSV Files**:

    - Start by downloading the necessary CSV files from the provided links:
        - [yoprint_test_import.csv](https://drive.google.com/file/d/1gHgR6KPxTZ78Z2zIj4XKt168A9Q6jdet/view?usp=drive_link)
        - [yoprint_test_updated.csv](https://drive.google.com/file/d/11Fp4Sh3Jfu3kH40UzDJc20-0DdW2zGNg/view?usp=drive_link)
        - [yoprint_test_import_1m.csv](https://drive.google.com/file/d/1v16Nr7c_rXGeEmJdnwuwKqpAGYGGaU6V/view?usp=drive_link)
        - [yoprint_test_import - Failed.csv](https://drive.google.com/file/d/1DP0S8TK-sBno8T-n8bOjcBgSSgd937V0/view?usp=drive_link)

2. **Upload yoprint_test_import.csv**:

    - After downloading, navigate to the web application's index page.
    - Select the `yoprint_test_import.csv` file for upload.
    - Click the "Upload" button to initiate the data import process.

    You will see the progress of the data import and the percentage completed in realtime as the file is processed.

3. **Upload yoprint_test_updated.csv**:

    - Once the import of `yoprint_test_import.csv` is complete, proceed to upload the `yoprint_test_updated.csv` file.
    - This file contains data with unique keys that will be used to update existing records.

    After uploading, the application will identify matching records and update them accordingly.

4. **Upload yoprint_test_import - Failed.csv**:

    - To observe how the application handles issues, upload the `yoprint_test_import - Failed.csv` file.
    - This file is intentionally formatted to trigger errors during processing.

    As a result, the status of the file will change to "failed," demonstrating the application's ability to handle and report errors.

5. **Uploading 1 Million Records (Optional)**:

    If you want to process a large dataset with one million records, upload the `yoprint_test_import_1m.csv` file.
    Consider the following before you start the import process:

    - Ensure your server and database are properly configured to handle such a large dataset.
    - It's recommended to perform this upload in a controlled environment due to the potentially longer processing time.
    - Please be patient, as the application will take more time to process and import one million records.

These instructions will guide you through the process of using the web application to upload, process, and update CSV files. Remember to download the provided CSV files, **run queue jobs**, **run websockets server** and follow the steps in sequence for a complete demonstration of the application's capabilities.

## Web Application Features

-   **CSV File Upload**: Easily upload CSV files through the web interface.

-   **Real-Time Progress Tracking**: The application provides live updates on data import progress with a percentage completion indicator.

-   **Data Update**: The application supports data updates using unique keys from the `yoprint_test_updated.csv` file, enabling efficient data maintenance.

-   **Error Handling**: It effectively handles errors in data processing, as demonstrated when uploading `yoprint_test_import - Failed.csv`, with the status of the file changed to "failed."

These features make the web application a robust tool for managing and processing CSV data with real-time feedback and error handling capabilities.
