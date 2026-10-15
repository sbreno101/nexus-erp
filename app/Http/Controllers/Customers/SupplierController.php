<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Parties\DeleteBusinessParty;
use App\Actions\Parties\SaveBusinessParty;
use App\Enums\BillStatus;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\SupplierRequest;
use App\Models\Supplier;
use App\Support\CsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de fornecedores (listagem no componente Livewire SupplierIndex).
 */
class SupplierController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create', ['supplier' => new Supplier(['type' => 'company', 'status' => RecordStatus::Active])]);
    }

    public function store(SupplierRequest $request, SaveBusinessParty $saveParty): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $saveParty->handle(new Supplier, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load([
            'addresses', 'contacts', 'attachments.uploader',
            'payables' => fn ($query) => $query->with('category')->latest('due_date')->limit(15),
            'recurringExpenses',
        ]);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'openCents' => (int) $supplier->payables()->whereIn('status', BillStatus::outstanding())->sum(DB::raw('amount_cents - paid_cents')),
            'paidCents' => (int) $supplier->payables()->sum('paid_cents'),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', ['supplier' => $supplier->load(['addresses', 'contacts'])]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SaveBusinessParty $saveParty): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $saveParty->handle($supplier, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Supplier $supplier, DeleteBusinessParty $deleteParty): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $deleteParty->handle($supplier);

        return redirect()->route('suppliers.index')->with('success', 'Fornecedor excluído.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Supplier::class);

        $query = Supplier::query()
            ->search($request->query('busca'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $supplier) {
                yield [
                    $supplier->id, $supplier->type->label(), $supplier->name, $supplier->trade_name, $supplier->formatted_document,
                    $supplier->email, $supplier->phone, $supplier->status->label(), br_date($supplier->created_at),
                ];
            }
        })();

        return CsvExporter::download('fornecedores-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Tipo', 'Nome/Razão social', 'Nome fantasia', 'CPF/CNPJ', 'E-mail', 'Telefone', 'Status', 'Cadastrado em',
        ], $rows);
    }
}
