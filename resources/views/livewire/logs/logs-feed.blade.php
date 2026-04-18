<div class="w-full h-full flex flex-col bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
            <h3 class="font-semibold text-gray-900 dark:text-white">Feed em Tempo Real</h3>
            <span class="text-xs text-gray-500 dark:text-slate-400">({{ count($logs) }} logs)</span>
        </div>
        <div class="flex items-center gap-2">
            <button 
                wire:click="togglePause"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md transition-colors {{ $isPaused ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 hover:bg-red-200 dark:hover:bg-red-800' : 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800' }}"
            >
                @if($isPaused)
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" />
                    </svg>
                    Pausado
                @else
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" />
                    </svg>
                    Ao Vivo
                @endif
            </button>
            <button 
                wire:click="clear"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Limpar
            </button>
        </div>
    </div>

    <!-- Feed -->
    <div class="flex-1 overflow-y-auto space-y-2 p-4 divide-y divide-gray-200 dark:divide-slate-700">
        @forelse($logs as $log)
            <div class="py-3 px-3 hover:bg-gray-50 dark:hover:bg-slate-800 rounded-lg transition-colors group">
                <!-- Cabeçalho do log -->
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <!-- Avatar do usuário -->
                        @if($log->actor['avatar'])
                            <img 
                                src="{{ $log->actor['avatar'] }}" 
                                alt="{{ $log->actor['name'] }}"
                                class="w-5 h-5 rounded-full flex-shrink-0"
                            />
                        @else
                            <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-slate-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                {{ substr($log->actor['name'], 0, 1) }}
                            </div>
                        @endif
                        
                        <!-- Nome do usuário -->
                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $log->actor['name'] }}
                        </span>

                        <!-- Tipo de log -->
                        <span class="text-xs px-1.5 py-0.5 rounded-full font-medium flex-shrink-0
                            {{ $log->type === 'system' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : '' }}
                            {{ $log->type === 'caixa' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : '' }}
                            {{ $log->type === 'cancelamento' ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' : '' }}
                        ">
                            {{ ucfirst($log->type) }}
                        </span>
                    </div>

                    <!-- Timestamp -->
                    <span class="text-xs text-gray-500 dark:text-slate-400 ml-2 flex-shrink-0">
                        {{ $log->humanitarian_readable_time ?? $log->timestamp->format('H:i:s') }}
                    </span>
                </div>

                <!-- Ação -->
                <p class="text-sm text-gray-700 dark:text-slate-300 mb-2">
                    {{ $log->action }}
                </p>

                <!-- Detalhes (módulo, valor, etc) -->
                <div class="flex items-center gap-3 flex-wrap text-xs">
                    <!-- Módulo -->
                    @if($log->module)
                        <div class="flex items-center gap-1">
                            @if($log->module['icon'])
                                <span>{{ $log->module['icon'] }}</span>
                            @endif
                            <span class="text-gray-600 dark:text-slate-400">{{ $log->module['name'] }}</span>
                        </div>
                    @endif

                    <!-- Valor -->
                    @if($log->value)
                        <div class="font-mono text-green-700 dark:text-green-400 font-semibold">
                            {{ $log->value['formatted'] }} {{ $log->value['currency'] }}
                        </div>
                    @endif

                    <!-- Severidade -->
                    @php
                        $severityClasses = [
                            'info' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400',
                            'success' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400',
                            'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400',
                            'danger' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
                        ];
                        $severityLabels = [
                            'info' => 'Info',
                            'success' => 'OK',
                            'warning' => 'Aviso',
                            'danger' => 'Crítico',
                        ];
                    @endphp
                    <span class="px-2 py-0.5 rounded font-semibold {{ $severityClasses[$log->severity] ?? $severityClasses['info'] }}">
                        {{ $severityLabels[$log->severity] ?? 'Desconhecido' }}
                    </span>
                </div>

                <!-- Registro relacionado (se houver) -->
                @if($log->relatedRecord)
                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-slate-700">
                        <a 
                            href="{{ $log->relatedRecord['url'] }}"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold flex items-center gap-1"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            {{ $log->relatedRecord['name'] }}
                        </a>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-12">
                <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <p class="text-gray-500 dark:text-slate-400 text-sm">Aguardando novos logs...</p>
            </div>
        @endforelse
    </div>
</div>
