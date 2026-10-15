{{--
    Formulário compartilhado de criação/edição de itens do catálogo.
    Variáveis: $catalogItem, $categories, $action (URL), $method, $cancelUrl

    A lista de categorias é filtrada no navegador (Alpine.js) conforme o tipo
    escolhido; o servidor valida de novo (categoria do mesmo tipo).
--}}
@php
    $selectedType = old('type', $catalogItem->type?->value ?? 'service');
    $selectedCategory = (string) old('catalog_category_id', $catalogItem->catalog_category_id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
    x-data="{ submitting: false, type: @js($selectedType), category: @js($selectedCategory) }"
    x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados do item">
        <div class="grid gap-4 md:grid-cols-6">
            <div class="md:col-span-2">
                <span class="form-label">Tipo <span class="text-red-500">*</span></span>
                <div class="flex gap-4 pt-2">
                    @foreach (\App\Enums\CatalogItemType::cases() as $itemType)
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="type" value="{{ $itemType->value }}" x-model="type" x-on:change="category = ''" class="text-brand-600 focus:ring-brand-500">
                            {{ $itemType->label() }}
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-form.input name="code" label="Código" class="md:col-span-2" :value="$catalogItem->code" required maxlength="30"
                style="text-transform: uppercase" help="Gravado em maiúsculas. Único na empresa." />
            <x-form.input name="unit" label="Unidade" class="md:col-span-2" :value="$catalogItem->unit" required maxlength="10" help="Ex.: UN, H, MÊS, KG." />

            <x-form.input name="name" label="Nome" class="md:col-span-4" :value="$catalogItem->name" required maxlength="255" />
            <x-form.money name="price" label="Preço" class="md:col-span-2" :value="$catalogItem->price_cents" required />

            <div class="md:col-span-3">
                <label for="field-catalog_category_id" class="form-label">Categoria</label>
                <select id="field-catalog_category_id" name="catalog_category_id" x-model="category"
                    @class(['form-control', 'form-control-invalid' => $errors->has('catalog_category_id')])>
                    <option value="">Sem categoria</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" x-show="type === @js($category->type->value)" x-bind:disabled="type !== @js($category->type->value)">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('catalog_category_id')
                    <p class="form-error">{{ $message }}</p>
                @else
                    @can('catalog.update')
                        <p class="form-help"><a href="{{ route('catalog.categories') }}" class="link">Gerenciar categorias</a></p>
                    @endcan
                @enderror
            </div>
            <div class="flex items-end pb-2 md:col-span-3">
                <x-form.checkbox name="is_active" label="Item ativo" :checked="$catalogItem->is_active" help="Itens inativos não aparecem para seleção em novas propostas e OS." />
            </div>

            <x-form.textarea name="description" label="Descrição" class="md:col-span-6" :value="$catalogItem->description" rows="4" maxlength="5000" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
