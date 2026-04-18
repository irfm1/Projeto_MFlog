<?php

namespace App\Events;

use App\DTOs\LogItemDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CancelamentoLogDetected Event
 * 
 * Disparado quando um novo cancelamento crítico é detectado.
 * Broadcastado no canal 'logs.critico' (sempre monitora autoridades)
 */
class CancelamentoLogDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LogItemDTO $log
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('logs.critico'),
            new Channel('logs.cancelamento'),
            new Channel('logs'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'cancelamento',
            'log' => $this->log->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cancelamento_log.detected';
    }
}
