<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\StoreAttachment;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Support\MorphTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download e remoção de anexos.
 *
 * Os arquivos ficam no disco privado (storage/app/private) e nunca são
 * servidos diretamente pelo servidor web: todo download passa por aqui,
 * após a verificação da Policy do registro ao qual o anexo pertence.
 */
class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, StoreAttachment $storeAttachment): RedirectResponse
    {
        $attachable = MorphTarget::resolve($request->string('attachable_type')->toString(), $request->integer('attachable_id'), StoreAttachmentRequest::ATTACHABLE_TYPES);

        $this->authorize('attach', $attachable);

        $storeAttachment->handle($attachable, $request->file('file'), $request->input('description'));

        return back()->with('success', 'Arquivo anexado com sucesso.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Arquivo não encontrado.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        // Soft delete: o registro e o arquivo são preservados para auditoria.
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}
