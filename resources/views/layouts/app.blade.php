{{--
    Layout administrativo autenticado.

    Usado de duas formas:
      - Views de Controllers:   <x-layouts::app title="Clientes"> ... </x-layouts::app>
      - Componentes Livewire de página inteira (config/livewire.php → component_layout).
--}}
@props(['title' => null])
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full">
    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        {{-- Sidebar (desktop fixa; mobile como gaveta) --}}
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" x-on:click="sidebarOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
            x-bind:class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5 dark:border-slate-800">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">N</span>
                    <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Nexus <span class="text-brand-600">ERP</span></span>
                </a>
                <button type="button" class="ml-auto rounded-md p-1 text-slate-400 lg:hidden" x-on:click="sidebarOpen = false" aria-label="Fechar menu">
                    <x-icon name="x-mark" />
                </button>
            </div>
            @include('layouts.partials.navigation')
        </aside>

        <div class="lg:pl-64">
            {{-- Cabeçalho --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90">
                <button type="button" class="rounded-md p-1.5 text-slate-500 lg:hidden" x-on:click="sidebarOpen = true" aria-label="Abrir menu">
                    <x-icon name="bars-3" class="size-6" />
                </button>

                @include('layouts.partials.company-switcher')

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    <button type="button" x-data x-on:click="$store.theme.toggle()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Alternar tema claro/escuro">
                        <span x-show="!$store.theme.dark"><x-icon name="moon" /></span>
                        <span x-show="$store.theme.dark" x-cloak><x-icon name="sun" /></span>
                    </button>

                    <livewire:notifications.bell />

                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800" aria-haspopup="true" x-bind:aria-expanded="open">
                            <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">{{ auth()->user()->initials() }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-medium leading-tight text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</span>
                                <span class="block text-xs leading-tight text-slate-500">{{ auth()->user()->isSuperAdmin() ? 'Superadministrador' : auth()->user()->job_title }}</span>
                            </span>
                            <x-icon name="chevron-down" class="hidden size-4 text-slate-400 sm:block" />
                        </button>

                        <div x-show="open" x-transition x-cloak class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                            <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                <x-icon name="user" class="size-4" /> Meu perfil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                    <x-icon name="logout" class="size-4" /> Sair
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @include('layouts.partials.toasts')
    @livewireScripts
</body>
</html>
