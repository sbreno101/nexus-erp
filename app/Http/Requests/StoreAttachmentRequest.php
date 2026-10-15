<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de upload. Lista branca de extensões e limite de tamanho:
 * nunca aceitamos executáveis ou scripts.
 */
class StoreAttachmentRequest extends FormRequest
{
    /**
     * Registros que aceitam anexos (apelidos do morph map).
     */
    public const array ATTACHABLE_TYPES = [
        'customer', 'supplier', 'proposal', 'contract', 'service_order', 'ticket', 'receivable', 'payable',
    ];

    public function authorize(): bool
    {
        // A autorização depende do registro "dono" e é feita no controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in(self::ATTACHABLE_TYPES)],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,odt,ods,zip,xml'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'O arquivo pode ter no máximo 10 MB.',
            'file.mimes' => 'Tipo de arquivo não permitido. Envie PDF, imagens, documentos do Office, CSV, TXT, XML ou ZIP.',
        ];
    }
}
