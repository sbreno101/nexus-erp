{{--
    Lista compacta de registros relacionados (usada nas abas da tela de cliente).
    Parâmetros: $items, $route (nome da rota show), $label (closure), $meta (closure),
    $badge (closure que retorna um Enum com label()/color(), ou null), $customBadge (closure [cor, rótulo]).
--}}
<ul class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse ($items as $item)
        <li class="flex items-center justify-between gap-3 px-5 py-3">
            <div class="min-w-0">
                <a href="{{ route($route, $item) }}" class="link block truncate text-sm">{{ $label($item) }}</a>
                <p class="text-xs text-slate-500">{{ $meta($item) }}</p>
            </div>
            @if (! empty($badge))
                @php($status = $badge($item))
                <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
            @elseif (! empty($customBadge))
                @php([$color, $text] = $customBadge($item))
                <x-ui.badge :color="$color">{{ $text }}</x-ui.badge>
            @endif
        </li>
    @empty
        <li><x-ui.empty-state title="Nada por aqui ainda" /></li>
    @endforelse
</ul>
