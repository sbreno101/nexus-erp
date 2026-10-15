{{-- Campo de busca com debounce para listagens Livewire. --}}
@props(['placeholder' => 'Buscar...'])

<div class="relative w-full sm:max-w-xs">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
        <x-icon name="magnifying-glass" class="size-4" />
    </span>
    <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" class="form-control pl-9">
</div>
