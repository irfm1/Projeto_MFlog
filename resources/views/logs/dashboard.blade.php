<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Logs - Maison Ferry</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 dark:bg-slate-950">
    <!-- Header -->
    <header class="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-600 to-pink-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Visualizador de Logs</h1>
                        <p class="text-sm text-gray-600 dark:text-slate-400">Maison Ferry - Auditoria em Tempo Real</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Status da conexão -->
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="text-sm font-medium text-gray-700 dark:text-slate-300">Conectado</span>
                    </div>

                    <!-- Menu de ações -->
                    <div class="flex items-center gap-2">
                        <button class="p-2 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors" title="Configurações">
                            <svg class="w-6 h-6 text-gray-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Coluna: Filtros + Feed -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Filtros avançados -->
                @livewire('logs.logs-filter')

                <!-- Feed em tempo real - DISABLED FOR PERFORMANCE -->
                {{-- 
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Atividade em Tempo Real</h2>
                    @livewire('logs.logs-feed')
                </div>
                --}}
            </div>

            <!-- Coluna: Tabela de logs -->
            <div class="lg:col-span-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Histórico de Logs</h2>
                @livewire('logs.logs-table')
            </div>
        </div>
    </main>

    <!-- Modal de detalhe de log -->
    @livewire('logs.log-detail')

    <!-- Scripts -->
    @livewireScripts
    
    <!-- Reverb Broadcasting -->
    <script>
        // Inicializa Echo na conexão de Reverb
        if (window.Echo) {
            // Escuta logs gerais
            window.Echo.channel('logs')
                .listen('LogDetected', (e) => {
                    Livewire.dispatch('log-detected', { data: e.data });
                });

            // Escuta por tipo específico
            window.Echo.channel('logs.system')
                .listen('SystemLogDetected', (e) => {
                    console.log('System log detected:', e);
                });

            window.Echo.channel('logs.caixa')
                .listen('CaixaLogDetected', (e) => {
                    console.log('Caixa log detected:', e);
                });

            window.Echo.channel('logs.cancelamento')
                .listen('CancelamentoLogDetected', (e) => {
                    console.log('Cancelamento log detected:', e);
                });
        }
    </script>
</body>
</html>
