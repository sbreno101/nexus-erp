<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Define uma nova senha a partir do link enviado por e-mail (recuperação de
 * senha ou convite de novo usuário).
 *
 * Como o link só chega a quem tem acesso à caixa de e-mail, concluir este
 * fluxo também comprova o e-mail: marcamos email_verified_at se estiver vazio.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
