<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvFileUploaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $fileName,
        public string $batchId,
        public string $fileId,
        public string $uploadedAt,
        public string $status = 'Pending'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('csv-uploads'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'file.uploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'fileName' => $this->fileName,
            'batchId' => $this->batchId,
            'fileId' => $this->fileId,
            'uploadedAt' => $this->uploadedAt,
            'status' => $this->status,
        ];
    }
}
