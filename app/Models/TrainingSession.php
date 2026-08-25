<?php

namespace App\Models;

use App\Policies\TrainingSessionPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $name
 * @property int $session_duration_id
 * @property bool $show_connection_order
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
 */
#[Fillable([
    'user_id',
    'problem_id',
    'name',
    'session_duration_id',
    'show_connection_order',
    'elapsed_seconds',
    'notes',
    'nodes',
    'edges',
    'checks',
    'estimate',
    'last_opened_at',
])]
#[UsePolicy(TrainingSessionPolicy::class)]
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
            'show_connection_order' => 'boolean',
            'elapsed_seconds' => 'integer',
            'nodes' => 'array',
            'edges' => 'array',
            'checks' => 'array',
            'estimate' => 'array',
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * O isolamento por usuário (US-1.4) começa aqui: nenhuma leitura de sessão
     * sai sem dono, nem quando a policy já teria recusado o acesso.
     *
     * @param  Builder<TrainingSession>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('user_id'), $user->id);
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
     * O banco guarda a FK da duração; a API fala minutos.
     *
     * @return Attribute<int, never>
     */
    protected function durationMinutes(): Attribute
    {
        return Attribute::get(fn (): int => $this->sessionDuration->minutes);
    }
}
