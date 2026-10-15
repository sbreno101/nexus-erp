<?php

namespace App\Livewire\Operations;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Categorias de chamados da empresa (Dúvida, Problema técnico...).
 * Exige permissão de atualizar chamados.
 */
#[Title('Categorias de chamados')]
class TicketCategoryManager extends Component
{
    public string $name = '';

    /**
     * @var array<int, string>
     */
    public array $names = [];

    public function mount(): void
    {
        $this->authorize('tickets.update');
        $this->loadNames();
    }

    public function add(): void
    {
        $this->authorize('tickets.update');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'name')->where('company_id', app(CurrentCompany::class)->id())],
        ], attributes: ['name' => 'nome da categoria']);

        TicketCategory::create(['name' => $validated['name']]);
        $this->reset('name');
        $this->loadNames();
        $this->dispatch('notify', type: 'success', message: 'Categoria criada.');
    }

    public function rename(int $categoryId): void
    {
        $this->authorize('tickets.update');

        $this->validate([
            "names.{$categoryId}" => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'name')
                ->where('company_id', app(CurrentCompany::class)->id())->ignore($categoryId)],
        ], attributes: ["names.{$categoryId}" => 'nome']);

        TicketCategory::query()->findOrFail($categoryId)->update(['name' => $this->names[$categoryId]]);
        $this->dispatch('notify', type: 'success', message: 'Categoria renomeada.');
    }

    public function delete(int $categoryId): void
    {
        $this->authorize('tickets.update');

        if (Ticket::withTrashed()->where('ticket_category_id', $categoryId)->exists()) {
            $this->dispatch('notify', type: 'error', message: 'Há chamados nesta categoria; ela não pode ser excluída.');

            return;
        }

        TicketCategory::query()->findOrFail($categoryId)->delete();
        $this->loadNames();
        $this->dispatch('notify', type: 'success', message: 'Categoria excluída.');
    }

    public function render(): View
    {
        return view('livewire.operations.ticket-category-manager', [
            'categories' => TicketCategory::query()->withCount('tickets')->orderBy('name')->get(),
        ]);
    }

    private function loadNames(): void
    {
        $this->names = TicketCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
