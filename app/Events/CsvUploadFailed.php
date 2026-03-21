<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;

class CsvUploadFailed extends BaseCsvUploadEvent
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public.csv-upload-failed.1'),
        ];
    }

    /**
     * Get the event name for broadcasting.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'csv-upload-failed';
    }
}
