{{--
    Histórico de alterações (vindo da auditoria) de um registro.
      <x-audit-history :logs="$catalogItem->auditLogs" />
--}}
@props(['logs', 'labels' => []])

@php
    $formatValue = function ($key, $value) {
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (str_ends_with((string) $key, '_cents') && is_numeric($value)) {
            return money((int) $value);
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return \Illuminate\Support\Str::limit((string) $value, 80);
    };

    $labelFor = fn ($field) => $labels[$field]
        ?? (\Illuminate\Support\Facades\Lang::has('validation.attributes.'.$field) ? __('validation.attributes.'.$field) : $field);
@endphp

<x-ui.card title="Histórico de alterações" :padding="false">
    <ol class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($logs as $log)
            <li class="px-5 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $log->eventLabel() }}</span>
                    <span class="text-xs text-slate-500">{{ br_date($log->created_at, true) }} · {{ $log->user?->name ?? 'Sistema' }}</span>
                </div>
                @if ($log->description)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $log->description }}</p>
                @endif
                @if ($log->event === 'updated' && $log->new_values)
                    <dl class="mt-2 space-y-1 text-xs">
                        @foreach ($log->new_values as $field => $newValue)
                            <div class="flex flex-wrap gap-1">
                                <dt class="font-medium text-slate-500">{{ $labelFor($field) }}:</dt>
                                <dd class="text-slate-600 dark:text-slate-300">
                                    <span class="line-through opacity-70">{{ $formatValue($field, $log->old_values[$field] ?? null) }}</span>
                                    → {{ $formatValue($field, $newValue) }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </li>
        @empty
            <li><x-ui.empty-state icon="clock" title="Sem alterações registradas" /></li>
        @endforelse
    </ol>
</x-ui.card>
