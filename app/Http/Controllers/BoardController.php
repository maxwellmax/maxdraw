<?php

namespace App\Http\Controllers;

use App\Http\Resources\TrainingSessionResource;
use App\Services\CatalogService;
use App\Services\CurrentSessionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /**
     * Entrega a prancheta: a sessão corrente e o catálogo inteiro numa única
     * resposta Inertia (project-description → Core Workflow 1).
     *
     * O cronômetro chega pausado por construção — o que trafega é o
     * `elapsed_seconds` gravado, e o servidor não tem estado "rodando".
     */
    public function __invoke(Request $request, CurrentSessionResolver $currentSession, CatalogService $catalog): Response
    {
        $session = $currentSession->resolve($request->user());

        return Inertia::render('Board', [
            'session' => TrainingSessionResource::make($session)->resolve($request),
            'catalog' => $catalog->payload($session),
        ]);
    }
}
