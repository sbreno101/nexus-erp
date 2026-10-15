@php
    /*
     * Formata minutos como horas: 135 => "2h15".
     */
    $formatMinutes = fn (int $minutes): string => sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);

    $status = $serviceOrder->status;
    $statusEnum = \App\Enums\ServiceOrderStatus::class;

    /*
     * Botões de transição gerados a partir da máquina de estados do Enum
     * (ServiceOrderStatus::transitions()). Concluir e cancelar exigem um
     * texto (solução aplicada / motivo), validado também na Action.
     */
    $transitionButtons = collect($status->transitions())->map(fn ($target) => match ($target) {
        $statusEnum::InProgress => [
            'target' => $target,
            'label' => match ($status) {
                $statusEnum::OnHold => 'Retomar execução',
                $statusEnum::Completed => 'Reabrir OS',
                default => 'Iniciar execução',
            },
            'icon' => $status === $statusEnum::Completed ? 'arrow-uturn-left' : 'play',
            'variant' => 'primary',
            'message' => $status === $statusEnum::Completed
                ? 'A OS voltará para "Em execução" e a data de conclusão será apagada.'
                : 'A OS passará para "Em execução" e a data de início será registrada.',
            'reason' => false,
            'reasonLabel' => null,
        ],
        $statusEnum::OnHold => [
            'target' => $target, 'label' => 'Colocar em espera', 'icon' => 'pause', 'variant' => 'warning',
            'message' => 'A OS ficará "Aguardando" (ex.: aguardando peça ou retorno do cliente).',
            'reason' => false, 'reasonLabel' => null,
        ],
        $statusEnum::Completed => [
            'target' => $target, 'label' => 'Concluir', 'icon' => 'check-circle', 'variant' => 'success',
            'message' => 'Descreva a solução aplicada. Ela ficará registrada na OS.',
            'reason' => true, 'reasonLabel' => 'Solução aplicada',
        ],
        $statusEnum::Cancelled => [
            'target' => $target, 'label' => 'Cancelar OS', 'icon' => 'no-symbol', 'variant' => 'danger',
            'message' => 'O cancelamento é definitivo. Informe o motivo.',
            'reason' => true, 'reasonLabel' => 'Motivo',
        ],
        default => [
            'target' => $target, 'label' => $target->label(), 'icon' => null, 'variant' => 'secondary',
            'message' => 'Confirmar a mudança de status?', 'reason' => false, 'reasonLabel' => null,
        ],
    });
@endphp

<x-layouts::app :title="'OS '.$serviceOrder->number">
    <x-ui.page-header :title="'OS '.$serviceOrder->number.' — '.$serviceOrder->title" :back="route('service-orders.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                <x-ui.badge :color="$serviceOrder->priority->color()">Prioridade {{ mb_strtolower($serviceOrder->priority->label()) }}</x-ui.badge>
                <span @class(['font-semibold text-red-600 dark:text-red-400' => $serviceOrder->isOverdue()])>
                    Prazo: {{ $serviceOrder->due_date ? br_date($serviceOrder->due_date) : 'não definido' }}
                    @if ($serviceOrder->isOverdue()) (atrasada) @endif
                </span>
            </div>
        </x-slot:meta>

        @can('update', $serviceOrder)
            @foreach ($transitionButtons as $button)
                <x-ui.confirm-action :action="route('service-orders.status', $serviceOrder)" method="PATCH"
                    :title="$button['label']" :message="$button['message']" :confirm="$button['label']"
                    :variant="$button['variant']" :icon="$button['icon']" size="sm"
                    :reason="$button['reason']" :reason-label="$button['reasonLabel'] ?? 'Motivo'">
                    <x-slot:fields><input type="hidden" name="status" value="{{ $button['target']->value }}"></x-slot:fields>
                    {{ $button['label'] }}
                </x-ui.confirm-action>
            @endforeach
        @endcan
        @can('create', \App\Models\Ticket::class)
            <x-ui.button variant="secondary" size="sm" icon="lifebuoy" :href="route('tickets.create', ['customer_id' => $serviceOrder->customer_id, 'service_order_id' => $serviceOrder->id])">Abrir chamado</x-ui.button>
        @endcan
        @can('update', $serviceOrder)
            <x-ui.button size="sm" icon="pencil" :href="route('service-orders.edit', $serviceOrder)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Dados da OS">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="dl-item">
                        <dt>Cliente</dt>
                        <dd>
                            @if ($serviceOrder->customer)
                                <a href="{{ route('customers.show', $serviceOrder->customer) }}" class="link">{{ $serviceOrder->customer->display_name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="dl-item">
                        <dt>Contrato</dt>
                        <dd>
                            @if ($serviceOrder->contract)
                                <a href="{{ route('contracts.show', $serviceOrder->contract) }}" class="link">{{ $serviceOrder->contract->number }} — {{ $serviceOrder->contract->title }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="dl-item">
                        <dt>Serviço</dt>
                        <dd>{{ $serviceOrder->service ? $serviceOrder->service->code.' — '.$serviceOrder->service->name : '—' }}</dd>
                    </div>
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $serviceOrder->assignee?->name ?? 'Sem responsável' }}</dd></div>
                    <div class="dl-item"><dt>Aberta em</dt><dd>{{ br_date($serviceOrder->created_at, true) }}{{ $serviceOrder->creator ? ' por '.$serviceOrder->creator->name : '' }}</dd></div>
                    <div class="dl-item"><dt>Prazo</dt><dd>{{ br_date($serviceOrder->due_date) }}</dd></div>
                    <div class="dl-item"><dt>Início da execução</dt><dd>{{ br_date($serviceOrder->started_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Conclusão</dt><dd>{{ br_date($serviceOrder->completed_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Tempo total registrado</dt><dd class="tabular-nums">{{ $formatMinutes($totalMinutes) }}</dd></div>
                    <div class="dl-item sm:col-span-2"><dt>Descrição</dt><dd class="whitespace-pre-line">{{ $serviceOrder->description }}</dd></div>
                </dl>
            </x-ui.card>

            @if ($status === \App\Enums\ServiceOrderStatus::Completed && $serviceOrder->resolution)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-800 dark:bg-emerald-500/10">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                        <x-icon name="check-circle" class="size-5" /> Solução aplicada
                    </h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-emerald-900 dark:text-emerald-200">{{ $serviceOrder->resolution }}</p>
                </div>
            @endif

            <x-ui.card title="Atividades" :description="'Tempo total: '.$formatMinutes($totalMinutes)">
                @if ($serviceOrder->status->acceptsActivities() && auth()->user()->can('update', $serviceOrder))
                    <form method="POST" action="{{ route('service-orders.activities.store', $serviceOrder) }}" class="mb-6 grid gap-3 sm:grid-cols-6"
                        x-data="{ submitting: false }" x-on:submit="submitting = true">
                        @csrf
                        <x-form.textarea name="description" id="activity-description" label="O que foi feito?" class="sm:col-span-6" rows="2" required maxlength="2000" />
                        <x-form.input name="minutes_spent" type="number" label="Tempo gasto (minutos)" class="sm:col-span-2" min="1" max="1440" step="1" />
                        <div class="flex items-end justify-end sm:col-span-4">
                            <x-ui.button icon="plus" x-bind:disabled="submitting">Registrar atividade</x-ui.button>
                        </div>
                    </form>
                @endif

                <ol class="relative space-y-5 border-l border-slate-200 pl-6 dark:border-slate-700">
                    @forelse ($serviceOrder->activities as $activity)
                        <li>
                            <span class="absolute -left-1.5 mt-1.5 size-3 rounded-full border-2 border-white bg-brand-500 dark:border-slate-900"></span>
                            <p class="whitespace-pre-line text-sm text-slate-800 dark:text-slate-200">{{ $activity->description }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ br_date($activity->created_at, true) }} · {{ $activity->user?->name ?? 'Sistema' }}
                                @if ($activity->minutes_spent) · {{ $formatMinutes($activity->minutes_spent) }} @endif
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Nenhuma atividade registrada ainda.</li>
                    @endforelse
                </ol>
            </x-ui.card>

            <x-ui.card title="Chamados vinculados" :padding="false">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($serviceOrder->tickets as $ticket)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('tickets.show', $ticket) }}" class="link block truncate text-sm">{{ $ticket->number }} — {{ $ticket->subject }}</a>
                                <p class="text-xs text-slate-500">Aberto em {{ br_date($ticket->created_at, true) }}</p>
                            </div>
                            <x-ui.badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</x-ui.badge>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="lifebuoy" title="Nenhum chamado vinculado" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-attachments :model="$serviceOrder" :attachments="$serviceOrder->attachments" />
            <x-status-history :changes="$serviceOrder->statusChanges" :enum="\App\Enums\ServiceOrderStatus::class" />

            @can('delete', $serviceOrder)
                @if (in_array($status, [\App\Enums\ServiceOrderStatus::Open, \App\Enums\ServiceOrderStatus::Cancelled], true))
                    <div class="flex justify-end">
                        <x-ui.confirm-action :action="route('service-orders.destroy', $serviceOrder)" method="DELETE" title="Excluir OS"
                            message="A ordem de serviço será removida das listagens. Somente OS abertas ou canceladas podem ser excluídas."
                            confirm="Excluir OS" icon="trash" size="sm">Excluir OS</x-ui.confirm-action>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-layouts::app>
