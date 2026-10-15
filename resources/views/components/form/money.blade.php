{{--
    Campo monetário com máscara brasileira ("1.234,56").
    $value deve estar em CENTAVOS; o servidor converte de volta com App\Support\Money::parse().
      <x-form.money name="amount" label="Valor" :value="$receivable->amount_cents" required />
--}}
@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.str_replace(['.', '[', ']'], '-', $name));
    $hasError = $errors->has($errorKey) || $errors->has($errorKey.'_cents');
    $initial = old($errorKey, $value === null || $value === '' ? '' : \App\Support\Money::toInput((int) $value));
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }} @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative" x-data="moneyInput(@js($initial))">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">R$</span>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            x-model="display"
            x-on:input="onInput($event)"
            placeholder="0,00"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id'])->class(['form-control pl-9 text-right tabular-nums', 'form-control-invalid' => $hasError]) }}
        >
    </div>

    @if ($hasError)
        <p class="form-error">{{ $errors->first($errorKey) ?: $errors->first($errorKey.'_cents') }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
