<div class="space-y-6">
    @if(!empty($financeRange['min_date']) && !empty($financeRange['max_date']))
        <div class="rounded-lg border {{ $financeRange['is_short_history'] ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300' }} px-4 py-3 text-sm">
            Base carregada no warehouse: {{ $financeRange['min_date'] }} até {{ $financeRange['max_date'] }} ({{ $financeRange['days_covered'] }} dias, {{ $financeRange['total'] }} registros de caixa).
            @if($financeRange['is_short_history'])
                Os rankings de 3 meses e all time podem ficar parecidos enquanto o histórico completo não for carregado.
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Cliente Em Foco</h2>
                    <p class="text-sm text-gray-600 dark:text-slate-400">Selecione um lead para preparar ação de divulgação.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 uppercase tracking-wide mb-1">Cliente</label>
                    <select
                        wire:model="selectedClienteId"
                        wire:change="selectCliente"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white px-3 py-2"
                    >
                        <option value="">Selecione um cliente</option>
                        @foreach($leadCandidates as $candidate)
                            <option value="{{ $candidate['cliente_id'] }}">{{ $candidate['nome'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($selectedClienteDetails)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50 dark:bg-slate-800/50 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $selectedClienteDetails['nome'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">Código cliente: {{ $selectedClienteDetails['codigo_cliente'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">Gasto 3 meses</p>
                            <p class="text-base font-semibold text-emerald-700 dark:text-emerald-300">R$ {{ number_format($selectedClienteDetails['gasto_3m'], 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2">
                            <p class="text-xs text-gray-500 dark:text-slate-400">Telefone</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $selectedClienteDetails['telefone'] !== '' ? $selectedClienteDetails['telefone'] : 'Não informado' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2">
                            <p class="text-xs text-gray-500 dark:text-slate-400">E-mail</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $selectedClienteDetails['email'] !== '' ? $selectedClienteDetails['email'] : 'Não informado' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2">
                            <p class="text-xs text-gray-500 dark:text-slate-400">Visitas 3 meses</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $selectedClienteDetails['visitas_3m'] }}</p>
                        </div>
                    </div>

                    @if($selectedClienteDetails['phone_missing'])
                        <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200 text-sm">
                            Alerta: cliente sem telefone. Lembrar operador de atualizar esse dado na próxima visita.
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Aniversariantes Do Mês</h2>
            @if($firebirdWarning)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200 text-sm">
                    {{ $firebirdWarning }}
                </div>
            @endif
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse($aniversariantes as $item)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 bg-slate-50 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $item['nome'] }}</p>
                            <span class="text-xs font-mono text-gray-600 dark:text-slate-300">Dia {{ $item['dia'] }}</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-slate-400">Tel: {{ $item['telefone'] !== '' ? $item['telefone'] : 'Não informado' }}</p>
                        @if($item['phone_missing'])
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Atualizar telefone na próxima visita.</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-slate-400">Sem aniversariantes encontrados para o mês atual.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Mais Assíduas (3 meses)</h2>
            <div class="space-y-2">
                @foreach($assiduas as $lead)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $lead['nome'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-slate-400">{{ $lead['visitas'] }} visitas • Tel: {{ $lead['telefone'] !== '' ? $lead['telefone'] : 'Não informado' }}</p>
                            @if($lead['phone_missing'])
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Atualizar telefone na próxima visita.</p>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">R$ {{ number_format($lead['total_gasto'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Maior Gasto (3 meses)</h2>
            <div class="space-y-2">
                @foreach($gastadoras as $lead)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $lead['nome'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-slate-400">{{ $lead['visitas'] }} visitas • Tel: {{ $lead['telefone'] !== '' ? $lead['telefone'] : 'Não informado' }}</p>
                            @if($lead['phone_missing'])
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Atualizar telefone na próxima visita.</p>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">R$ {{ number_format($lead['total_gasto'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Maior Assiduidade (All Time)</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mb-3">Considera todo o histórico disponível no warehouse.</p>
            <div class="space-y-2">
                @foreach($assiduasAllTime as $lead)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $lead['nome'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-slate-400">{{ $lead['visitas'] }} visitas • Tel: {{ $lead['telefone'] !== '' ? $lead['telefone'] : 'Não informado' }}</p>
                            @if($lead['phone_missing'])
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Atualizar telefone na próxima visita.</p>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">R$ {{ number_format($lead['total_gasto'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Maior Gasto (All Time High)</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mb-3">Considera todo o histórico disponível no warehouse.</p>
            <div class="space-y-2">
                @foreach($gastadorasAllTime as $lead)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $lead['nome'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-slate-400">{{ $lead['visitas'] }} visitas • Tel: {{ $lead['telefone'] !== '' ? $lead['telefone'] : 'Não informado' }}</p>
                            @if($lead['phone_missing'])
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Atualizar telefone na próxima visita.</p>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">R$ {{ number_format($lead['total_gasto'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
