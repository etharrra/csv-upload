<?php

namespace App\Console\Commands;

use App\Enums\FileStatus;
use App\Models\File;
use App\Services\FileService;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollS3UploadNotifications extends Command
{
    protected $signature = 's3:poll-upload-notifications {--once : Poll once and exit}';

    protected $description = 'Poll SQS for S3 upload-complete notifications and start CSV processing.';

    public function __construct(
        private readonly FileService $fileService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $queueUrl = (string) config('services.aws.s3_upload_notification_queue_url');

        if ($queueUrl === '') {
            $this->error('AWS_S3_UPLOAD_NOTIFICATION_QUEUE_URL is not configured.');

            return self::FAILURE;
        }

        $client = $this->sqsClient();

        do {
            $messages = $this->receiveMessages($client, $queueUrl);

            foreach ($messages as $message) {
                if ($this->processMessage($message)) {
                    $client->deleteMessage([
                        'QueueUrl' => $queueUrl,
                        'ReceiptHandle' => $message['ReceiptHandle'],
                    ]);
                }
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function sqsClient(): SqsClient
    {
        $config = [
            'version' => 'latest',
            'region' => config('services.aws.region'),
        ];

        if (config('services.aws.key') && config('services.aws.secret')) {
            $config['credentials'] = [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ];
        }

        return new SqsClient($config);
    }

    private function receiveMessages(SqsClient $client, string $queueUrl): array
    {
        $result = $client->receiveMessage([
            'QueueUrl' => $queueUrl,
            'MaxNumberOfMessages' => 10,
            'WaitTimeSeconds' => 20,
            'MessageAttributeNames' => ['All'],
        ]);

        return $result->get('Messages') ?? [];
    }

    private function processMessage(array $message): bool
    {
        try {
            $records = $this->extractRecords((string) ($message['Body'] ?? ''));

            foreach ($records as $record) {
                $this->processRecord($record);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to process S3 upload notification: '.$e->getMessage(), [
                'exception' => $e,
                'message_id' => $message['MessageId'] ?? null,
            ]);

            return false;
        }
    }

    private function extractRecords(string $body): array
    {
        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (isset($payload['Message']) && is_string($payload['Message'])) {
            $payload = json_decode($payload['Message'], true, flags: JSON_THROW_ON_ERROR);
        }

        return $payload['Records'] ?? [];
    }

    private function processRecord(array $record): void
    {
        $eventName = (string) ($record['eventName'] ?? '');

        if (! str_starts_with($eventName, 'ObjectCreated:')) {
            return;
        }

        $key = urldecode(str_replace('+', ' ', (string) data_get($record, 's3.object.key')));
        $prefix = trim((string) config('services.aws.upload_prefix', 'csv-uploads'), '/').'/';

        if ($key === '' || ! str_starts_with($key, $prefix)) {
            return;
        }

        $file = $this->findFileForKey($key, $prefix);

        if (! $file) {
            Log::warning('S3 upload notification did not match a file record.', ['key' => $key]);

            return;
        }

        if (in_array($file->status, [FileStatus::Processing, FileStatus::Completed], true)) {
            return;
        }

        $this->fileService->processStoredFile($file);
    }

    private function findFileForKey(string $key, string $prefix): ?File
    {
        $file = File::where('storage_disk', 's3')
            ->where('storage_path', $key)
            ->first();

        if ($file) {
            return $file;
        }

        $relativeKey = substr($key, strlen($prefix));
        $segments = explode('/', $relativeKey, 2);
        $fileId = filter_var($segments[0] ?? null, FILTER_VALIDATE_INT);

        if (! $fileId) {
            return null;
        }

        $file = File::where('storage_disk', 's3')->find($fileId);

        if (! $file || $file->status !== FileStatus::Pending || $file->job_batch_id !== null) {
            return null;
        }

        Log::info('S3 upload key differed from presigned storage path; using notification key.', [
            'file_id' => $file->id,
            'expected_key' => $file->storage_path,
            'actual_key' => $key,
        ]);

        $file->update(['storage_path' => $key]);

        return $file->refresh();
    }
}
