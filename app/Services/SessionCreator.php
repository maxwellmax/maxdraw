<?php

namespace App\Services;

use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use RuntimeException;

/**
 * O único caminho pelo qual uma sessão de treino nasce: vazia, com os padrões
 * do catálogo (duração 45, estimativa por usuários) e já com a numeração das
 * conexões visível, como a sessão corrente do dono (US-11.2).
 */
class SessionCreator
{
    /**
     * A estimativa com que o protótipo abre a calculadora — o modo por usuários
     * e os números do `est` congelado.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_ESTIMATE = [
        'mode' => 'user',
        'dau' => 1000000,
        'act' => 10,
        'per_month' => 10000000,
        'ratio' => 100,
        'size' => 1,
        'peak' => 3,
        'ret' => 3,
    ];

    public function create(User $user, ?int $problemId = null, ?int $durationMinutes = null): TrainingSession
    {
        return TrainingSession::create([
            'user_id' => $user->id,
            'problem_id' => $problemId,
            'session_duration_id' => $this->durationId($durationMinutes),
            'show_connection_order' => true,
            'elapsed_seconds' => 0,
            'notes' => null,
            'nodes' => [],
            'edges' => [],
            'checks' => [],
            'estimate' => self::DEFAULT_ESTIMATE,
            'last_opened_at' => now(),
        ]);
    }

    private function durationId(?int $minutes): int
    {
        $id = is_null($minutes)
            ? SessionDuration::query()->where('is_default', true)->value('id')
            : SessionDuration::query()->where('minutes', $minutes)->value('id');

        if (is_null($id)) {
            throw new RuntimeException('O catálogo de durações não tem a duração pedida — rode o CatalogSeeder.');
        }

        return (int) $id;
    }
}
