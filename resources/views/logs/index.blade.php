@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Gestão de Logs
            </h1>
            <p class="mt-2 text-gray-600 dark:text-slate-400">
                Visualize, filtre e analise todos os logs do sistema
            </p>
        </div>

        <!-- Layout Grid: Sidebar + Main -->
        <div
         class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Filtros Sidebar -->
            <aside class="lg:col-span-1">
                <livewire:logs.logs-filter />
            </aside>

            <!-- Content Area: Tabela + Modal -->
            <main class="lg:col-span-2 space-y-6">
                <livewire:logs.logs-table />
            </main>
        </div>

        <!-- Modal Global -->
        <livewire:logs.log-detail />
    </div>
</div>
@endsection
