<?php

namespace App\Livewire\Logs;

use App\DTOs\LogItemDTO;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * LogsFeed Component
 * 
 * Exibe um feed em tempo real de logs com atualização via Broadcasting.
 */
class LogsFeed extends Component
{
    /** @var LogItemDTO[] */
    public array $logs = [];

    public int $maxItems = 15;
    public bool $autoPause = false;
    public bool $isPaused = false;

    /**
     * Recebe evento de novo log via broadcasting
     */
    #[On('echo:logs,LogDetected')]
    public function onLogDetected(array $data): void
    {
        if ($this->isPaused) {
            return;
        }

        // Cria DTO a partir dos dados broadcast
        try {
            $dto = LogItemDTO::fromArray($data);
            
            // Adiciona ao topo da lista
            array_unshift($this->logs, $dto);
            
            // Limita a quantidade de items exibidos
            $this->logs = array_slice($this->logs, 0, $this->maxItems);
            
            // Auto-pausa se atingir max
            if ($this->autoPause && count($this->logs) >= $this->maxItems) {
                $this->isPaused = true;
            }
        } catch (\Exception $e) {
            logger()->error('Erro ao processar log broadcast', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Limpa o feed
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->isPaused = false;
    }

    /**
     * Toggle pausa
     */
    public function togglePause(): void
    {
        $this->isPaused = !$this->isPaused;
    }

    public function render()
    {
        return view('livewire.logs.logs-feed');
    }
}
