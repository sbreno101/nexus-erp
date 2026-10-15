{{--
    Campo de texto com rótulo e mensagem de validação.
      <x-form.input name="email" label="E-mail" type="email" :value="$customer->email" required />
    Dentro de componentes Livewire, use o atributo wire:model normalmente.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])

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

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @unless ($isLivewire || $type === 'password') value="{{ old($errorKey, $value) }}" @endunless
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class(['form-control', 'form-control-invalid' => $hasError]) }}
    >

    @if ($hasError)
        <p id="{{ $id }}-error" class="form-error">{{ $errors->first($errorKey) }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
