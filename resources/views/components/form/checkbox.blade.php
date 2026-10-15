@props(['name', 'label', 'checked' => false, 'value' => '1', 'help' => null])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.str_replace(['.', '[', ']'], '-', $name).'-'.$value);
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $isChecked = old($errorKey) !== null ? (bool) old($errorKey) : (bool) $checked;
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-start gap-2">
        @unless ($isLivewire || str_ends_with($name, '[]'))
            <input type="hidden" name="{{ $name }}" value="0">
        @endunless
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @if (! $isLivewire && $isChecked) checked @endif
            {{ $attributes->except('class')->merge(['class' => 'mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800']) }}
        >
        <span class="text-sm text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if ($help)
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $help }}</span>
            @endif
        </span>
    </label>
    @error($errorKey)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
