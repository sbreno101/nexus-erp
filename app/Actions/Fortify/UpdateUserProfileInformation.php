<?php

namespace App\Actions\Fortify;

use App\Enums\ThemePreference;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Atualização do perfil pelo próprio usuário (rota do Fortify PUT /user/profile-information).
 *
 * Se o e-mail mudar, ele volta a ficar "não verificado" e um novo link de
 * verificação é enviado (o usuário precisa provar que é dono do novo e-mail).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', Rule::enum(ThemePreference::class)],
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = mb_strtolower($validated['email']) !== $user->email;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'theme' => $validated['theme'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }
    }
}
