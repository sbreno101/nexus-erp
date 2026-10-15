{{--
    Lista de seleção. $options é um array "valor => rótulo".
      <x-form.select name="status" label="Status" :options="CustomerStatus::options()" :value="$customer->status?->value" />
--}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.str_replace(['.', '[', ']'], '-', $name));
    $hasError = $errors->has($errorKey);
    $selected = (string) old($errorKey, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }} @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class(['form-control', 'form-control-invalid' => $hasError]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected !== '' && $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if ($hasError)
        <p id="{{ $id }}-error" class="form-error">{{ $errors->first($errorKey) }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
