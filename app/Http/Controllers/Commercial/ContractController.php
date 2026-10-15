<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Contracts\ChangeContractStatus;
use App\Actions\Contracts\GenerateContractCharges;
use App\Actions\Contracts\RenewContract;
use App\Actions\Contracts\SaveContract;
use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Enums\FinancialCategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\ChangeStatusRequest;
use App\Http\Requests\Commercial\ContractRequest;
use App\Models\CatalogItem;
use App\Models\CompanyUnit;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\FinancialCategory;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Contratos: cadastro, detalhe, mudanças de status, renovação e geração
 * manual de cobranças. Contratos não são excluídos (são cancelados).
 */
class ContractController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Contract::class);

        return view('contracts.create', [
            'contract' => new Contract([
                'customer_id' => $request->integer('customer_id') ?: null,
                'owner_id' => auth()->id(),
                'start_date' => today(),
                'billing_frequency' => BillingFrequency::Monthly,
                'billing_day' => $this->currentCompany->getOrFail()->settingsOrDefault()->default_billing_day,
                'renewal_months' => 12,
                'auto_renew' => false,
            ]),
            'items' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(ContractRequest $request, SaveContract $saveContract): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $contract = $saveContract->create($request->validated());

        return redirect()->route('contracts.show', $contract)->with('success', "Contrato {$contract->number} cadastrado como rascunho. Ative-o para gerar cobranças.");
    }

    public function show(Contract $contract): View
    {
        $this->authorize('view', $contract);

        $contract->load([
            'customer', 'proposal', 'unit', 'owner', 'category', 'items.catalogItem',
            'statusChanges.user', 'attachments.uploader',
            'receivables' => fn ($query) => $query->orderByDesc('due_date')->limit(24),
            'serviceOrders' => fn ($query) => $query->latest()->limit(10),
            'tickets' => fn ($query) => $query->latest()->limit(10),
        ]);

        $billing = $contract->receivables()->selectRaw(
            'COALESCE(SUM(amount_cents) FILTER (WHERE status <> ?), 0) AS billed, COALESCE(SUM(paid_cents), 0) AS paid',
            ['cancelled'],
        )->first();

        return view('contracts.show', [
            'contract' => $contract,
            'billing' => $billing,
            'auditLogs' => $contract->auditLogs()->with('user')->limit(20)->get(),
        ]);
    }

    public function edit(Contract $contract): View
    {
        $this->authorize('update', $contract);

        $contract->load('items');

        return view('contracts.edit', [
            'contract' => $contract,
            'items' => $contract->items->map(fn ($item) => [
                'catalog_item_id' => $item->catalog_item_id,
                'description' => $item->description,
                'quantity' => $item->formattedQuantity(),
                'unit' => $item->unit,
                'unit_price' => money($item->unit_price_cents, false),
                'discount' => $item->discount_cents ? money($item->discount_cents, false) : '',
            ])->all(),
            ...$this->formOptions(),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract, SaveContract $saveContract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $saveContract->update($contract, $request->validated());

        return redirect()->route('contracts.show', $contract)->with('success', 'Contrato atualizado. As próximas cobranças usarão o novo valor.');
    }

    public function changeStatus(ChangeStatusRequest $request, Contract $contract, ChangeContractStatus $changeStatus): RedirectResponse
    {
        $this->authorize('manage', $contract);

        $request->validate(['status' => [Rule::enum(ContractStatus::class)]]);
        $target = ContractStatus::from($request->string('status')->toString());

        $changeStatus->handle($contract, $target, $request->input('reason'));

        return back()->with('success', "Contrato {$contract->number}: {$target->label()}.");
    }

    public function renew(Request $request, Contract $contract, RenewContract $renewContract): RedirectResponse
    {
        $this->authorize('manage', $contract);

        $validated = $request->validate(['months' => ['nullable', 'integer', 'between:1,120']]);
        $renewContract->handle($contract, $validated['months'] ?? null);

        return back()->with('success', 'Contrato renovado até '.br_date($contract->fresh()->end_date).'.');
    }

    /**
     * Gera agora as cobranças que estiverem dentro da janela de antecedência
     * (o mesmo processo que o Scheduler executa diariamente).
     */
    public function generateCharges(Contract $contract, GenerateContractCharges $generate): RedirectResponse
    {
        $this->authorize('manage', $contract);

        $days = $this->currentCompany->getOrFail()->settingsOrDefault()->billing_days_in_advance;
        $created = $generate->forContract($contract, CarbonImmutable::today()->addDays($days));

        return back()->with($created > 0 ? 'success' : 'warning', $created > 0
            ? "{$created} cobrança(s) gerada(s)."
            : "Nenhuma cobrança pendente dentro da janela de {$days} dias.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($customer) => [$customer->id => $customer->display_name])->all(),
            'units' => CompanyUnit::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'frequencies' => BillingFrequency::options(),
            'categories' => FinancialCategory::query()->ofType(FinancialCategoryType::Income)->pluck('name', 'id')->all(),
            'catalog' => CatalogItem::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'unit', 'price_cents'])
                ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'label' => "{$item->code} — {$item->name}", 'unit' => $item->unit, 'price_cents' => $item->price_cents])
                ->all(),
        ];
    }
}
