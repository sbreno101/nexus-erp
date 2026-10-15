<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Armazena um arquivo no disco PRIVADO e o vincula a um registro.
 *
 * - O nome no disco é aleatório (UUID): evita sobrescrita, adivinhação de
 *   URLs e problemas com caracteres especiais. O nome original fica no banco.
 * - Os arquivos ficam separados por empresa: attachments/{company_id}/...
 */
class StoreAttachment
{
    public function handle(Model $attachable, UploadedFile $file, ?string $description = null): Attachment
    {
        $directory = sprintf('attachments/%d/%s', $attachable->getAttribute('company_id'), $attachable->getMorphClass());
        $filename = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());

        $path = $file->storeAs($directory, $filename, 'local');

        /** @var Attachment $attachment */
        $attachment = $attachable->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $description,
            'uploaded_by' => Auth::id(),
        ]);

        return $attachment;
    }
}
