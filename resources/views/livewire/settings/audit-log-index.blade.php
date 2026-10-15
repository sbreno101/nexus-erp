<div>
@php
    /*
     * Formata um valor da auditoria para exibição. Campos "*_cents" são
     * valores monetários em centavos e passam pelo helper money().
     */
    $formatValue = function (string $field, mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (str_ends_with($field, '_cents') && is_numeric($value)) {
            return money((int) $value);
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return \Illuminate\Support\Str::limit((string) $value, 200);
    };

    $labelFor = fn (string $field): string => \Illuminate\Support\Facades\Lang::has('validation.attributes.'.$field)
        ? __('validation.attributes.'.$field)
        : $field;

    $eventColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'restored' => 'yellow'];
@endphp
    <x-ui.page-header title="Auditoria" subtitle="Quem fez o quê e quando na empresa. Os registros não podem ser alterados." />

    <div class="card relative">
        <x-table.loading />

        <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-slate-800">
            <div class="sm:col-span-2">
                <x-table.search placeholder="Buscar na descrição..." />
            </div>
            <select wire:model.live="event" class="form-control" aria-label="Filtrar por evento">
                <option value="">Todos os eventos</option>
                @foreach ($eventOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="resourceType" class="form-control" aria-label="Filtrar por tipo de recurso">
                <option value="">Todos os recursos</option>
                @foreach ($resourceOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="userId" class="form-control lg:col-span-2" aria-label="Filtrar por usuário">
                <option value="">Todos os usuários</option>
                @foreach ($userOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2 sm:col-span-2">
                <label for="audit-date-from" class="shrink-0 text-sm text-slate-500">De</label>
                <input id="audit-date-from" type="date" wire:model.live="dateFrom" class="form-control">
            </div>
            <div class="flex items-center gap-2 sm:col-span-2">
                <label for="audit-date-to" class="shrink-0 text-sm text-slate-500">Até</label>
                <input id="audit-date-to" type="date" wire:model.live="dateTo" class="form-control">
            </div>
            @if ($search || $event || $resourceType || $userId || $dateFrom || $dateTo)
                <div class="flex items-center sm:col-span-2">
                    <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
                </div>
            @endif
        </div>

        @if ($logs->isEmpty())
            <x-ui.empty-state icon="shield-check" title="Nenhum registro encontrado" description="Ajuste os filtros para ver outras operações." />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><span class="sr-only">Detalhes</span></th>
                            <th>Data/hora</th>
                            <th>Usuário</th>
                            <th>Evento</th>
                            <th>Recurso</th>
                            <th>Descrição</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    {{-- Um <tbody> por registro: permite a linha de detalhes expansível com Alpine. --}}
                    @foreach ($logs as $log)
                        @php
                            $oldValues = $log->old_values ?? [];
                            $newValues = $log->new_values ?? [];
                            $fields = array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)]));
                        @endphp
                        <tbody wire:key="audit-{{ $log->id }}" x-data="{ open: false }" class="border-t border-slate-100 dark:border-slate-800">
                            <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50" x-on:click="open = !open">
                                <td class="w-8">
                                    <button type="button" class="rounded p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" x-bind:aria-expanded="open" aria-label="Ver detalhes">
                                        <x-icon name="chevron-down" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                                    </button>
                                </td>
                                <td class="whitespace-nowrap tabular-nums">{{ br_date($log->created_at, true) }}</td>
                                <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                                <td><x-ui.badge :color="$eventColors[$log->event] ?? 'purple'">{{ $log->eventLabel() }}</x-ui.badge></td>
                                <td class="whitespace-nowrap">
                                    {{ $log->resourceLabel() }}
                                    @if ($log->auditable_id)
                                        <span class="text-xs text-slate-400">#{{ $log->auditable_id }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs truncate" title="{{ $log->description }}">{{ $log->description ?? '—' }}</td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td colspan="7" class="bg-slate-50 px-5 py-4 dark:bg-slate-800/40">
                                    @if ($fields === [])
                                        <p class="text-sm text-slate-500">Nenhum valor registrado para esta operação.</p>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs uppercase text-slate-500">
                                                        <th class="py-1 pr-4 font-medium">Campo</th>
                                                        <th class="py-1 pr-4 font-medium">Antes</th>
                                                        <th class="py-1 font-medium">Depois</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    @foreach ($fields as $field)
                                                        <tr>
                                                            <td class="py-1.5 pr-4 align-top font-medium text-slate-700 dark:text-slate-300">
                                                                {{ $labelFor((string) $field) }}
                                                                @if ($labelFor((string) $field) !== (string) $field)
                                                                    <span class="block font-mono text-[11px] font-normal text-slate-400">{{ $field }}</span>
                                                                @endif
                                                            </td>
                                                            <td class="break-all py-1.5 pr-4 align-top text-slate-500">{{ array_key_exists($field, $oldValues) ? $formatValue((string) $field, $oldValues[$field]) : '—' }}</td>
                                                            <td class="break-all py-1.5 align-top text-slate-900 dark:text-white">{{ array_key_exists($field, $newValues) ? $formatValue((string) $field, $newValues[$field]) : '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                    @if ($log->user_agent)
                                        <p class="mt-3 text-xs text-slate-400">Navegador: {{ \Illuminate\Support\Str::limit($log->user_agent, 160) }}</p>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
