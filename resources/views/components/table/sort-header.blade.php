{{-- Cabeçalho de coluna ordenável em listagens Livewire. --}}
@props(['field', 'sortField' => '', 'sortDirection' => 'asc', 'align' => 'left'])

<th scope="col" @class(['text-right' => $align === 'right'])>
    <button type="button" wire:click="sortBy('{{ $field }}')" class="group inline-flex items-center gap-1 uppercase hover:text-slate-700 dark:hover:text-slate-200">
        {{ $slot }}
        <span @class(['text-brand-600' => $sortField === $field, 'text-slate-300 group-hover:text-slate-400' => $sortField !== $field])>
            @if ($sortField === $field && $sortDirection === 'desc') ↓ @elseif ($sortField === $field) ↑ @else ↕ @endif
        </span>
    </button>
</th>
