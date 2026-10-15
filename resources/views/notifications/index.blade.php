<x-layouts::app title="Notificações">
    <x-ui.page-header title="Notificações">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <x-ui.button variant="secondary" icon="check">Marcar todas como lidas</x-ui.button>
        </form>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($notifications as $notification)
                <li>
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex items-start gap-3 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50', 'bg-brand-50/40 dark:bg-brand-500/5' => $notification->read_at === null])>
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-500' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null])></span>
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $notification->data['title'] ?? 'Notificação' }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ br_date($notification->created_at, true) }}</p>
                        </div>
                    </a>
                </li>
            @empty
                <li><x-ui.empty-state icon="bell" title="Nenhuma notificação" description="Você será avisado aqui sobre atribuições, aprovações e contas vencidas." /></li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $notifications->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts::app>
