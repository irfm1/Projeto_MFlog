<?php

namespace App\Services;

use App\DTOs\LogItemDTO;
use Illuminate\Support\Collection;

/**
 * LogFormatterService
 * 
 * Formata LogItemDTO para diferentes outputs:
 * - JSON para API/broadcasting
 * - HTML para UI
 * - CSV/Excel para exportação
 * - Texto para logs de sistema
 */
class LogFormatterService
{
    /**
     * Formata DTO para JSON estruturado
     */
    public function toJson(LogItemDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Formata coleção de DTOs para JSON
     */
    public function collectionToJson(Collection $items): array
    {
        return $items->map(fn (LogItemDTO $dto) => $dto->toArray())
            ->values()
            ->all();
    }

    /**
     * Formata DTO para exibição em HTML/frontend
     */
    public function toHtml(LogItemDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'actor' => [
                'name' => $dto->actorName,
                'avatar' => $dto->actorAvatar,
            ],
            'action_html' => $this->formatActionHtml($dto),
            'timestamp' => $dto->timestamp->toIso8601String(),
            'timestamp_display' => $dto->humanReadableTime,
            'severity_badge' => $this->getSeverityBadge($dto->severity),
            'module_icon' => $dto->moduleIcon,
            'tags_display' => $this->formatTagsHtml($dto->tags),
            'value_display' => $this->formatValueHtml($dto),
        ];
    }

    /**
     * Formata DTO para CSV (exportação)
     */
    public function toCsv(LogItemDTO $dto): array
    {
        return [
            'ID' => $dto->id,
            'Data/Hora' => $dto->timestamp->format('d/m/Y H:i:s'),
            'Tempo Decorrido' => $dto->humanReadableTime,
            'Tipo' => $this->formatTypeLabel($dto->type),
            'Usuário' => $dto->actorName,
            'Ação' => $dto->action,
            'Módulo' => $dto->module,
            'Registro Relacionado' => $dto->relatedRecordName,
            'Tipo Operação' => $dto->operationType ?? '-',
            'Valor' => $dto->value ? $this->formatCurrency($dto->value, $dto->valueCurrency) : '-',
            'Tags' => implode('; ', $dto->tags),
            'Severidade' => $this->formatSeverityLabel($dto->severity),
        ];
    }

    /**
     * Formata coleção para CSV (com headers)
     */
    public function collectionToCsv(Collection $items): array
    {
        $headers = [
            'ID',
            'Data/Hora',
            'Tempo Decorrido',
            'Tipo',
            'Usuário',
            'Ação',
            'Módulo',
            'Registro Relacionado',
            'Tipo Operação',
            'Valor',
            'Tags',
            'Severidade',
        ];

        $rows = $items->map(fn (LogItemDTO $dto) => $this->toCsv($dto))
            ->values()
            ->all();

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Formata DTO para log de texto simple
     */
    public function toText(LogItemDTO $dto): string
    {
        $parts = [
            "[{$dto->timestamp->format('d/m/Y H:i:s')}]",
            "[$dto->severity]",
            $dto->actorName,
            $dto->action,
            $dto->relatedRecordName,
        ];

        if ($dto->value) {
            $parts[] = "({$this->formatCurrency($dto->value, $dto->valueCurrency)})";
        }

        return implode(' ', $parts);
    }

    /**
     * Formata para dashboard cards
     */
    public function toDashboardCard(LogItemDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'type' => $dto->type,
            'actor' => $dto->actorName,
            'avatar' => $dto->actorAvatar,
            'action' => $dto->action,
            'related' => $dto->relatedRecordName,
            'module' => $dto->module,
            'time' => $dto->humanReadableTime,
            'timestamp_iso' => $dto->timestamp->toIso8601String(),
            'severity' => $dto->severity,
            'severity_badge' => $this->getSeverityBadge($dto->severity),
            'icon' => $dto->moduleIcon,
            'value' => $dto->value ? $this->formatCurrency($dto->value, $dto->valueCurrency) : null,
            'tags' => $dto->tags,
        ];
    }

    /**
     * Formata ação com HTML
     */
    private function formatActionHtml(LogItemDTO $dto): string
    {
        $module_icon = "<i class=\"icon-{$dto->moduleIcon}\"></i>";

        if ($dto->relatedRecordUrl) {
            return "{$dto->action} <a href=\"{$dto->relatedRecordUrl}\">{$dto->relatedRecordName}</a>";
        }

        return $dto->action . ' ' . $dto->relatedRecordName;
    }

    /**
     * Retorna HTML/Tailwind para badge de severidade
     */
    private function getSeverityBadge(string $severity): array
    {
        return match ($severity) {
            'danger' => [
                'class' => 'bg-red-100 text-red-800',
                'icon' => 'x-circle',
                'label' => 'Crítico',
            ],
            'warning' => [
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'alert-circle',
                'label' => 'Aviso',
            ],
            'success' => [
                'class' => 'bg-green-100 text-green-800',
                'icon' => 'check-circle',
                'label' => 'Sucesso',
            ],
            'info' => [
                'class' => 'bg-blue-100 text-blue-800',
                'icon' => 'info-circle',
                'label' => 'Info',
            ],
            default => [
                'class' => 'bg-gray-100 text-gray-800',
                'icon' => 'help-circle',
                'label' => 'Outro',
            ],
        };
    }

    /**
     * Formata tags HTML
     */
    private function formatTagsHtml(array $tags): array
    {
        return array_map(fn ($tag) => [
            'label' => $tag,
            'class' => 'bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs',
        ], $tags);
    }

    /**
     * Formata valor monetário para HTML
     */
    private function formatValueHtml(LogItemDTO $dto): ?array
    {
        if (!$dto->value) {
            return null;
        }

        $class = match (true) {
            $dto->value > 0 => 'text-green-600 font-bold',
            $dto->value < 0 => 'text-red-600 font-bold',
            default => 'text-gray-600',
        };

        return [
            'value' => $this->formatCurrency($dto->value, $dto->valueCurrency),
            'class' => $class,
        ];
    }

    /**
     * Formata moeda
     */
    private function formatCurrency(float $amount, ?string $currency): string
    {
        $currency = $currency ?? 'BRL';

        if ($currency === 'BRL') {
            return 'R$ ' . number_format($amount, 2, ',', '.');
        }

        return "$currency " . number_format($amount, 2);
    }

    /**
     * Formata rótulo de tipo
     */
    private function formatTypeLabel(string $type): string
    {
        return match ($type) {
            'system' => 'Auditoria do Sistema',
            'caixa' => 'Transação de Caixa',
            'cancelamento' => 'Cancelamento',
            default => 'Log',
        };
    }

    /**
     * Formata rótulo de severidade
     */
    private function formatSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'danger' => 'Crítico',
            'warning' => 'Aviso',
            'success' => 'Sucesso',
            'info' => 'Informação',
            default => 'Outro',
        };
    }
}
