<?php

namespace App\Events;

use App\DTOs\LogItemDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LogDetected Event
 * 
 * Disparado quando um novo log é detectado no Firebird.
 * Broadcastado via WebSocket para conectados em tempo real.
 */
class LogDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public LogItemDTO $log,
        public string $channel = 'logs'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * 
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel($this->channel),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'log' => $this->log->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Nome do evento para o frontend
     */
    public function broadcastAs(): string
    {
        return 'log.detected';
    }
}
