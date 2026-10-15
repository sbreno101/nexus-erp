<?php

namespace App\Livewire\Finance;

use App\Enums\FinancialCategoryType;
use App\Models\CostCenter;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Payable;
use App\Models\Receivable;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cadastros auxiliares do financeiro: categorias (receita/despesa) e centros de custo.
 *
 * Itens já usados em títulos ou lançamentos não são excluídos — são
 * inativados, para não quebrar o histórico e os relatórios.
 */
#[Title('Configurações financeiras')]
class FinanceSettings extends Component
{
    public string $categoryName = '';

    public string $categoryType = 'expense';

    public string $centerCode = '';

    public string $centerName = '';

    public function mount(): void
    {
        $this->authorize('finance.configure');
    }

    public function addCategory(): void
    {
        $this->authorize('finance.configure');

        $validated = $this->validate([
            'categoryType' => ['required', Rule::enum(FinancialCategoryType::class)],
            'categoryName' => ['required', 'string', 'max:100', Rule::unique('financial_categories', 'name')
                ->where('company_id', app(CurrentCompany::class)->id())->where('type', $this->categoryType)],
        ], attributes: ['categoryName' => 'nome da categoria', 'categoryType' => 'tipo']);

        FinancialCategory::create(['name' => $validated['categoryName'], 'type' => $validated['categoryType'], 'is_active' => true]);
        $this->reset('categoryName');
        $this->dispatch('notify', type: 'success', message: 'Categoria criada.');
    }

    public function toggleCategory(int $categoryId): void
    {
        $this->authorize('finance.configure');

        $category = FinancialCategory::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->authorize('finance.configure');

        $inUse = Receivable::query()->where('financial_category_id', $categoryId)->exists()
            || Payable::query()->where('financial_category_id', $categoryId)->exists()
            || FinancialTransaction::query()->where('financial_category_id', $categoryId)->exists();

        if ($inUse) {
            $this->dispatch('notify', type: 'error', message: 'Categoria em uso: inative-a em vez de excluir.');

            return;
        }

        FinancialCategory::query()->findOrFail($categoryId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Categoria excluída.');
    }

    public function addCostCenter(): void
    {
        $this->authorize('finance.configure');

        $companyId = app(CurrentCompany::class)->id();
        $this->centerCode = mb_strtoupper(trim($this->centerCode));

        $validated = $this->validate([
            'centerCode' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('cost_centers', 'code')->where('company_id', $companyId)],
            'centerName' => ['required', 'string', 'max:100'],
        ], attributes: ['centerCode' => 'código', 'centerName' => 'nome']);

        CostCenter::create(['code' => $validated['centerCode'], 'name' => $validated['centerName'], 'is_active' => true]);
        $this->reset('centerCode', 'centerName');
        $this->dispatch('notify', type: 'success', message: 'Centro de custo criado.');
    }

    public function toggleCostCenter(int $centerId): void
    {
        $this->authorize('finance.configure');

        $center = CostCenter::query()->findOrFail($centerId);
        $center->update(['is_active' => ! $center->is_active]);
    }

    public function render(): View
    {
        return view('livewire.finance.finance-settings', [
            'categories' => FinancialCategory::query()->withCount(['receivables', 'payables'])->orderBy('type')->orderBy('name')->get()->groupBy(fn ($category) => $category->type->value),
            'costCenters' => CostCenter::query()->withCount('payables')->orderBy('code')->get(),
            'typeOptions' => FinancialCategoryType::options(),
        ]);
    }
}
