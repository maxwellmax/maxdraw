<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;

/**
 * A camada de autorização do isolamento por usuário (US-1.4). Ela não anda
 * sozinha: toda consulta também sai pelo escopo `ownedBy` do model, para que
 * nem a linha de outro usuário chegue a ser carregada.
 */
class TrainingSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TrainingSession $trainingSession): bool
    {
        return $this->owns($user, $trainingSession);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TrainingSession $trainingSession): bool
    {
        return $this->owns($user, $trainingSession);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TrainingSession $trainingSession): bool
    {
        return $this->owns($user, $trainingSession);
    }

    private function owns(User $user, TrainingSession $trainingSession): bool
    {
        return $trainingSession->user_id === $user->id;
    }
}
