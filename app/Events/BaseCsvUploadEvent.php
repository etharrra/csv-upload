<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseCsvUploadEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $batch_arr;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $batchId,
        int $progress,
        int $failedJobs,
        bool $finished,
        int $pendingJobs
    ) {
        $this->batch_arr = compact('batchId', 'progress', 'failedJobs', 'finished', 'pendingJobs');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'batch' => $this->batch_arr,
        ];
    }
}
