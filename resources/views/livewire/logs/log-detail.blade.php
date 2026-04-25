<div>
    @if($showModal && $log)
           <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-3 sm:p-4" 
             wire:click="close"
             @click.self="$wire.close()">
              <div class="bg-white dark:bg-slate-900 rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] sm:max-h-[88vh] flex flex-col"
                 wire:click.stop>
            <!-- Header -->
              <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-900 z-10">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Detalhes do Log</h2>
                <button 
                    wire:click="close"
                    class="text-gray-500 hover:text-gray-700 dark:hover:text-slate-300 transition-colors"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-4 sm:p-6 space-y-6 overflow-y-auto">
                <!-- ID e Tipo -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">ID</label>
                        <p class="text-gray-900 dark:text-slate-100 font-mono">{{ $log->id }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">Tipo</label>
                        <p class="inline-flex items-center gap-2">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                {{ ucfirst($log->type) }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Ator (Usuário) -->
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-3">Usuário</label>
                    <div class="flex items-center gap-3">
                        @if($log->actor['avatar'])
                            <img 
                                src="{{ $log->actor['avatar'] }}" 
                                alt="{{ $log->actor['name'] }}"
                                class="w-8 h-8 rounded-full"
                            />
                        @else
                            <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-slate-600 flex items-center justify-center text-sm font-bold text-white">
                                {{ substr($log->actor['name'], 0, 1) }}
                            </div>
                        @endif
                        <span class="text-gray-900 dark:text-slate-100">{{ $log->actor['name'] }}</span>
                    </div>
                </div>

                <!-- Ação -->
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">Ação</label>
                    <p class="text-gray-900 dark:text-slate-100">{{ $log->action }}</p>
                </div>

                <!-- Módulo -->
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-2">Módulo</label>
                    <div class="flex items-center gap-2">
                        @if($log->module['icon'])
                            <span class="text-2xl">{{ $log->module['icon'] }}</span>
                        @endif
                        <span class="text-gray-900 dark:text-slate-100">{{ $log->module['name'] }}</span>
                    </div>
                </div>

                <!-- Tipo de Operação -->
                @if($log->operationType)
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">Tipo de Operação</label>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                            {{ $log->operationType }}
                        </span>
                    </div>
                @endif

                <!-- Valor -->
                @if($log->value)
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">Valor</label>
                        <p class="text-lg font-semibold text-green-700 dark:text-green-400 font-mono">
                            {{ $log->value['formatted'] }} {{ $log->value['currency'] }}
                        </p>
                    </div>
                @endif

                <!-- Registro Relacionado -->
                @if($log->relatedRecord)
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-2">Registro Relacionado</label>
                        <a 
                            href="{{ $log->relatedRecord['url'] }}"
                            class="inline-flex items-center gap-2 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            {{ $log->relatedRecord['name'] }}
                        </a>
                    </div>
                @endif

                <!-- Dados da Venda -->
                @if(!empty($log->sale))
                    @php
                        $itensVenda = $log->sale['itens'] ?? [];
                        $quantidadeItens = count($itensVenda);
                        $totalVenda = collect($itensVenda)->sum('valor_total');
                    @endphp
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm dark:border-emerald-900/70 dark:from-emerald-950/30 dark:to-slate-900">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Venda vinculada</p>
                                    <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $log->sale['cliente'] }}</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Pedido #{{ $log->sale['numero'] }}</p>
                                </div>
                                <div class="rounded-xl bg-white/90 px-3 py-2 text-right ring-1 ring-emerald-100 dark:bg-slate-900/70 dark:ring-emerald-900/80">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Total da venda</p>
                                    <p class="text-base font-semibold font-mono text-emerald-700 dark:text-emerald-300">R$ {{ number_format($totalVenda, 2, ',', '.') }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-white/90 px-3 py-2 ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-slate-700">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Cliente</p>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $log->sale['cliente'] }}</p>
                                </div>
                                <div class="rounded-xl bg-white/90 px-3 py-2 ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-slate-700">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Itens</p>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $quantidadeItens }} {{ $quantidadeItens === 1 ? 'item' : 'itens' }}</p>
                                </div>
                                <div class="rounded-xl bg-white/90 px-3 py-2 ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-slate-700">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Número</p>
                                    <p class="text-sm font-semibold font-mono text-slate-900 dark:text-slate-100">#{{ $log->sale['numero'] }}</p>
                                </div>
                            </div>

                            @if(!empty($itensVenda))
                                <div class="mt-4 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Itens da venda</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $quantidadeItens }} {{ $quantidadeItens === 1 ? 'lançamento' : 'lançamentos' }}</p>
                                    </div>

                                    @foreach($itensVenda as $item)
                                        <div class="rounded-xl border border-slate-200 bg-white/95 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 break-words">{{ $item['nome'] }}</p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ ucfirst(strtolower($item['tipo'])) }} · Qtd {{ number_format($item['quantidade'], 2, ',', '.') }}</p>
                                                    @if(!empty($item['profissional']))
                                                        <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">Profissional: {{ $item['profissional'] }}</p>
                                                    @endif
                                                </div>
                                                <p class="text-sm font-semibold font-mono text-slate-900 dark:text-slate-100 whitespace-nowrap">R$ {{ number_format($item['valor_total'], 2, ',', '.') }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white/80 px-3 py-2 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400">
                                    Itens da venda não encontrados no histórico sincronizado.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Tags -->
                @if($log->tags && count($log->tags) > 0)
                    <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-2">Tags</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($log->tags as $tag)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 rounded-full text-xs font-medium">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Severidade -->
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-2">Severidade</label>
                    @php
                        $severityClasses = [
                            'info' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                            'success' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
                            'warning' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                            'danger' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                        ];
                        $severityLabels = [
                            'info' => 'Informação',
                            'success' => 'Sucesso',
                            'warning' => 'Aviso',
                            'danger' => 'Crítico',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold {{ $severityClasses[$log->severity] ?? $severityClasses['info'] }}">
                        {{ $severityLabels[$log->severity] ?? 'Desconhecido' }}
                    </span>
                </div>

                <!-- Data/Hora -->
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-1">Data/Hora</label>
                    <p class="text-gray-900 dark:text-slate-100 font-mono">
                        {{ $log->timestamp->format('d/m/Y H:i:s') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                        {{ $log->humanReadableTime }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700 px-4 sm:px-6 py-4 flex justify-end gap-3 shrink-0">
                <button 
                    wire:click="close"
                    class="px-4 py-2 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-900 dark:text-white font-semibold rounded-lg transition-colors"
                >
                    Fechar
                </button>
            </div>
        </div>
        </div>
    @endif
</div>
