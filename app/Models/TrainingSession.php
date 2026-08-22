<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A única tabela que a aplicação escreve durante o uso: o diagrama inteiro é
 * salvo como bloco JSON, nunca decomposto em linhas.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $problem_id
 * @property int $session_duration_id
 * @property int $sequence_mode_id
 * @property int $elapsed_seconds
 * @property string|null $notes
 * @property array<int, array<string, mixed>> $nodes
 * @property array<int, array<string, mixed>> $edges
 * @property array<array-key, bool> $checks
 * @property array<string, mixed> $estimate
 * @property CarbonImmutable $last_opened_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int $duration_minutes
 * @property-read User $user
 * @property-read Problem|null $problem
 * @property-read SessionDuration $sessionDuration
 * @property-read SequenceMode $sequenceMode
 */
#[Fillable([
    'user_id',
    'problem_id',
    'session_duration_id',
    'sequence_mode_id',
    'elapsed_seconds',
    'notes',
    'nodes',
    'edges',
    'checks',
    'estimate',
    'last_opened_at',
])]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'elapsed_seconds' => 'integer',
            'nodes' => 'array',
            'edges' => 'array',
            'checks' => 'array',
            'estimate' => 'array',
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Problem, $this>
     */
    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    /**
     * @return BelongsTo<SessionDuration, $this>
     */
    public function sessionDuration(): BelongsTo
    {
        return $this->belongsTo(SessionDuration::class);
    }

    /**
     * @return BelongsTo<SequenceMode, $this>
     */
    public function sequenceMode(): BelongsTo
    {
        return $this->belongsTo(SequenceMode::class);
    }

    /**
     * O banco guarda a FK da duração; a API fala minutos.
     *
     * @return Attribute<int, never>
     */
    protected function durationMinutes(): Attribute
    {
        return Attribute::get(fn (): int => $this->sessionDuration->minutes);
    }
}
