<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Sair devolve ao login, não à página pública (US-1.2): quem saiu no navegador
 * compartilhado vê a porta de entrada, não a vitrine.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('login');
    }
}
