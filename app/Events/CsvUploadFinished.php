<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvUploadFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $batch_arr;

    /**
     * Create a new event instance.
     */
    public function __construct($batchId, $progress, $failedJobs, $finished, $pendingJobs)
    {
        $this->batch_arr = [
            'batchId' => $batchId,
            'progress' => $progress,
            'failedJobs' => $failedJobs,
            'finished' => $finished,
            'pendingJobs' => $pendingJobs
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public.csv-upload-finished.1'),
        ];
    }

    /**
     * Get the event name for broadcasting.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'csv-upload-finished';
    }

    public function broadcastWith()
    {
        return [
            'batch' => $this->batch_arr,
        ];
    }
}
