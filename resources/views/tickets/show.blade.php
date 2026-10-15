@php
    $status = $ticket->status;
    $statusEnum = \App\Enums\TicketStatus::class;

    /*
     * Botões de transição gerados a partir da máquina de estados do Enum
     * (TicketStatus::transitions()). Cancelar pede um motivo obrigatório;
     * resolver aceita uma observação opcional (fica no histórico de status).
     */
    $transitionButtons = collect($status->transitions())->map(fn ($target) => match ($target) {
        $statusEnum::InProgress => [
            'target' => $target,
            'label' => match ($status) {
                $statusEnum::WaitingCustomer => 'Retomar atendimento',
                $statusEnum::Resolved => 'Reabrir chamado',
                default => 'Iniciar atendimento',
            },
            'icon' => $status === $statusEnum::Resolved ? 'arrow-uturn-left' : 'play',
            'variant' => 'primary',
            'message' => $status === $statusEnum::Resolved
                ? 'O chamado voltará para "Em atendimento" e as datas de resolução serão apagadas.'
                : 'O chamado passará para "Em atendimento".',
            'reason' => false, 'reasonRequired' => false, 'reasonLabel' => 'Motivo',
        ],
        $statusEnum::WaitingCustomer => [
            'target' => $target, 'label' => 'Aguardar cliente', 'icon' => 'pause', 'variant' => 'warning',
            'message' => 'O chamado ficará "Aguardando cliente" até o retorno dele.',
            'reason' => false, 'reasonRequired' => false, 'reasonLabel' => 'Motivo',
        ],
        $statusEnum::Resolved => [
            'target' => $target, 'label' => 'Marcar como resolvido', 'icon' => 'check-circle', 'variant' => 'success',
            'message' => 'Registre, se quiser, um resumo da solução (fica no histórico de status).',
            'reason' => true, 'reasonRequired' => false, 'reasonLabel' => 'Resumo da solução (opcional)',
        ],
        $statusEnum::Closed => [
            'target' => $target, 'label' => 'Encerrar', 'icon' => 'check', 'variant' => 'secondary',
            'message' => 'Após encerrado, o chamado não aceita novas mensagens.',
            'reason' => false, 'reasonRequired' => false, 'reasonLabel' => 'Motivo',
        ],
        $statusEnum::Cancelled => [
            'target' => $target, 'label' => 'Cancelar chamado', 'icon' => 'no-symbol', 'variant' => 'danger',
            'message' => 'O cancelamento é definitivo. Informe o motivo.',
            'reason' => true, 'reasonRequired' => true, 'reasonLabel' => 'Motivo',
        ],
        default => [
            'target' => $target, 'label' => $target->label(), 'icon' => null, 'variant' => 'secondary',
            'message' => 'Confirmar a mudança de status?', 'reason' => false, 'reasonRequired' => false, 'reasonLabel' => 'Motivo',
        ],
    });
@endphp

<x-layouts::app :title="'Chamado '.$ticket->number">
    <x-ui.page-header :title="$ticket->number.' — '.$ticket->subject" :back="route('tickets.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                <x-ui.badge :color="$ticket->priority->color()">Prioridade {{ mb_strtolower($ticket->priority->label()) }}</x-ui.badge>
                <span>Aberto em {{ br_date($ticket->created_at, true) }} via {{ mb_strtolower($ticket->channel->label()) }}</span>
            </div>
        </x-slot:meta>

        @can('update', $ticket)
            <x-ui.button size="sm" icon="pencil" :href="route('tickets.edit', $ticket)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Conversa --}}
        <div class="space-y-4 lg:col-span-2">
            {{-- Descrição inicial (abertura do chamado) --}}
            <article class="card p-5">
                <header class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $ticket->creator?->name ?? 'Sistema' }} <span class="font-normal text-slate-500">abriu o chamado</span></span>
                    <span class="text-xs text-slate-500">{{ br_date($ticket->created_at, true) }}</span>
                </header>
                <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $ticket->description }}</p>
            </article>

            {{-- Mensagens em ordem cronológica (a relação já ordena da mais antiga para a mais nova) --}}
            @forelse ($ticket->messages as $message)
                <article @class([
                    'rounded-xl border p-5',
                    'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-500/10' => $message->is_internal,
                    'card' => ! $message->is_internal,
                ])>
                    <header class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $message->user?->name ?? 'Sistema' }}
                            @if ($message->is_internal)
                                <x-ui.badge color="yellow">Comentário interno</x-ui.badge>
                            @endif
                        </span>
                        <span class="text-xs text-slate-500">{{ br_date($message->created_at, true) }}</span>
                    </header>
                    <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $message->body }}</p>
                </article>
            @empty
                <p class="px-1 text-sm text-slate-500">Nenhuma resposta ainda.</p>
            @endforelse

            {{-- Formulário de resposta --}}
            @can('update', $ticket)
                @if ($status->isFinal())
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                        Este chamado está {{ mb_strtolower($status->label()) }} e não aceita novas mensagens.
                    </div>
                @else
                    <x-ui.card title="Responder">
                        <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" class="space-y-4"
                            x-data="{ submitting: false, internal: @js((bool) old('is_internal')) }" x-on:submit="submitting = true">
                            @csrf
                            <x-form.textarea name="body" label="Mensagem" rows="4" required maxlength="5000"
                                x-bind:class="internal ? 'bg-amber-50 dark:bg-amber-500/10' : ''" />
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <x-form.checkbox name="is_internal" label="Comentário interno (não visível ao cliente)" x-model="internal" />
                                <x-ui.button icon="paper-airplane" x-bind:disabled="submitting">
                                    <span x-text="internal ? 'Registrar comentário' : 'Enviar resposta'">Enviar resposta</span>
                                </x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>
                @endif
            @endcan
        </div>

        {{-- Painel lateral --}}
        <div class="space-y-6">
            @can('update', $ticket)
                @if ($transitionButtons->isNotEmpty())
                    <x-ui.card title="Ações">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($transitionButtons as $button)
                                <x-ui.confirm-action :action="route('tickets.status', $ticket)" method="PATCH"
                                    :title="$button['label']" :message="$button['message']" :confirm="$button['label']"
                                    :variant="$button['variant']" :icon="$button['icon']" size="sm"
                                    :reason="$button['reason']" :reason-label="$button['reasonLabel']" :reason-required="$button['reasonRequired']">
                                    <x-slot:fields><input type="hidden" name="status" value="{{ $button['target']->value }}"></x-slot:fields>
                                    {{ $button['label'] }}
                                </x-ui.confirm-action>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            @endcan

            <x-ui.card title="Detalhes">
                <dl class="grid gap-4">
                    <div class="dl-item">
                        <dt>Cliente</dt>
                        <dd>
                            @if ($ticket->customer)
                                <a href="{{ route('customers.show', $ticket->customer) }}" class="link">{{ $ticket->customer->display_name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="dl-item">
                        <dt>Contrato</dt>
                        <dd>
                            @if ($ticket->contract)
                                <a href="{{ route('contracts.show', $ticket->contract) }}" class="link">{{ $ticket->contract->number }} — {{ $ticket->contract->title }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="dl-item">
                        <dt>Ordem de serviço</dt>
                        <dd>
                            @if ($ticket->serviceOrder)
                                <a href="{{ route('service-orders.show', $ticket->serviceOrder) }}" class="link">{{ $ticket->serviceOrder->number }} — {{ $ticket->serviceOrder->title }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="dl-item"><dt>Categoria</dt><dd>{{ $ticket->category?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Canal</dt><dd>{{ $ticket->channel->label() }}</dd></div>
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $ticket->assignee?->name ?? 'Sem responsável' }}</dd></div>
                    <div class="dl-item"><dt>Abertura</dt><dd>{{ br_date($ticket->created_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Primeira resposta</dt><dd>{{ br_date($ticket->first_response_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Resolução</dt><dd>{{ br_date($ticket->resolved_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Encerramento</dt><dd>{{ br_date($ticket->closed_at, true) }}</dd></div>
                </dl>
            </x-ui.card>

            <x-attachments :model="$ticket" :attachments="$ticket->attachments" />
            <x-status-history :changes="$ticket->statusChanges" :enum="\App\Enums\TicketStatus::class" />

            @can('delete', $ticket)
                @if (in_array($status, [\App\Enums\TicketStatus::Open, \App\Enums\TicketStatus::Cancelled], true))
                    <div class="flex justify-end">
                        <x-ui.confirm-action :action="route('tickets.destroy', $ticket)" method="DELETE" title="Excluir chamado"
                            message="O chamado será removido das listagens. Só é possível excluir chamados abertos ou cancelados que ainda não tenham respostas ao cliente."
                            confirm="Excluir chamado" icon="trash" size="sm">Excluir chamado</x-ui.confirm-action>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-layouts::app>
