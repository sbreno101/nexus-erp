<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Controller base. O trait AuthorizesRequests disponibiliza $this->authorize(),
 * que consulta as Policies e responde 403 quando o usuário não tem permissão.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
