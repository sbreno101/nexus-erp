<div>
    <x-ui.page-header title="Configurações do CRM" subtitle="Etapas do funil de vendas e origens de leads." :back="route('opportunities.pipeline')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Etapas do funil" description="A ordem define as colunas do Kanban. Etapas de ganho/perda encerram a oportunidade." class="lg:col-span-2" :padding="false">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($stages as $id => $stage)
                    <div wire:key="stage-row-{{ $id }}" class="grid items-end gap-3 px-5 py-3 md:grid-cols-12">
                        <div class="flex gap-1 md:col-span-1">
                            <button type="button" wire:click="moveStage({{ $id }}, 'up')" class="rounded p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Subir">↑</button>
                            <button type="button" wire:click="moveStage({{ $id }}, 'down')" class="rounded p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Descer">↓</button>
                        </div>
                        <div class="md:col-span-4">
                            <label class="form-label">Nome</label>
                            <input type="text" wire:model="stages.{{ $id }}.name" class="form-control">
                            @error("stages.$id.name") <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Prob. %</label>
                            <input type="number" min="0" max="100" wire:model="stages.{{ $id }}.probability" class="form-control">
                        </div>
                        <div class="md:col-span-1">
                            <label class="form-label">Cor</label>
                            <input type="color" wire:model="stages.{{ $id }}.color" class="h-9 w-full rounded border border-slate-300 dark:border-slate-700">
                        </div>
                        <div class="space-y-1 text-xs md:col-span-2">
                            <label class="flex items-center gap-1"><input type="checkbox" wire:model="stages.{{ $id }}.is_won" class="rounded text-emerald-600"> Ganho</label>
                            <label class="flex items-center gap-1"><input type="checkbox" wire:model="stages.{{ $id }}.is_lost" class="rounded text-red-600"> Perda</label>
                        </div>
                        <div class="flex items-center justify-end gap-1 md:col-span-2">
                            <span class="mr-1 text-xs text-slate-500">{{ $stageCounts[$id] ?? 0 }} opp.</span>
                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="saveStage({{ $id }})">Salvar</x-ui.button>
                            <button type="button" wire:click="deleteStage({{ $id }})" wire:confirm="Excluir esta etapa?" class="rounded p-1.5 text-slate-400 hover:text-red-600" aria-label="Excluir etapa"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    </div>
                @endforeach
            </div>

            <form wire:submit="addStage" class="grid items-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:grid-cols-12 dark:border-slate-800 dark:bg-slate-900/50">
                <div class="md:col-span-5">
                    <label class="form-label">Nova etapa</label>
                    <input type="text" wire:model="stageName" class="form-control" placeholder="Ex.: Apresentação">
                    @error('stageName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Prob. %</label>
                    <input type="number" min="0" max="100" wire:model="stageProbability" class="form-control">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Cor</label>
                    <input type="color" wire:model="stageColor" class="h-9 w-full rounded border border-slate-300 dark:border-slate-700">
                </div>
                <div class="md:col-span-3">
                    <x-ui.button icon="plus" class="w-full">Adicionar etapa</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Origens de leads" :padding="false">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($sources as $source)
                    <li wire:key="source-{{ $source->id }}" class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p @class(['text-sm font-medium', 'text-slate-900 dark:text-white' => $source->is_active, 'text-slate-400 line-through' => ! $source->is_active])>{{ $source->name }}</p>
                            <p class="text-xs text-slate-500">{{ $source->leads_count }} leads</p>
                        </div>
                        <button type="button" wire:click="toggleSource({{ $source->id }})" class="text-xs link">{{ $source->is_active ? 'Desativar' : 'Ativar' }}</button>
                    </li>
                @endforeach
            </ul>
            <form wire:submit="addSource" class="flex items-start gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
                <div class="flex-1">
                    <input type="text" wire:model="sourceName" class="form-control" placeholder="Nova origem" aria-label="Nova origem">
                    @error('sourceName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <x-ui.button icon="plus">Adicionar</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
