<div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
    <!-- Header da Tabela -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Listagem de Logs</h3>
            <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Exibindo {{ count($logs) }} de {{ $totalLogs }} registros</p>
        </div>
        @if($totalLogs > 0)
            <div class="flex items-center gap-2 px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0zM8 9a1 1 0 100-2 1 1 0 000 2zm5-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                </svg>
                {{ $totalLogs }} logs
            </div>
        @endif
    </div>

    <!-- Tabela -->
    <div class="overflow-x-auto">
        @forelse($logs as $log)
            <div 
                wire:click="selectLog('{{ $log->type }}', {{ $log->id }})"
                class="border-b border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer transition-colors px-6 py-4 group"
            >
                <div class="flex items-start justify-between gap-4">
                    <!-- Avatar e Info Primária -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                            <!-- Avatar -->
                            <div class="flex-shrink-0">
                                @if($log->actor['avatar'])
                                    <img 
                                        src="{{ $log->actor['avatar'] }}" 
                                        alt="{{ $log->actor['name'] }}"
                                        class="w-10 h-10 rounded-full object-cover"
                                    />
                                @else
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
                                        {{ substr($log->actor['name'], 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <!-- Nome e Info -->
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 dark:text-white truncate">
                                    {{ $log->actor['name'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-slate-400 truncate">
                                    {{ $log->action }}
                                </p>
                            </div>
                        </div>

                        <!-- Detalhes da Ação -->
                        <div class="flex items-center gap-2 flex-wrap mt-2 text-sm">
                            <!-- Tipo de Log -->
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $log->type === 'system' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : ($log->type === 'caixa' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200') }}">
                                {{ ucfirst($log->type) }}
                            </span>

                            <!-- Módulo -->
                            @if($log->module)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300">
                                    @if($log->module['icon'])
                                        <span class="text-sm">{{ $log->module['icon'] }}</span>
                                    @endif
                                    {{ $log->module['name'] }}
                                </span>
                            @endif

                            <!-- Valor (se houver) -->
                            @if($log->value)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $log->value['formatted'] > 0 ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200' }}">
                                    {{ $log->value['formatted'] }}
                                </span>
                            @endif

                            <!-- Severidade -->
                            @php
                                $severityClasses = [
                                    'info' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                                    'success' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
                                    'warning' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                                    'danger' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                                ];
                                $severityLabels = [
                                    'info' => 'Info',
                                    'success' => 'OK',
                                    'warning' => 'Aviso',
                                    'danger' => 'Crítico',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $severityClasses[$log->severity] ?? 'bg-gray-100 dark:bg-slate-800' }}">
                                {{ $severityLabels[$log->severity] ?? 'Desconhecido' }}
                            </span>
                        </div>
                    </div>

                    <!-- Timestamp e Indicador -->
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm text-gray-600 dark:text-slate-400 whitespace-nowrap">
                            {{ $log->timestamp->format('H:i:s') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-slate-500 whitespace-nowrap mt-1">
                            {{ $log->timestamp->format('d/m/Y') }}
                        </p>
                        <div class="flex justify-end mt-2">
                            <svg class="w-5 h-5 text-gray-400 dark:text-slate-500 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <!-- Estado Vazio -->
            <div class="px-6 py-12 text-center">
                <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">Nenhum log encontrado</p>
                <p class="text-gray-400 dark:text-slate-500 text-xs">Tente alterar os filtros ou o período de busca</p>
            </div>
        @endforelse
    </div>

    <!-- Paginação -->
    @if($totalPages > 1)
        <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-slate-400">
                Página <span class="font-semibold">{{ $page }}</span> de <span class="font-semibold">{{ $totalPages }}</span>
            </div>

            <div class="flex gap-2">
                <button 
                    wire:click="previousPage"
                    wire:disabled="$page == 1"
                    class="px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Números de Página -->
                @php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    if ($end - $start < 4) {
                        if ($start == 1) $end = min($totalPages, $start + 4);
                        else $start = max(1, $end - 4);
                    }
                @endphp

                @if($start > 1)
                    <button 
                        wire:click="goToPage(1)"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-all"
                    >
                        1
                    </button>
                    @if($start > 2)
                        <span class="px-2 py-2 text-gray-500 dark:text-slate-400">...</span>
                    @endif
                @endif

                @for($i = $start; $i <= $end; $i++)
                    <button 
                        wire:click="goToPage({{ $i }})"
                        class="px-3 py-2 rounded-lg border transition-all {{ $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800' }}"
                    >
                        {{ $i }}
                    </button>
                @endfor

                @if($end < $totalPages)
                    @if($end < $totalPages - 1)
                        <span class="px-2 py-2 text-gray-500 dark:text-slate-400">...</span>
                    @endif
                    <button 
                        wire:click="goToPage({{ $totalPages }})"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-all"
                    >
                        {{ $totalPages }}
                    </button>
                @endif

                <button 
                    wire:click="nextPage"
                    wire:disabled="$page == $totalPages"
                    class="px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>

