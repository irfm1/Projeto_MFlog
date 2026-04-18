<div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-6 space-y-6">
    <!-- Título -->
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Filtros de Logs</h3>
        <p class="text-sm text-gray-600 dark:text-slate-400">Configure os filtros para buscar os logs desejados</p>
    </div>

    <!-- Busca por Texto -->
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Buscar por Texto</label>
        <div class="relative">
            <input 
                type="text"
                wire:model.live="searchText"
                placeholder="Buscar por usuário, ação ou módulo..."
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:focus:ring-blue-600 transition-all"
            />
            <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400 dark:text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
    </div>

    <!-- Tipos de Logs -->
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">Tipo de Log</label>
        <div class="flex flex-wrap gap-2">
            <button 
                wire:click="toggleLogType('system')"
                class="px-4 py-2 rounded-lg font-medium text-sm transition-all {{ in_array('system', $types ?? []) ? 'bg-blue-500 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700' }}"
            >
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v4h8v-4zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                </svg>
                Sistema
            </button>
            <button 
                wire:click="toggleLogType('caixa')"
                class="px-4 py-2 rounded-lg font-medium text-sm transition-all {{ in_array('caixa', $types ?? []) ? 'bg-green-500 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700' }}"
            >
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8.16 5.314l5.25 5.25a2.5 2.5 0 11-3.536 3.536l-5.25-5.25a1 1 0 00-.707.293L1.293 13.5a2 2 0 002.828 2.828l3.633-3.633a3 3 0 004.243 0l7.07-7.07a2 2 0 00-2.828-2.828l-4.596 4.596.707-.707a1 1 0 00-1.414-1.414l-.707.707-1.414-1.414z" />
                </svg>
                Caixa
            </button>
            <button 
                wire:click="toggleLogType('cancelamento')"
                class="px-4 py-2 rounded-lg font-medium text-sm transition-all {{ in_array('cancelamento', $types ?? []) ? 'bg-red-500 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700' }}"
            >
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Cancelamento
            </button>
        </div>
    </div>

    <!-- Período Pré-definido -->
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">Período</label>
        <div class="grid grid-cols-3 gap-2">
            <button 
                wire:click="setPeriod('last-7')"
                class="px-3 py-2 rounded-lg font-medium text-sm transition-all bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            >
                Últimos 7 dias
            </button>
            <button 
                wire:click="setPeriod('last-30')"
                class="px-3 py-2 rounded-lg font-medium text-sm transition-all bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 font-semibold"
            >
                Últimos 30 dias
            </button>
            <button 
                wire:click="setPeriod('last-60')"
                class="px-3 py-2 rounded-lg font-medium text-sm transition-all bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            >
                Últimos 60 dias
            </button>
            <button 
                wire:click="setPeriod('last-90')"
                class="px-3 py-2 rounded-lg font-medium text-sm transition-all bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            >
                Últimos 90 dias
            </button>
            <button 
                wire:click="setPeriod('today')"
                class="px-3 py-2 rounded-lg font-medium text-sm transition-all bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            >
                Hoje
            </button>
        </div>
    </div>

    <!-- Período Personalizado -->
    <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">Período Personalizado</label>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-600 dark:text-slate-400 mb-1">De</label>
                <input 
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:focus:ring-blue-600 transition-all"
                />
            </div>
            <div>
                <label class="block text-xs text-gray-600 dark:text-slate-400 mb-1">Até</label>
                <input 
                    type="date"
                    wire:model.live="dateTo"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:focus:ring-blue-600 transition-all"
                />
            </div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex gap-3">
        <button 
            wire:click="resetFilters"
            class="flex-1 px-4 py-2.5 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-900 dark:text-white font-semibold rounded-lg transition-all"
        >
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Limpar Filtros
        </button>
    </div>
</div>