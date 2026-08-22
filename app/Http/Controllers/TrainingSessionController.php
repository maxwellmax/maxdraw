<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainingSessionStoreRequest;
use App\Http\Requests\TrainingSessionUpdateRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use App\Services\CurrentSessionResolver;
use App\Services\SessionCreator;
use App\Services\SessionStateWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TrainingSessionController extends Controller
{
    /**
     * List the training sessions of the authenticated user.
     *
     * A rota resolve `{trainingSession}` já escopada ao dono (ver
     * `AppServiceProvider`); a `TrainingSessionPolicy` chamada aqui é a segunda
     * tranca do isolamento (US-1.4).
     *
     * @return AnonymousResourceCollection<int, TrainingSessionResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TrainingSession::class);

        return TrainingSessionResource::collection(
            TrainingSession::ownedBy($request->user())
                ->with(['sessionDuration', 'sequenceMode'])
                ->orderByDesc('last_opened_at')
                ->get()
        );
    }

    /**
     * Open a brand new training session, which becomes the current one.
     *
     * A sessão anterior fica intacta no banco — trocar de sessão não apaga nem
     * reescreve o que já estava salvo nela (US-11.2).
     */
    public function store(TrainingSessionStoreRequest $request, SessionCreator $creator): JsonResponse
    {
        Gate::authorize('create', TrainingSession::class);

        $session = $creator->create(
            $request->user(),
            $request->validated('problem_id'),
            $request->validated('duration_minutes'),
        );

        return TrainingSessionResource::make($session)
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    /**
     * Show a single training session.
     */
    public function show(TrainingSession $trainingSession): TrainingSessionResource
    {
        Gate::authorize('view', $trainingSession);

        return TrainingSessionResource::make($trainingSession);
    }

    /**
     * Make a saved session the current one and restore its whole state.
     */
    public function open(TrainingSession $trainingSession): TrainingSessionResource
    {
        Gate::authorize('view', $trainingSession);

        $trainingSession->update(['last_opened_at' => now()]);

        return TrainingSessionResource::make($trainingSession);
    }

    /**
     * Persist the state of a training session.
     */
    public function update(TrainingSessionUpdateRequest $request, TrainingSession $trainingSession, SessionStateWriter $writer): JsonResponse
    {
        Gate::authorize('update', $trainingSession);

        $saved = $writer->write($trainingSession, $request->validated());

        return response()->json([
            'id' => $saved->id,
            'updated_at' => $saved->updated_at,
        ]);
    }

    /**
     * Delete a training session and everything it holds.
     *
     * A corrente é sempre a de maior `last_opened_at`, então a promoção da mais
     * recente restante é consequência da exclusão. O que ainda falta é o caso
     * de não sobrar nenhuma: aí o treino continua com uma sessão vazia (US-11.3).
     */
    public function destroy(Request $request, TrainingSession $trainingSession, CurrentSessionResolver $currentSession): Response
    {
        Gate::authorize('delete', $trainingSession);

        $trainingSession->delete();

        $currentSession->resolve($request->user());

        return response()->noContent();
    }
}
