<?php

namespace App\Services;

use App\Models\TrainingSession;
use App\Models\User;

/**
 * A sessão corrente é derivada, nunca apontada: é a de maior `last_opened_at`
 * do usuário. Sem nenhuma, o treino ainda assim começa — uma sessão vazia é
 * criada na hora (US-11.2).
 */
class CurrentSessionResolver
{
    public function __construct(private SessionCreator $creator) {}

    public function resolve(User $user): TrainingSession
    {
        return TrainingSession::ownedBy($user)
            ->orderByDesc('last_opened_at')
            ->orderByDesc('id')
            ->first()
            ?? $this->creator->create($user);
    }
}
