{{--
    Gráfico Chart.js alimentado por dados do servidor.
    O wire:key muda quando os dados mudam: o Livewire substitui o elemento e
    o Alpine recria o gráfico com os novos valores (filtros do dashboard).
      <x-chart :config="['type' => 'bar', 'money' => true, 'data' => [...]]" />
--}}
@props(['config', 'height' => 'h-72'])

<div wire:key="chart-{{ md5(json_encode($config)) }}" x-data="chart(@js($config))" {{ $attributes->merge(['class' => 'relative '.$height]) }}>
    <canvas x-ref="canvas" role="img" aria-label="Gráfico"></canvas>
</div>
