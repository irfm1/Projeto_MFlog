<?php

namespace App\Events;

use App\DTOs\LogItemDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SystemLogDetected Event
 * 
 * Disparado quando um novo log de auditoria do sistema é detectado.
 * Broadcastado no canal 'logs.system'
 */
class SystemLogDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LogItemDTO $log
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('logs.system'),
            new Channel('logs'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'system',
            'log' => $this->log->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'system_log.detected';
    }
}
