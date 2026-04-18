<?php

namespace App\Events;

use App\DTOs\LogItemDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CaixaLogDetected Event
 * 
 * Disparado quando uma nova transação de caixa é detectada.
 * Broadcastado no canal 'logs.financeiro'
 */
class CaixaLogDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LogItemDTO $log
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('logs.financeiro'),
            new Channel('logs.caixa'),
            new Channel('logs'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'caixa',
            'log' => $this->log->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'caixa_log.detected';
    }
}
