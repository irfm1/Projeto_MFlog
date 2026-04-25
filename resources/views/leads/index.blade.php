@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard De Leads</h1>
            <p class="mt-2 text-gray-600 dark:text-slate-400">
                Insights de marketing para campanhas de divulgação e promoções.
            </p>
        </div>

        <livewire:leads.leads-dashboard />
    </div>
</div>
@endsection
