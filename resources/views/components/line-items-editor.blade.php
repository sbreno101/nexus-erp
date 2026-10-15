{{--
    Editor de itens (propostas e contratos) com prévia dos totais em Alpine.js.
    O valor OFICIAL é sempre recalculado no servidor (LineItemCalculator).

    @props:
      $items     lista inicial [{catalog_item_id, description, quantity, unit, unit_price, discount}]
      $catalog   itens do catálogo [{id, name, label, unit, price_cents}]
      $discount  desconto geral inicial (texto "1.234,56") — null para ocultar o campo
--}}
@props(['items' => [], 'catalog' => [], 'discount' => null])

@php
    $initialItems = old('items', $items);
    $initialItems = collect($initialItems)->map(fn ($item, $index) => [
        'key' => $index.'-'.uniqid(),
        'catalog_item_id' => (string) ($item['catalog_item_id'] ?? ''),
        'description' => $item['description'] ?? '',
        'quantity' => (string) ($item['quantity'] ?? '1'),
        'unit' => $item['unit'] ?? 'UN',
        'unit_price' => $item['unit_price'] ?? (isset($item['unit_price_cents']) ? money((int) $item['unit_price_cents'], false) : ''),
        'discount' => $item['discount'] ?? (isset($item['discount_cents']) && $item['discount_cents'] ? money((int) $item['discount_cents'], false) : ''),
    ])->values()->all();
    $itemErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'items'))->flatten()->unique();
@endphp

<div x-data="lineItemsEditor({ items: @js($initialItems), catalog: @js($catalog), discount: @js(old('discount', $discount ?? '0,00')) })">
    @if ($itemErrors->isNotEmpty())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300" role="alert">
            <ul class="list-inside list-disc">
                @foreach ($itemErrors as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-1/3">Item / descrição</th>
                    <th class="w-24">Qtd.</th>
                    <th class="w-20">Unid.</th>
                    <th class="w-36">Preço unit. (R$)</th>
                    <th class="w-32">Desconto (R$)</th>
                    <th class="w-32 text-right">Total</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="(item, index) in items" :key="item.key">
                    <tr>
                        <td class="!whitespace-normal">
                            <select class="form-control mb-1" :name="`items[${index}][catalog_item_id]`" x-model="item.catalog_item_id" x-on:change="selectCatalog(item)" aria-label="Item do catálogo">
                                <option value="">— Item livre (sem catálogo) —</option>
                                <template x-for="entry in catalog" :key="entry.id">
                                    <option :value="String(entry.id)" x-text="entry.label" :selected="String(entry.id) === item.catalog_item_id"></option>
                                </template>
                            </select>
                            <input type="text" class="form-control" :name="`items[${index}][description]`" x-model="item.description" placeholder="Descrição exibida na proposta" required maxlength="255" aria-label="Descrição">
                        </td>
                        <td><input type="text" inputmode="decimal" class="form-control text-right" :name="`items[${index}][quantity]`" x-model="item.quantity" required aria-label="Quantidade"></td>
                        <td><input type="text" class="form-control" :name="`items[${index}][unit]`" x-model="item.unit" maxlength="10" aria-label="Unidade"></td>
                        <td><input type="text" inputmode="numeric" class="form-control text-right" :name="`items[${index}][unit_price]`" x-model="item.unit_price" x-on:blur="formatMoneyField(item, 'unit_price')" placeholder="0,00" required aria-label="Preço unitário"></td>
                        <td><input type="text" inputmode="numeric" class="form-control text-right" :name="`items[${index}][discount]`" x-model="item.discount" x-on:blur="formatMoneyField(item, 'discount')" placeholder="0,00" aria-label="Desconto do item"></td>
                        <td class="text-right font-medium tabular-nums" x-text="format(itemTotal(item))"></td>
                        <td><button type="button" x-on:click="removeItem(index)" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remover item"><x-icon name="trash" class="size-4" /></button></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addItem()">Adicionar item</x-ui.button>

        <dl class="w-full max-w-xs space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-medium tabular-nums" x-text="format(subtotal)"></dd></div>
            @if ($discount !== null)
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Desconto geral (R$)</dt>
                    <dd><input type="text" name="discount" inputmode="numeric" class="form-control w-32 text-right" x-model="discount" x-on:blur="discount = discount === '' ? '0,00' : window.NexusMoney.formatCents(window.NexusMoney.parseMoneyToCents(discount))" aria-label="Desconto geral"></dd>
                </div>
            @endif
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base dark:border-slate-700"><dt class="font-semibold">Total</dt><dd class="font-bold tabular-nums" x-text="format(total)"></dd></div>
            <p class="text-xs text-slate-400">Prévia — o total é recalculado e validado no servidor ao salvar.</p>
        </dl>
    </div>
</div>
