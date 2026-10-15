@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'help' => null, 'required' => false])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.str_replace(['.', '[', ']'], '-', $name));
    $hasError = $errors->has($errorKey);
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }} @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class(['form-control', 'form-control-invalid' => $hasError]) }}
    >@unless ($isLivewire){{ old($errorKey, $value) }}@endunless</textarea>

    @if ($hasError)
        <p id="{{ $id }}-error" class="form-error">{{ $errors->first($errorKey) }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
