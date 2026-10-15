<?php

namespace App\Http\Controllers\Operations\Concerns;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Opções dos selects compartilhados pelos formulários de OS e chamados.
 *
 * Detalhe importante: na EDIÇÃO, o registro pode apontar para um cliente que
 * hoje está inativo (ou um serviço desativado). Se ele não estiver na lista,
 * o select ficaria vazio e o usuário perderia o vínculo ao salvar. Por isso
 * cada método aceita o id atual ($includeId), que é sempre incluído.
 */
trait ProvidesOperationFormOptions
{
    /**
     * Clientes ativos ordenados por nome (+ o cliente atual, se houver).
     *
     * @return array<int, string>
     */
    protected function customerOptions(?int $includeId = null): array
    {
        return Customer::query()
            ->where(function (Builder $query) use ($includeId): void {
                $query->active()->when($includeId, fn (Builder $query) => $query->orWhere('id', $includeId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name'])
            ->mapWithKeys(fn (Customer $customer): array => [$customer->id => $customer->display_name])
            ->all();
    }

    /**
     * Contratos com o cliente carregado (para exibir "número — cliente").
     * O formulário filtra no navegador os contratos do cliente escolhido.
     *
     * @return Collection<int, Contract>
     */
    protected function contractOptions(): Collection
    {
        return Contract::query()
            ->with('customer:id,name,trade_name')
            ->orderByDesc('number')
            ->get(['id', 'number', 'customer_id']);
    }

    /**
     * Ordens de serviço pendentes (+ a OS atual, se houver), com o cliente
     * carregado. O formulário de chamados filtra as OS do cliente escolhido.
     *
     * @return Collection<int, ServiceOrder>
     */
    protected function serviceOrderOptions(?int $includeId = null): Collection
    {
        return ServiceOrder::query()
            ->with('customer:id,name,trade_name')
            ->where(function (Builder $query) use ($includeId): void {
                $query->pending()->when($includeId, fn (Builder $query) => $query->orWhere('id', $includeId));
            })
            ->orderByDesc('number')
            ->get(['id', 'number', 'title', 'customer_id']);
    }

    /**
     * Serviços ativos do catálogo (+ o serviço atual, se houver).
     *
     * @return array<int, string>
     */
    protected function serviceOptions(?int $includeId = null): array
    {
        return CatalogItem::query()
            ->where('type', CatalogItemType::Service)
            ->where(function (Builder $query) use ($includeId): void {
                $query->active()->when($includeId, fn (Builder $query) => $query->orWhere('id', $includeId));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (CatalogItem $item): array => [$item->id => "{$item->code} — {$item->name}"])
            ->all();
    }
}
