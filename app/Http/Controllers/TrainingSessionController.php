<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainingSessionUpdateRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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
     * Show a single training session.
     */
    public function show(TrainingSession $trainingSession): TrainingSessionResource
    {
        Gate::authorize('view', $trainingSession);

        return TrainingSessionResource::make($trainingSession);
    }

    /**
     * Persist the state of a training session.
     */
    public function update(TrainingSessionUpdateRequest $request, TrainingSession $trainingSession): TrainingSessionResource
    {
        Gate::authorize('update', $trainingSession);

        $trainingSession->fill($request->validated())->save();

        return TrainingSessionResource::make($trainingSession);
    }

    /**
     * Delete a training session.
     */
    public function destroy(TrainingSession $trainingSession): Response
    {
        Gate::authorize('delete', $trainingSession);

        $trainingSession->delete();

        return response()->noContent();
    }
}
