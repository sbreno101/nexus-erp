<?php

namespace App\Actions\Companies;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Atualiza os dados institucionais, as configurações e o logotipo da empresa.
 *
 * Arquivos não participam da transação do banco. Por isso a ordem é:
 *   1. grava o novo logotipo no disco (se houver);
 *   2. atualiza o banco dentro de uma transação;
 *   3. se o banco falhar, apaga o arquivo novo (não deixa "lixo" no disco);
 *   4. se der certo, apaga o logotipo antigo.
 *
 * O disco "local" é privado (fora de public/): a imagem é entregue pela
 * rota company.logo, que confere se o usuário tem acesso à empresa.
 */
class UpdateCompanySettings
{
    /**
     * @param  array<string, mixed>  $companyData  campos fillable de Company (sem status/logo_path)
     * @param  array<string, mixed>  $settingsData  campos fillable de CompanySetting
     */
    public function handle(
        Company $company,
        array $companyData,
        array $settingsData,
        ?UploadedFile $logo = null,
        bool $removeLogo = false,
    ): Company {
        $previousLogoPath = $company->logo_path;
        $newLogoPath = $logo !== null ? $this->storeLogo($company, $logo) : null;

        try {
            DB::transaction(function () use ($company, $companyData, $settingsData, $newLogoPath, $removeLogo): void {
                $attributes = $companyData;

                if ($newLogoPath !== null) {
                    $attributes['logo_path'] = $newLogoPath;
                } elseif ($removeLogo) {
                    $attributes['logo_path'] = null;
                }

                $company->update($attributes);
                $company->settingsOrDefault()->update($settingsData);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('local')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($previousLogoPath !== null && ($newLogoPath !== null || $removeLogo)) {
            Storage::disk('local')->delete($previousLogoPath);
        }

        return $company;
    }

    /**
     * Nome UUID: evita colisões e não expõe o nome original do arquivo.
     */
    private function storeLogo(Company $company, UploadedFile $logo): string
    {
        $extension = $logo->extension() ?: 'png';

        return $logo->storeAs("company-logos/{$company->id}", Str::uuid()->toString().'.'.$extension, 'local');
    }
}
