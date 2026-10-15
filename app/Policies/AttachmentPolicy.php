<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Anexos herdam a autorização do registro ao qual pertencem:
 * quem pode VER o registro pode baixar o anexo; quem pode EDITAR pode
 * enviar e remover anexos.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->attachable !== null && Gate::forUser($user)->allows('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->attachable !== null && Gate::forUser($user)->allows('attach', $attachment->attachable);
    }
}
