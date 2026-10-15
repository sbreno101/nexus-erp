<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreInteractionRequest;
use App\Models\Interaction;
use App\Support\MorphTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Histórico de interações (ligações, reuniões, anotações) de clientes,
 * leads e oportunidades.
 */
class InteractionController extends Controller
{
    public function store(StoreInteractionRequest $request): RedirectResponse
    {
        $target = MorphTarget::resolve($request->string('interactable_type')->toString(), $request->integer('interactable_id'), StoreInteractionRequest::TYPES);

        $this->authorize('attach', $target);

        $target->interactions()->create([
            ...$request->safe()->only(['type', 'subject', 'description', 'occurred_at']),
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Interação registrada.');
    }

    public function destroy(Interaction $interaction): RedirectResponse
    {
        $target = $interaction->interactable;
        abort_if($target === null, 404);

        $this->authorize('attach', $target);
        abort_unless($interaction->user_id === Auth::id() || Auth::user()->isSuperAdmin(), 403, 'Somente o autor pode excluir a interação.');

        $interaction->delete();

        return back()->with('success', 'Interação removida.');
    }
}
