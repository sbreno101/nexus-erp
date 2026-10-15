{{-- Layout das telas públicas de autenticação (login, recuperação de senha...). --}}
@props(['title' => null])
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full">
    <div class="flex min-h-full">
        <div class="relative hidden w-1/2 overflow-hidden bg-brand-700 lg:block">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-600 via-brand-700 to-slate-900"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <div class="flex items-center gap-2">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold">N</span>
                    <span class="text-xl font-bold tracking-tight">Nexus ERP</span>
                </div>
                <div class="max-w-md">
                    <h2 class="text-3xl font-bold leading-tight">Toda a operação da sua empresa de serviços em um só lugar.</h2>
                    <p class="mt-4 text-brand-100">CRM, propostas, contratos, ordens de serviço, atendimento e financeiro integrados — com dados isolados por empresa e permissões granulares.</p>
                    <ul class="mt-8 space-y-3 text-sm text-brand-100">
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="size-5 text-emerald-300" /> Cobranças recorrentes automáticas</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="size-5 text-emerald-300" /> Fluxo de caixa previsto x realizado</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="size-5 text-emerald-300" /> Auditoria completa das operações</li>
                    </ul>
                </div>
                <p class="text-xs text-brand-200">&copy; {{ now()->year }} Nexus ERP</p>
            </div>
        </div>

        <div class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-16">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-2 lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 font-bold text-white">N</span>
                    <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Nexus ERP</span>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>

    @include('layouts.partials.toasts')
    @livewireScripts
</body>
</html>
