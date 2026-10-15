<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras comuns de títulos financeiros (contas a receber e a pagar).
 *
 * A competência chega do formulário como mês ("2026-10") e é gravada como
 * o primeiro dia do mês ("2026-10-01"), para agrupar relatórios por mês.
 */
abstract class BillRequest extends FormRequest
{
    use NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * "income" para contas a receber, "expense" para contas a pagar.
     */
    abstract protected function categoryType(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function partyRules(int $companyId): array;

    protected function moneyFields(): array
    {
        return ['amount' => 'amount_cents'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyFields();

        $competence = (string) $this->input('competence', '');

        if (preg_match('/^\d{4}-\d{2}$/', $competence) === 1) {
            $this->merge(['competence' => $competence.'-01']);
        } elseif ($competence === '' && $this->filled('due_date')) {
            $this->merge(['competence' => substr((string) $this->input('due_date'), 0, 7).'-01']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            ...$this->partyRules($companyId),
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', $companyId)->where('type', $this->categoryType())],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'description' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'competence' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['document_number' => 'número do documento'];
    }
}
