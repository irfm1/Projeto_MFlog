<?php

namespace App\Livewire\Logs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

/**
 * LogsFilter Component
 * 
 * Painel de filtros avançados para logs com períodos pré-definidos.
 * Sincroniza com LogsTable via atribute parent property binding.
 */
class LogsFilter extends Component
{
    // Inicializar nos mount para não ter erros
    public ?array $types = null;
    public ?array $severities = null;
    public ?string $searchText = null;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $sortBy = 'timestamp';
    public string $sortOrder = 'desc';
    public ?string $fullLoadMessage = null;
    public ?string $fullLoadMessageType = null;

    public function mount(): void
    {
        // Padrão: últimos 30 dias
        $this->applyPeriodDays(30);

        $this->dispatchFiltersUpdated();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['searchText', 'dateFrom', 'dateTo'], true)) {
            $this->dispatchFiltersUpdated();
        }
    }

    /**
     * Define período pré-definido com base na seleção
     */
    public function setPeriod(string $period): void
    {
        match ($period) {
            'today' => $this->applyPeriodDays(0),
            'last-7' => $this->applyPeriodDays(7),
            'last-30' => $this->applyPeriodDays(30),
            'last-60' => $this->applyPeriodDays(60),
            'last-90' => $this->applyPeriodDays(90),
            default => null,
        };

        $this->dispatchFiltersUpdated();
    }

    /**
     * Define filtro por tipo de log
     */
    public function toggleLogType(string $type): void
    {
        if (!$this->types) {
            $this->types = [];
        }

        $key = array_search($type, $this->types);
        if ($key !== false) {
            unset($this->types[$key]);
            $this->types = array_values($this->types);
            if (empty($this->types)) {
                $this->types = null;
            }
        } else {
            $this->types[] = $type;
        }

        $this->dispatchFiltersUpdated();
    }

    /**
     * Define filtro por severidade
     */
    public function toggleSeverity(string $severity): void
    {
        if (!$this->severities) {
            $this->severities = [];
        }

        $key = array_search($severity, $this->severities);
        if ($key !== false) {
            unset($this->severities[$key]);
            $this->severities = array_values($this->severities);
            if (empty($this->severities)) {
                $this->severities = null;
            }
        } else {
            $this->severities[] = $severity;
        }

        $this->dispatchFiltersUpdated();
    }

    /**
     * Reset todos os filtros
     */
    public function resetFilters(): void
    {
        $this->types = null;
        $this->severities = null;
        $this->searchText = null;
        $this->applyPeriodDays(30);
        $this->sortBy = 'timestamp';
        $this->sortOrder = 'desc';

        $this->dispatchFiltersUpdated();
    }

    /**
     * Executa full load rápido para hoje e ontem (2 dias), sem migrations.
     */
    public function runQuickFullLoad(): void
    {
        $this->fullLoadMessage = null;
        $this->fullLoadMessageType = null;

        try {
            Artisan::call('etl:full-load', [
                '--no-migrate' => true,
                '--days' => 2,
            ]);

            // Ajusta visualmente o filtro para hoje + ontem após recarga.
            $today = Carbon::today();
            $this->dateTo = $today->toDateString();
            $this->dateFrom = $today->copy()->subDay()->toDateString();
            $this->dispatchFiltersUpdated();

            $this->fullLoadMessageType = 'success';
            $this->fullLoadMessage = 'Full load de 2 dias concluído com sucesso.';
        } catch (\Throwable $e) {
            $this->fullLoadMessageType = 'error';
            $this->fullLoadMessage = 'Erro no full load de 2 dias: ' . $e->getMessage();
        }
    }

    private function applyPeriodDays(int $days): void
    {
        $today = Carbon::today();
        $this->dateTo = $today->toDateString();
        $this->dateFrom = $today->copy()->subDays($days)->toDateString();
    }

    private function dispatchFiltersUpdated(): void
    {
        $this->dispatch(
            'filters-updated',
            types: $this->types,
            severities: $this->severities,
            searchText: $this->searchText,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo
        );
    }

    public function render()
    {
        return view('livewire.logs.logs-filter', [
            'types' => $this->types,
            'severities' => $this->severities,
            'searchText' => $this->searchText,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }
}
