{{-- Seletor da empresa ativa (todas as consultas passam a filtrar por ela). --}}
@php
    $activeCompany = app(\App\Support\Tenancy\CurrentCompany::class)->get();
    $companies = auth()->user()->accessibleCompanies();
@endphp

@if ($activeCompany)
    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
        <button type="button" x-on:click="open = !open" class="flex max-w-56 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-left text-sm shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700" aria-haspopup="true" x-bind:aria-expanded="open">
            <x-icon name="building" class="size-4 text-slate-400" />
            <span class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $activeCompany->display_name }}</span>
            @if ($companies->count() > 1)
                <x-icon name="chevron-up-down" class="size-4 text-slate-400" />
            @endif
        </button>

        @if ($companies->count() > 1)
            <div x-show="open" x-transition x-cloak class="absolute left-0 z-40 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Trocar empresa</p>
                @foreach ($companies as $company)
                    <form method="POST" action="{{ route('company.switch') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <button type="submit" @class([
                            'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700',
                            'font-semibold text-brand-700 dark:text-brand-300' => $company->is($activeCompany),
                            'text-slate-700 dark:text-slate-200' => ! $company->is($activeCompany),
                        ])>
                            <span class="truncate">{{ $company->display_name }}</span>
                            @if ($company->is($activeCompany))
                                <x-icon name="check" class="size-4" />
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endif
