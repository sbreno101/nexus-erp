<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Proposals\ChangeProposalStatus;
use App\Actions\Proposals\ConvertProposalToContract;
use App\Actions\Proposals\SaveProposal;
use App\Enums\BillingFrequency;
use App\Enums\FinancialCategoryType;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\ChangeStatusRequest;
use App\Http\Requests\Commercial\ConvertProposalRequest;
use App\Http\Requests\Commercial\ProposalRequest;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\FinancialCategory;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Propostas comerciais: formulário, detalhe, ciclo de status, PDF e
 * conversão em contrato. Toda regra de negócio está nas Actions.
 */
class ProposalController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Proposal::class);

        $settings = $this->currentCompany->getOrFail()->settingsOrDefault();

        return view('proposals.create', [
            'proposal' => new Proposal([
                'customer_id' => $request->integer('customer_id') ?: null,
                'opportunity_id' => $request->integer('opportunity_id') ?: null,
                'owner_id' => auth()->id(),
                'issue_date' => today(),
                'valid_until' => today()->addDays($settings->proposal_validity_days),
                'payment_terms' => $settings->default_payment_terms,
                'discount_cents' => 0,
            ]),
            'items' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(ProposalRequest $request, SaveProposal $saveProposal): RedirectResponse
    {
        $this->authorize('create', Proposal::class);

        $proposal = $saveProposal->create($request->proposalData());

        return redirect()->route('proposals.show', $proposal)->with('success', "Proposta {$proposal->number} criada.");
    }

    public function show(Proposal $proposal): View
    {
        $this->authorize('view', $proposal);

        $proposal->load(['customer', 'opportunity', 'owner', 'approver', 'items.catalogItem', 'contract', 'statusChanges.user', 'attachments.uploader']);

        return view('proposals.show', ['proposal' => $proposal]);
    }

    public function edit(Proposal $proposal): View
    {
        $this->authorize('update', $proposal);

        $proposal->load('items');

        return view('proposals.edit', [
            'proposal' => $proposal,
            'items' => $proposal->items->map(fn ($item) => [
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

    public function update(ProposalRequest $request, Proposal $proposal, SaveProposal $saveProposal): RedirectResponse
    {
        $this->authorize('update', $proposal);

        $saveProposal->update($proposal, $request->proposalData());

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposta atualizada.');
    }

    public function destroy(Proposal $proposal): RedirectResponse
    {
        $this->authorize('delete', $proposal);

        $proposal->delete();

        return redirect()->route('proposals.index')->with('success', 'Proposta excluída.');
    }

    /**
     * Enviar, aprovar, recusar, cancelar, expirar ou reabrir (máquina de estados no Enum).
     */
    public function changeStatus(ChangeStatusRequest $request, Proposal $proposal, ChangeProposalStatus $changeStatus): RedirectResponse
    {
        $this->authorize('changeStatus', $proposal);

        $request->validate(['status' => [Rule::enum(ProposalStatus::class)]]);
        $target = ProposalStatus::from($request->string('status')->toString());

        $changeStatus->handle($proposal, $target, $request->input('reason'));

        return back()->with('success', "Proposta {$proposal->number}: {$target->label()}.");
    }

    public function pdf(Proposal $proposal): Response
    {
        $this->authorize('view', $proposal);

        $proposal->load(['customer.primaryAddress', 'items', 'owner', 'company']);

        return Pdf::loadView('proposals.pdf', [
            'proposal' => $proposal,
            'company' => $proposal->company,
            'logo' => $proposal->company->logoContents(),
        ])->setPaper('a4')->download("proposta-{$proposal->number}.pdf");
    }

    public function convertForm(Proposal $proposal): View|RedirectResponse
    {
        $this->authorize('convert', $proposal);

        if (! $proposal->canBeConvertedToContract()) {
            return redirect()->route('proposals.show', $proposal)->with('error', 'Somente propostas aprovadas e ainda não convertidas podem gerar contrato.');
        }

        return view('proposals.convert', [
            'proposal' => $proposal->load(['customer', 'items']),
            'frequencies' => BillingFrequency::options(),
            'categories' => FinancialCategory::query()->ofType(FinancialCategoryType::Income)->pluck('name', 'id')->all(),
            'defaultBillingDay' => $this->currentCompany->getOrFail()->settingsOrDefault()->default_billing_day,
        ]);
    }

    public function convert(ConvertProposalRequest $request, Proposal $proposal, ConvertProposalToContract $convert): RedirectResponse
    {
        $this->authorize('convert', $proposal);

        $contract = $convert->handle($proposal, $request->validated());

        return redirect()->route('contracts.show', $contract)->with('success', "Contrato {$contract->number} gerado a partir da proposta {$proposal->number}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($customer) => [$customer->id => $customer->display_name])->all(),
            'opportunities' => Opportunity::query()->open()->whereNotNull('customer_id')->orderBy('title')->pluck('title', 'id')->all(),
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'catalog' => CatalogItem::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'unit', 'price_cents'])
                ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'label' => "{$item->code} — {$item->name}", 'unit' => $item->unit, 'price_cents' => $item->price_cents])
                ->all(),
        ];
    }
}
