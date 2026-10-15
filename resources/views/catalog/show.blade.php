<x-layouts::app :title="$catalogItem->name">
    <x-ui.page-header :title="$catalogItem->name" :back="route('catalog.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$catalogItem->type->color()">{{ $catalogItem->type->label() }}</x-ui.badge>
                @if ($catalogItem->is_active)
                    <x-ui.badge color="green">Ativo</x-ui.badge>
                @else
                    <x-ui.badge color="gray">Inativo</x-ui.badge>
                @endif
                <span class="font-mono">{{ $catalogItem->code }}</span>
            </div>
        </x-slot:meta>

        @can('update', $catalogItem)
            <x-ui.button icon="pencil" :href="route('catalog.edit', $catalogItem)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Dados do item">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="dl-item"><dt>Código</dt><dd class="font-mono">{{ $catalogItem->code }}</dd></div>
                    <div class="dl-item"><dt>Nome</dt><dd>{{ $catalogItem->name }}</dd></div>
                    <div class="dl-item"><dt>Tipo</dt><dd>{{ $catalogItem->type->label() }}</dd></div>
                    <div class="dl-item"><dt>Categoria</dt><dd>{{ $catalogItem->category?->name ?? 'Sem categoria' }}</dd></div>
                    <div class="dl-item"><dt>Unidade</dt><dd>{{ $catalogItem->unit }}</dd></div>
                    <div class="dl-item"><dt>Preço atual</dt><dd class="tabular-nums">{{ money($catalogItem->price_cents) }}</dd></div>
                    <div class="dl-item"><dt>Cadastrado em</dt><dd>{{ br_date($catalogItem->created_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Última alteração</dt><dd>{{ br_date($catalogItem->updated_at, true) }}</dd></div>
                    @if ($catalogItem->description)
                        <div class="dl-item sm:col-span-2"><dt>Descrição</dt><dd class="whitespace-pre-line">{{ $catalogItem->description }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <section>
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Histórico de alterações (inclui preços)</h2>
                <x-audit-history :logs="$auditLogs" :labels="['price_cents' => 'preço', 'is_active' => 'ativo', 'catalog_category_id' => 'categoria', 'unit' => 'unidade']" />
            </section>
        </div>

        <div class="space-y-6">
            {{-- Uso do item: quantas linhas de propostas/contratos referenciam este item. --}}
            <x-ui.stat label="Uso em propostas" :value="$catalogItem->proposal_items_count" icon="document-text" color="blue" hint="Linhas de propostas com este item" />
            <x-ui.stat label="Uso em contratos" :value="$catalogItem->contract_items_count" icon="document-check" color="green" hint="Linhas de contratos com este item" />

            @can('delete', $catalogItem)
                <div class="flex justify-end">
                    <x-ui.confirm-action :action="route('catalog.destroy', $catalogItem)" method="DELETE" title="Excluir item"
                        message="O item deixará de aparecer no catálogo. Propostas e contratos que já o utilizam não são alterados."
                        confirm="Excluir item" icon="trash" size="sm">Excluir item</x-ui.confirm-action>
                </div>
            @endcan
        </div>
    </div>
</x-layouts::app>
