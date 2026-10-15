{{--
    Histórico de mudanças de status.
      <x-status-history :changes="$proposal->statusChanges" :enum="\App\Enums\ProposalStatus::class" />
--}}
@props(['changes', 'enum'])

<x-ui.card title="Histórico de status" :padding="false">
    <ol class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($changes as $change)
            <li class="px-5 py-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    @if ($change->from_status && $change->from_status !== $change->to_status)
                        <span class="text-slate-500">{{ $change->labelFor($change->from_status, $enum) }}</span>
                        <span class="text-slate-400">→</span>
                    @endif
                    <span class="font-medium text-slate-900 dark:text-white">{{ $change->labelFor($change->to_status, $enum) }}</span>
                </div>
                @if ($change->reason)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $change->reason }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ br_date($change->created_at, true) }} · {{ $change->user?->name ?? 'Sistema' }}</p>
            </li>
        @empty
            <li><x-ui.empty-state icon="clock" title="Sem histórico" /></li>
        @endforelse
    </ol>
</x-ui.card>
