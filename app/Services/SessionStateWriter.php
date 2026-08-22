<?php

namespace App\Services;

use App\Models\SequenceMode;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * O autosave grava o estado inteiro de uma vez: as colunas JSON do diagrama e
 * as colunas próprias saem na mesma transação, para que nenhuma sessão fique
 * com metade do desenho (US-8.1).
 */
class SessionStateWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(TrainingSession $session, array $payload): TrainingSession
    {
        return DB::transaction(function () use ($session, $payload): TrainingSession {
            $session->fill(Arr::only($payload, ['notes', 'elapsed_seconds', 'nodes', 'edges', 'checks', 'estimate']));

            if (array_key_exists('duration_minutes', $payload)) {
                $session->session_duration_id = $this->durationId((int) $payload['duration_minutes']);
            }

            if (array_key_exists('seq_mode', $payload)) {
                $session->sequence_mode_id = $this->sequenceModeId((string) $payload['seq_mode']);
            }

            $session->save();

            return $session;
        });
    }

    private function durationId(int $minutes): int
    {
        return (int) SessionDuration::query()->where('minutes', $minutes)->value('id');
    }

    private function sequenceModeId(string $slug): int
    {
        return (int) SequenceMode::query()->where('slug', $slug)->value('id');
    }
}
