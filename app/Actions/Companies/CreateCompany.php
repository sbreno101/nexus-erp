<?php

namespace App\Actions\Companies;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Cadastra uma nova empresa (tenant) já com a configuração inicial.
 */
class CreateCompany
{
    public function __construct(private ProvisionCompanyDefaults $provisionDefaults) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::create([
                ...$data,
                'status' => $data['status'] ?? CompanyStatus::Active->value,
            ]);

            $this->provisionDefaults->handle($company);

            return $company;
        });
    }
}
