<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Parties\DeleteBusinessParty;
use App\Actions\Parties\SaveBusinessParty;
use App\Enums\BillStatus;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\CsvExporter;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de clientes (a listagem fica no componente Livewire CustomerIndex).
 *
 * Responsabilidade do Controller: receber a requisição, autorizar (Policy),
 * delegar a regra de negócio (Actions) e devolver a resposta. Nada mais.
 */
class CustomerController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'customer' => new Customer(['type' => 'company', 'status' => CustomerStatus::Active]),
            'users' => User::optionsForCompany($this->currentCompany->id()),
        ]);
    }

    public function store(CustomerRequest $request, SaveBusinessParty $saveParty): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $saveParty->handle(new Customer, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        // Eager loading: carrega os relacionamentos em poucas consultas (evita N+1).
        $customer->load([
            'owner', 'addresses', 'contacts', 'originLead.source',
            'interactions.user', 'tasks.assignee',
            'attachments.uploader',
            'opportunities' => fn ($query) => $query->with('stage')->latest()->limit(10),
            'proposals' => fn ($query) => $query->latest()->limit(10),
            'contracts' => fn ($query) => $query->latest()->limit(10),
            'tickets' => fn ($query) => $query->latest()->limit(10),
            'serviceOrders' => fn ($query) => $query->latest()->limit(10),
            'receivables' => fn ($query) => $query->latest('due_date')->limit(10),
        ]);

        $financialSummary = [
            'open_cents' => (int) $customer->receivables()->whereIn('status', BillStatus::outstanding())->sum(DB::raw('amount_cents - paid_cents')),
            'overdue_cents' => (int) $customer->receivables()->overdue()->sum(DB::raw('amount_cents - paid_cents')),
            'paid_cents' => (int) $customer->receivables()->sum('paid_cents'),
        ];

        return view('customers.show', [
            'customer' => $customer,
            'financialSummary' => $financialSummary,
            'users' => User::query()->active()->memberOf($this->currentCompany->id())->orderBy('name')->get(['id', 'name']),
            'auditLogs' => $customer->auditLogs()->with('user')->limit(20)->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer->load(['addresses', 'contacts']),
            'users' => User::optionsForCompany($this->currentCompany->id()),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, SaveBusinessParty $saveParty): RedirectResponse
    {
        $this->authorize('update', $customer);

        $saveParty->handle($customer, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Customer $customer, DeleteBusinessParty $deleteParty): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $deleteParty->handle($customer);

        return redirect()->route('customers.index')->with('success', 'Cliente excluído.');
    }

    /**
     * Exporta os clientes (respeitando busca e filtro de status) em CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Customer::class);

        $query = Customer::query()
            ->with('owner:id,name')
            ->search($request->query('busca'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $customer) {
                yield [
                    $customer->id, $customer->type->label(), $customer->name, $customer->trade_name,
                    $customer->formatted_document, $customer->email, $customer->phone, $customer->mobile,
                    $customer->status->label(), $customer->owner?->name, br_date($customer->created_at),
                ];
            }
        })();

        return CsvExporter::download('clientes-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Tipo', 'Nome/Razão social', 'Nome fantasia', 'CPF/CNPJ', 'E-mail', 'Telefone', 'Celular', 'Status', 'Responsável', 'Cadastrado em',
        ], $rows);
    }
}
