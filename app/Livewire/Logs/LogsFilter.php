<?php

namespace App\Livewire\Logs;

use Illuminate\Support\Carbon;
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

    public function mount(): void
    {
        // Padrão: últimos 30 dias
        $today = Carbon::today();
        $this->dateFrom = $today->subDays(30)->toDateString();
        $this->dateTo = $today->toDateString();

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
        $today = Carbon::today();
        
        match ($period) {
            'today' => [
                $this->dateFrom = $today->toDateString(),
                $this->dateTo = $today->toDateString(),
            ],
            'last-7' => [
                $this->dateFrom = $today->subDays(7)->toDateString(),
                $this->dateTo = $today->toDateString(),
            ],
            'last-30' => [
                $this->dateFrom = $today->subDays(30)->toDateString(),
                $this->dateTo = $today->toDateString(),
            ],
            'last-60' => [
                $this->dateFrom = $today->subDays(60)->toDateString(),
                $this->dateTo = $today->toDateString(),
            ],
            'last-90' => [
                $this->dateFrom = $today->subDays(90)->toDateString(),
                $this->dateTo = $today->toDateString(),
            ],
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
        $today = Carbon::today();
        $this->types = null;
        $this->severities = null;
        $this->searchText = null;
        $this->dateFrom = $today->subDays(30)->toDateString();
        $this->dateTo = $today->toDateString();
        $this->sortBy = 'timestamp';
        $this->sortOrder = 'desc';

        $this->dispatchFiltersUpdated();
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
