<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.60s>
    <button type="button" x-on:click="open = !open" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Notificações ({{ $unreadCount }} não lidas)">
        <x-icon name="bell" />
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-transition x-cloak class="absolute right-0 z-40 mt-2 w-80 rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Notificações</p>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs link">Marcar todas como lidas</button>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
            @forelse ($latest as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50', 'bg-brand-50/50 dark:bg-brand-500/5' => $notification->read_at === null])>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $notification->data['title'] ?? 'Notificação' }}</p>
                        <p class="text-xs text-slate-500">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </a>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">Você não tem notificações.</li>
            @endforelse
        </ul>
        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-200 px-4 py-2 text-center text-xs font-medium link dark:border-slate-700">Ver todas</a>
    </div>
</div>
